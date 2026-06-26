<?php
declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Figma_API {
    private const API_BASE = 'https://api.figma.com/v1/';
    private const CACHE_TTL = HOUR_IN_SECONDS;
    private const RATE_LIMIT_KEY = 'hello_figma_rate_limit';
    private const TOKEN_OPTION = 'hello_figma_pat';

    private ?string $token = null;

    public function __construct() {
        $stored = get_option(self::TOKEN_OPTION, '');
        $this->token = $this->decrypt_token($stored);
    }

    public function set_token(string $token): void {
        $this->token = $token;
        update_option(self::TOKEN_OPTION, $this->encrypt_token($token));

        if (!get_option('hello_figma_token_created_at', 0)) {
            update_option('hello_figma_token_created_at', time());
        }
    }

    public function get_token(): string {
        return $this->token ?? '';
    }

    public function has_token(): bool {
        return !empty($this->token);
    }

    private function encrypt_token(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }
        $key = wp_salt('AUTH_KEY');
        $iv = openssl_random_pseudo_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }

    private function decrypt_token(string $ciphertext_b64): string {
        if ($ciphertext_b64 === '') {
            return '';
        }

        $data = base64_decode($ciphertext_b64, true);
        if ($data === false || strlen($data) < 16) {
            return $ciphertext_b64;
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $key = wp_salt('AUTH_KEY');
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            return $ciphertext_b64;
        }

        return $decrypted;
    }

    /**
     * Get file data from Figma API.
     *
     * @param string $file_key Figma file key
     * @param string|null $node_id Specific node ID (optional)
     * @param int|null $depth Response depth (1=canvases, 2=frames, null=full)
     * @return array|null
     */
    public function get_file(string $file_key, ?string $node_id = null, ?int $depth = null): ?array {
        $cache_key = 'hello_figma_file_' . $file_key . ($node_id ? "_$node_id" : '') . ($depth ? "_d{$depth}" : '');
        $cached = get_transient($cache_key);
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
            set_transient($cache_key, $data, $ttl);
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
    public function get_file_nodes(string $file_key, array $node_ids): ?array {
        if (empty($node_ids)) {
            return null;
        }

        $ids = implode(',', $node_ids);
        $cache_key = 'hello_figma_nodes_' . $file_key . '_' . md5($ids);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("files/{$file_key}/nodes?" . http_build_query(['ids' => $ids]));
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', self::CACHE_TTL, 'nodes');
            set_transient($cache_key, $data, $ttl);
        }

        return $data;
    }

    /**
     * Get file styles (colors, typography, effects).
     *
     * @param string $file_key Figma file key
     * @return array|null
     */
    public function get_styles(string $file_key): ?array {
        $cache_key = 'hello_figma_styles_' . $file_key;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("files/{$file_key}/styles");
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', HOUR_IN_SECONDS * 2, 'styles');
            set_transient($cache_key, $data, $ttl);
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
    public function get_images(string $file_key, array $node_ids, string $format = 'png'): ?array {
        if (empty($node_ids)) {
            return null;
        }

        $ids = implode(',', $node_ids);
        $cache_key = 'hello_figma_images_' . $file_key . '_' . md5($ids) . "_$format";
        $cached = get_transient($cache_key);
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
            set_transient($cache_key, $data['images'], $ttl);
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
    public function get_variables(string $file_key): ?array {
        $cache_key = 'hello_figma_variables_' . $file_key;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("files/{$file_key}/variables/local");
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', HOUR_IN_SECONDS * 2, 'variables');
            set_transient($cache_key, $data, $ttl);
        }

        return $data;
    }

    /**
     * Get team style data (shared styles).
     *
     * @param string $team_id Team ID
     * @return array|null
     */
    public function get_team_styles(string $team_id): ?array {
        $cache_key = 'hello_figma_team_styles_' . $team_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->request("teams/{$team_id}/styles");
        if ($data !== null) {
            $ttl = apply_filters('hello_figma_cache_ttl', HOUR_IN_SECONDS * 4, 'team_styles');
            set_transient($cache_key, $data, $ttl);
        }

        return $data;
    }

    /**
     * Perform a GET request to Figma API.
     *
     * @param string $endpoint API endpoint
     * @return array|null Decoded response
     */
    private function request(string $endpoint): ?array {
        if (empty($this->token)) {
            Logger::log('WARNING', 'FigmaAPI', 'Request skipped — no token set', [
                'endpoint' => $endpoint,
            ]);
            return null;
        }

        $this->enforce_rate_limit();

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
            $retry_after = (int) wp_remote_retrieve_header($response, 'Retry-After');
            if ($retry_after > 0 && $retry_after <= 60) {
                Logger::log('WARNING', 'FigmaAPI', 'Rate limited — retrying after delay', [
                    'retry_after' => $retry_after,
                    'endpoint' => $endpoint,
                ]);
                sleep($retry_after);
                return $this->request($endpoint);
            }
        }

        if ($code !== 200) {
            $error_message = sprintf(
                'Figma API error [%d]: %s',
                $code,
                wp_remote_retrieve_response_message($response)
            );
            Logger::log('WARNING', 'FigmaAPI', 'Non-200 Figma API response', [
                'http_code' => $code,
                'response_message' => wp_remote_retrieve_response_message($response),
                'body_preview' => substr($body, 0, 500),
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
    private function enforce_rate_limit(): void {
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
    private function update_rate_budget(array $response, string $endpoint): void {
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
    public function test_token(): bool {
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
    public function get_thumbnail_urls(string $file_key, array $node_ids): array {
        if (empty($node_ids)) {
            return [];
        }

        $ids = implode(',', $node_ids);
        $cache_key = 'hello_figma_thumb_' . $file_key . '_' . md5($ids);
        $cached = get_transient($cache_key);
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
            set_transient($cache_key, $data['images'], $ttl);
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
    public function get_token_expiry_info(): ?string {
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
    public function clear_cache(): void {
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
