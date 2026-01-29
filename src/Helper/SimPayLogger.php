<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Helper;

use Configuration;
use DateTime;
use Tools;

final class SimPayLogger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    private const CFG_ENABLED = 'SIMPAY_DEBUG_LOGS_ENABLED';

    // If true, each request starts with empty file
    private static bool $truncateOnStart = true;

    // Internal: ensures truncate happens only once per request
    private static bool $didTruncate = false;

    public static function enableTruncateOnStart(bool $enabled): void
    {
        self::$truncateOnStart = $enabled;
    }

    public static function reset(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::$didTruncate = true; // prevent double truncate this request
        @file_put_contents(self::getFilePath(), '');
    }

    public static function log(string $type, string $message, array $context = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $path = self::getFilePath();

        // Truncate once per request (optional)
        if (self::$truncateOnStart && !self::$didTruncate) {
            @file_put_contents($path, '');
            self::$didTruncate = true;
        }

        @file_put_contents($path, self::processRecord($type, $message, $context), FILE_APPEND);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    public static function respond(int $code, string $body, string $stage, array $context = []): void
    {
        self::log('resp', $stage, array_merge(['code' => $code, 'body' => $body], $context));
        http_response_code($code);
        die($body);
    }

    private static function isEnabled(): bool
    {
        // If Configuration is not available yet, log anyway (webhook debugging)
        try {
            return (int) true === 1;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private static function processRecord(string $type, string $message, array $context): string
    {
        $out = self::interpolate($message, $context);

        // Uppercase level without relying on Tools (safe fallback)
        $level = class_exists(Tools::class) ? Tools::strtoupper($type) : strtoupper($type);

        return self::getTimestamp() . ' ' . $level . ' ' . $out . PHP_EOL;
    }

    // Paynow-style {} placeholders interpolation
    private static function interpolate(string $message, array $context): string
    {
        $hasPlaceholders = strpos($message, '{}') !== false;

        if ($hasPlaceholders) {
            $split = explode('{}', $message);
            $out = '';

            $count = count($split);
            for ($i = 0; $i < $count; $i++) {
                if ($i > 0 && array_key_exists($i - 1, $context)) {
                    $val = $context[$i - 1];
                    $out .= is_array($val)
                        ? json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string) $val;
                }
                $out .= $split[$i];
            }

            return $out;
        }

        // If no placeholders, append context as JSON (but keep it readable)
        if (!empty($context)) {
            return $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $message;
    }

    private static function getTimestamp(): string
    {
        // Keep microseconds (do NOT cast to int)
        $now = microtime(true);
        $micro = sprintf('%06d', (int) (($now - floor($now)) * 1000000));

        $dt = DateTime::createFromFormat('U.u', sprintf('%.6f', $now));
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s.u');
        }

        // Fallback (should rarely happen)
        return date('Y-m-d H:i:s') . '.' . $micro;
    }

    private static function getFilePath(): string
    {
        $root = defined('_PS_ROOT_DIR_') ? (string) _PS_ROOT_DIR_ : null;

        $logDir = $root
            ? rtrim($root, '/') . '/modules/simpay/logs'
            : sys_get_temp_dir() . '/simpay-logs';

        // Try create dir
        if (!is_dir($logDir)) {
            $mk = @mkdir($logDir, 0775, true);
            if (!$mk) {
                $err = error_get_last();
                error_log('[SimPayLogger] mkdir failed: ' . $logDir . ' | ' . ($err['message'] ?? 'unknown'));
            }
        }

        // Check writable
        if (!is_dir($logDir)) {
            error_log('[SimPayLogger] logDir not a dir: ' . $logDir);
            return sys_get_temp_dir() . '/simpay-ipn.log';
        }

        if (!is_writable($logDir)) {
            error_log('[SimPayLogger] logDir not writable: ' . $logDir);
            error_log('[SimPayLogger] perms: ' . substr(sprintf('%o', @fileperms($logDir)), -4));
            error_log('[SimPayLogger] owner: ' . (@fileowner($logDir) ?: 'n/a') . ' group: ' . (@filegroup($logDir) ?: 'n/a'));
            return sys_get_temp_dir() . '/simpay-ipn.log';
        }

        $file = $logDir . '/simpay-ipn.log';

        // Smoke test write (only for debugging; safe and tiny)
        if (!file_exists($file)) {
            $ok = @file_put_contents($file, '');
            if ($ok === false) {
                $err = error_get_last();
                error_log('[SimPayLogger] file create failed: ' . $file . ' | ' . ($err['message'] ?? 'unknown'));
                return sys_get_temp_dir() . '/simpay-ipn.log';
            }
        }

        return $file;
    }
}