<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Logger
{
    private static ?string $run_id = null;
    private static ?string $log_dir = null;

    public static function start_run(string $run_id): void
    {
        self::$run_id = $run_id;
    }

    /** @param array<string, mixed> $context */
    public static function log(string $level, string $component, string $message, array $context = []): void
    {
        try {
            if (self::$log_dir === null) {
                $upload = wp_upload_dir();
                if (!empty($upload['basedir'])) {
                    self::$log_dir = $upload['basedir'] . '/hello-figma-logs';
                } else {
                    return;
                }
            }

            if (!is_dir(self::$log_dir)) {
                wp_mkdir_p(self::$log_dir);
            }

            $index = self::$log_dir . '/index.php';
            if (!file_exists($index)) {
                @file_put_contents($index, '<?php // Silence is golden.');
            }

            $date = current_time('Y-m-d');
            $log_file = self::$log_dir . "/import-{$date}.log";

            $timestamp = current_time('Y-m-d H:i:s');
            $run = self::$run_id !== null ? ' [' . self::$run_id . ']' : '';
            $ctx = !empty($context) ? ' | context=' . wp_json_encode($context) : '';

            $line = "[{$timestamp}]{$run} [{$level}] [{$component}] {$message}{$ctx}" . PHP_EOL;

            file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // fail silently — never let logging break the import
        }
    }

    public static function get_latest_log_contents(int $lines = 200): string
    {
        try {
            $date = current_time('Y-m-d');
            $log_file = self::get_log_dir() . "/import-{$date}.log";
            if (!file_exists($log_file)) {
                return '';
            }

            $content = file_get_contents($log_file);
            if ($content === false || $content === '') {
                return '';
            }

            $all_lines = explode(PHP_EOL, $content);
            $tail = array_slice($all_lines, -$lines);
            return implode(PHP_EOL, $tail);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function get_log_dir(): string
    {
        if (self::$log_dir === null) {
            $upload_dir = wp_upload_dir();
            self::$log_dir = $upload_dir['basedir'] . '/hello-figma-logs';
        }
        return self::$log_dir;
    }
}
