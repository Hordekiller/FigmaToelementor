<?php

/**
 * Minimal WordPress function stubs for CLI test environment.
 */

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        return trim(preg_replace('/[^a-z0-9\-_]+/', '-', strtolower($title)), '-');
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        return ['basedir' => '/tmp/hello-figma-test-logs', 'baseurl' => 'http://localhost'];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $dir): bool
    {
        return is_dir($dir) || mkdir($dir, 0777, true);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $flags = 0, int $depth = 512): string
    {
        return json_encode($data, $flags, $depth);
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value, bool $autoload = true): bool
    {
        return true;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'AUTH_KEY'): string
    {
        return 'test-salt-value-0123456789abcdef';
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

// Only stub __ if it doesn't exist (PHP has no native __())
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
