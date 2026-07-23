<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Figma_API
{
    private const API_BASE = 'https://api.figma.com/v1/';
    private const CACHE_TTL = HOUR_IN_SECONDS;
    private const RATE_LIMIT_KEY = 'hello_figma_rate_limit';
    private const TOKEN_OPTION = 'hello_figma_pat';
    private const CRYPTO_VER_OPTION = 'hello_figma_crypto_version';
    private const CACHE_METRIC_OPTION = 'hello_figma_cache_metrics';

    // Log cache hit/miss at most once per N calls (1 in 20 = 5% sample)
    private const CACHE_METRICS_SAMPLE_DENOM = 20;

    // Crypto version constants
    private const CRYPTO_V1_CBC = 1; // aes-256-cbc (legacy)
    private const CRYPTO_V2_GCM = 2; // aes-256-gcm (AEAD, current)

    private const CRYPTO_V2_PREFIX = 'v2:';

    // GCM uses a 12-byte IV (NIST recommendation) + 16-byte authentication tag
    private const GCM_IV_LEN = 12;
    private const GCM_TAG_LEN = 16;

    private ?string $token = null;
    private ?string $last_error = null;

    public function __construct()
    {
        $stored = get_option(self::TOKEN_OPTION, '');
        $this->token = $this->decrypt_token($stored);
    }

    /**
     * Return the last descriptive error message set by the API client,
     * or null if no error occurred.
     */
    public function get_last_error(): ?string
    {
        return $this->last_error;
    }

    public function set_token(string $token): void
    {
        $this->token = $token;
        update_option(self::TOKEN_OPTION, $this->encrypt_token($token));

        if (!get_option('hello_figma_token_created_at', 0)) {
            update_option('hello_figma_token_created_at', time());
        }
    }

    /**
     * Re-encrypt the stored token using the latest crypto (v2 GCM).
     * Called explicitly when you want to upgrade an existing token that
     * was stored with an older cipher (v1 CBC).
     */
    public function upgrade_token_crypto(): bool
    {
        $stored = get_option(self::TOKEN_OPTION, '');
        if ($stored === '') {
            return false;
        }

        $plaintext = $this->decrypt_token($stored);
        if ($plaintext === '' || $plaintext === $stored) {
            return false; // Could not decrypt
        }

        // If it's already v2 GCM, nothing to do
        if (str_starts_with($stored, self::CRYPTO_V2_PREFIX)) {
            return true;
        }

        // Re-encrypt with v2 GCM
        update_option(self::TOKEN_OPTION, $this->encrypt_token($plaintext));
        update_option(self::CRYPTO_VER_OPTION, self::CRYPTO_V2_GCM);
        return true;
    }

    public function get_token(): string
    {
        return $this->token ?? '';
    }

    public function has_token(): bool
    {
        return !empty($this->token);
    }

    private function encrypt_token(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = wp_salt('AUTH_KEY');

        // aes-256-gcm: 12-byte IV, 16-byte tag (AEAD)
        $iv = openssl_random_pseudo_bytes(self::GCM_IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            // Fallback to CBC if GCM is unavailable (very old PHP)
            $iv = openssl_random_pseudo_bytes(16);
            $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            update_option(self::CRYPTO_VER_OPTION, self::CRYPTO_V1_CBC);
            return base64_encode($iv . $ciphertext);
        }

        update_option(self::CRYPTO_VER_OPTION, self::CRYPTO_V2_GCM);
        return self::CRYPTO_V2_PREFIX . base64_encode($iv . $ciphertext . $tag);
    }

    private function decrypt_token(string $stored): string
    {
        if ($stored === '') {
            return '';
        }

        // v2: base64(iv + ciphertext + tag) — AEAD (aes-256-gcm)
        if (str_starts_with($stored, self::CRYPTO_V2_PREFIX)) {
            $data_b64 = substr($stored, strlen(self::CRYPTO_V2_PREFIX));
            $data = base64_decode($data_b64, true);
            if ($data === false || strlen($data) < self::GCM_IV_LEN + self::GCM_TAG_LEN) {
                return $stored; // Corrupt — return as-is to avoid data loss
            }

            $iv = substr($data, 0, self::GCM_IV_LEN);
            $tag = substr($data, -self::GCM_TAG_LEN);
            $ciphertext = substr($data, self::GCM_IV_LEN, -self::GCM_TAG_LEN);
            $key = wp_salt('AUTH_KEY');

            $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return $decrypted !== false ? $decrypted : $stored;
        }

        // v1 (legacy): base64(iv(16) + ciphertext) — aes-256-cbc
        $data = base64_decode($stored, true);
        if ($data === false || strlen($data) < 16) {
            return $stored;
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $key = wp_salt('AUTH_KEY');
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : $stored;
    }

    /**
     * Get file data from Figma API.
     *
     * @param string $file_key Figma file key
     * @param string|null $node_id Specific node ID (optional)
     * @param int|null $depth Response depth (1=canvases, 2=frames, null=full)
     * @return array|null
     */
    /**
     * Wrapper for cache reads that logs hit/miss at a configurable sample rate.
     *
     * @param string $cache_key
     * @param string $context  Label for metrics (e.g. 'file', 'nodes', 'styles')
     * @return mixed The cached value, or false on miss
     */
    private function cache_get(string $cache_key, string $context = 'general')
    {
        $value = get_transient($cache_key);
        $this->log_cache_metric($value !== false ? 'hit' : 'miss', $context);
        return $value;
    }

    /**
     * Wrapper for cache writes.
     */
    private function cache_set(string $cache_key, $data, int $ttl, string $context = 'general'): void
    {
        set_transient($cache_key, $data, $ttl);
        $this->log_cache_metric('set', $context);
    }

    /**
     * Log cache metric at a configurable sample rate.
     *
     * Sample rate default: 1 in CACHE_METRICS_SAMPLE_DENOM calls.
     * Override via filter: add_filter('hello_figma_cache_metrics_sample_rate', fn() => 5);
     */
    private function log_cache_metric(string $event, string $context): void
    {
        $sample_rate = (int) apply_filters('hello_figma_cache_metrics_sample_rate', self::CACHE_METRICS_SAMPLE_DENOM);
        if ($sample_rate < 1) {
            $sample_rate = 1;
        }

        $counter = get_transient(self::CACHE_METRIC_OPTION);
        if ($counter === false) {
            $counter = 0;
        }
        $counter++;
        set_transient(self::CACHE_METRIC_OPTION, $counter, DAY_IN_SECONDS);

        if ($counter % $sample_rate === 0) {
            Logger::log('INFO', 'CacheMetrics', "cache_{$event}", [
                'context' => $context,
                'sample_rate' => $sample_rate,
            ]);
        }
    }

    public function get_file(string $file_key, ?string $node_id = null, ?int $depth = null): ?array
    {
        $cache_key = 'hello_figma_file_' . $file_key . ($node_id ? "_$node_id" : '') . ($depth ? "_d{$depth}" : '');
        $cached = $this->cache_get($cache_key, 'file');
        if ($cached !== false) {
            return $cached;
        }

        $endpoint = "files/{$file_key}";
        $params = [];
        if ($node_id) {
            $params['ids'] = $node_id;
        }
        if ($depth !== null) {
            $params['depth'] = $depth;
        }
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }

        $data = $this->request($endpoint);
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', self::CACHE_TTL, 'file');
            $this->cache_set($cache_key, $data, $ttl, 'file');
        }

        return $data;
    }

    /**
     * Get file nodes (batch request).
     *
     * @param string $file_key Figma file key
     * @param array $node_ids Array of node IDs
     * @return array|null
     */
    public function get_file_nodes(string $file_key, array $node_ids): ?array
    {
        if (empty($node_ids)) {
            return null;
        }

        $ids = implode(',', $node_ids);
        $cache_key = 'hello_figma_nodes_' . $file_key . '_' . md5($ids);
        $cached = $this->cache_get($cache_key, 'nodes');
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("files/{$file_key}/nodes?" . http_build_query(['ids' => $ids]));
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', self::CACHE_TTL, 'nodes');
            $this->cache_set($cache_key, $data, $ttl, 'nodes');
        }

        return $data;
    }

    /**
     * Get file styles (colors, typography, effects).
     *
     * @param string $file_key Figma file key
     * @return array|null
     */
    public function get_styles(string $file_key): ?array
    {
        $cache_key = 'hello_figma_styles_' . $file_key;
        $cached = $this->cache_get($cache_key, 'styles');
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("files/{$file_key}/styles");
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', HOUR_IN_SECONDS * 2, 'styles');
            $this->cache_set($cache_key, $data, $ttl, 'styles');
        }

        return $data;
    }

    /**
     * Download images from Figma.
     *
     * @param string $file_key Figma file key
     * @param array $node_ids Array of node IDs to export
     * @param string $format Image format (png, jpg, svg, pdf)
     * @return array|null Map of node_id => image_url
     */
    public function get_images(string $file_key, array $node_ids, string $format = 'png'): ?array
    {
        if (empty($node_ids)) {
            return null;
        }

        $ids = implode(',', $node_ids);
        $cache_key = 'hello_figma_images_' . $file_key . '_' . md5($ids) . "_$format";
        $cached = $this->cache_get($cache_key, 'images');
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("images/{$file_key}?" . http_build_query([
            'ids' => $ids,
            'format' => $format,
            'scale' => 2,
        ]));
        if ($data !== null && isset($data['images'])) {
            $ttl = apply_filters('hello_figma_cache_ttl', self::CACHE_TTL, 'images');
            $this->cache_set($cache_key, $data['images'], $ttl, 'images');
            return $data['images'];
        }

        return null;
    }

    /**
     * Get Figma variables (design tokens).
     *
     * @param string $file_key Figma file key
     * @return array|null
     */
    public function get_variables(string $file_key): ?array
    {
        $cache_key = 'hello_figma_variables_' . $file_key;
        $cached = $this->cache_get($cache_key, 'variables');
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("files/{$file_key}/variables/local");
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', HOUR_IN_SECONDS * 2, 'variables');
            $this->cache_set($cache_key, $data, $ttl, 'variables');
        }

        return $data;
    }

    /**
     * Get team style data (shared styles).
     *
     * @param string $team_id Team ID
     * @return array|null
     */
    public function get_team_styles(string $team_id): ?array
    {
        $cache_key = 'hello_figma_team_styles_' . $team_id;
        $cached = $this->cache_get($cache_key, 'team_styles');
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("teams/{$team_id}/styles");
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', HOUR_IN_SECONDS * 4, 'team_styles');
            $this->cache_set($cache_key, $data, $ttl, 'team_styles');
        }

        return $data;
    }

    /**
     * Perform a GET request to Figma API.
     *
     * @param string $endpoint API endpoint
     * @return array|null Decoded response
     */
    private function request(string $endpoint): ?array
    {
        $this->enforce_rate_limit();
        return $this->do_request($endpoint, 3);
    }

    private function do_request(string $endpoint, int $retries_left): ?array
    {
        $this->last_error = null;

        if (empty($this->token)) {
            Logger::log('WARNING', 'FigmaAPI', 'Request skipped — no token set', [
                'endpoint' => $endpoint,
            ]);
            return null;
        }

        $url = self::API_BASE . ltrim($endpoint, '/');
        $redacted_url = str_replace($this->token, '[REDACTED]', $url);

        Logger::log('INFO', 'FigmaAPI', 'Figma API request', [
            'url' => $redacted_url,
            'endpoint' => $endpoint,
            'method' => 'GET',
        ]);

        $response = wp_remote_get($url, [
            'headers' => [
                'X-Figma-Token' => $this->token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => apply_filters('hello_figma_api_timeout', 120),
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            Logger::log('ERROR', 'FigmaAPI', 'Figma API request failed', [
                'error_message' => $error_msg,
                'url' => $redacted_url,
                'endpoint' => $endpoint,
            ]);
            do_action('hello_figma_api_error', $error_msg, $endpoint);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        Logger::log('INFO', 'FigmaAPI', 'Figma API response received', [
            'http_code' => $code,
            'body_length' => strlen($body),
            'url' => $redacted_url,
            'endpoint' => $endpoint,
        ]);

        if ($code === 429) {
            if ($retries_left <= 1) {
                Logger::log('ERROR', 'FigmaAPI', 'Rate limited — max retries exhausted', [
                    'endpoint' => $endpoint,
                ]);
                return null;
            }
            $retry_after = (int) wp_remote_retrieve_header($response, 'Retry-After');
            // Cap wait at 60s; default 5s if no Retry-After header
            $delay = max(1, min(60, $retry_after > 0 ? $retry_after : 5));
            Logger::log('WARNING', 'FigmaAPI', 'Rate limited — retrying', [
                'retry_after' => $retry_after,
                'delay' => $delay,
                'retries_left' => $retries_left - 1,
                'endpoint' => $endpoint,
            ]);
            sleep($delay);
            return $this->do_request($endpoint, $retries_left - 1);
        }

        if ($code !== 200) {
            $body_preview = substr($body, 0, 500);

            // Detect unsupported file types (Make/Slides/Board etc.)
            if ($code === 400 && stripos($body, 'File type not supported') !== false) {
                $this->last_error = __(
                    'This file type is not supported by the Figma API. Please use a standard Figma Design file (figma.com/file/... or figma.com/design/...). Figma Slides, Make, and Board files are not supported.',
                    'hello-figma'
                );
                Logger::log('WARNING', 'FigmaAPI', 'Unsupported Figma file type', [
                    'http_code' => $code,
                    'body_preview' => $body_preview,
                    'endpoint' => $endpoint,
                ]);
                do_action('hello_figma_api_error', $this->last_error, $endpoint);
                return null;
            }

            $error_message = sprintf(
                'Figma API error [%d]: %s',
                $code,
                wp_remote_retrieve_response_message($response)
            );
            Logger::log('WARNING', 'FigmaAPI', 'Non-200 Figma API response', [
                'http_code' => $code,
                'response_message' => wp_remote_retrieve_response_message($response),
                'body_preview' => $body_preview,
                'endpoint' => $endpoint,
            ]);
            do_action('hello_figma_api_error', $error_message, $endpoint);
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('ERROR', 'FigmaAPI', 'Invalid JSON response from Figma', [
                'json_error' => json_last_error_msg(),
                'endpoint' => $endpoint,
            ]);
            do_action('hello_figma_api_error', 'Invalid JSON response', $endpoint);
            return null;
        }

        $this->update_rate_budget($response, $endpoint);

        return $data;
    }

    /**
     * Ensure we don't exceed Figma's rate limit (2 requests/second).
     */
    private function enforce_rate_limit(): void
    {
        $last_request = get_transient(self::RATE_LIMIT_KEY);
        if ($last_request !== false) {
            $elapsed = microtime(true) - (float) $last_request;
            if ($elapsed < 0.5) {
                usleep((int) ((0.5 - $elapsed) * 1_000_000));
            }
        }
        set_transient(self::RATE_LIMIT_KEY, (string) microtime(true), 10);

        // Respect X-RateLimit-Remaining header if present
        $remaining_requests = get_transient('hello_figma_rate_remaining');
        if ($remaining_requests !== false && (int) $remaining_requests <= 2) {
            usleep(500_000);
        }
    }

    /**
     * Update rate limit budget based on response headers.
     */
    private function update_rate_budget(array $response, string $endpoint): void
    {
        $remaining = wp_remote_retrieve_header($response, 'X-RateLimit-Remaining');
        if ($remaining !== '') {
            set_transient('hello_figma_rate_remaining', (int) $remaining, 60);
            Logger::log('DEBUG', 'FigmaAPI', 'Rate limit budget updated', [
                'remaining' => $remaining,
                'endpoint' => $endpoint,
            ]);
        }
    }

    /**
     * Test if the current token is valid by calling the /me endpoint.
     *
     * @return bool True if token is valid
     */
    public function test_token(): bool
    {
        if (empty($this->token)) {
            return false;
        }

        $response = wp_remote_get(self::API_BASE . 'me', [
            'headers' => [
                'X-Figma-Token' => $this->token,
            ],
            'timeout' => apply_filters('hello_figma_api_timeout', 15),
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get low-resolution thumbnail URLs for preview purposes.
     *
     * Uses scale=1 (smaller/faster) and format=png. Batch-requests all node IDs
     * in a single API call.
     *
     * @param string $file_key Figma file key
     * @param array  $node_ids Array of node IDs to export
     * @return array Map of node_id => thumbnail_url (empty array on failure)
     */
    public function get_thumbnail_urls(string $file_key, array $node_ids): array
    {
        if (empty($node_ids)) {
            return [];
        }

        $ids = implode(',', $node_ids);
        $cache_key = 'hello_figma_thumb_' . $file_key . '_' . md5($ids);
        $cached = $this->cache_get($cache_key, 'thumbnail');
        if ($cached !== false) {
            return $cached;
        }

        Logger::log('INFO', 'FigmaAPI', 'Fetching thumbnail URLs', [
            'file_key' => $file_key,
            'node_count' => count($node_ids),
        ]);

        $data = $this->request("images/{$file_key}?" . http_build_query([
            'ids' => $ids,
            'format' => 'png',
            'scale' => 1,
        ]));

        if ($data !== null && isset($data['images'])) {
            $ttl = apply_filters('hello_figma_cache_ttl', self::CACHE_TTL, 'thumbnail');
            $this->cache_set($cache_key, $data['images'], $ttl, 'thumbnail');
            return $data['images'];
        }

        Logger::log('WARNING', 'FigmaAPI', 'get_thumbnail_urls failed', [
            'file_key' => $file_key,
            'node_ids' => $node_ids,
        ]);

        return [];
    }

    /**
     * Get remaining token expiry notice (approximate from PAT format).
     *
     * @return string|null Human-readable expiry info or null if unknown
     */
    public function get_token_expiry_info(): ?string
    {
        if (empty($this->token)) {
            return null;
        }

        // Figma PATs generated after Nov 2025 expire in 90 days.
        // We store the creation date when the token is first set.
        $created_at = get_option('hello_figma_token_created_at', 0);
        if ($created_at === 0) {
            return null;
        }

        $expires_at = $created_at + 90 * DAY_IN_SECONDS;
        $remaining = $expires_at - time();

        if ($remaining <= 0) {
            return __('Token has expired. Please generate a new one.', 'hello-figma');
        }

        return sprintf(
            /* translators: %d: number of days */
            __('Token expires in %d days.', 'hello-figma'),
            (int) ceil($remaining / DAY_IN_SECONDS)
        );
    }

    /**
     * Clear all cached Figma data.
     */
    public function clear_cache(): void
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_hello_figma_') . '%'
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_timeout_hello_figma_') . '%'
            )
        );
    }
}
