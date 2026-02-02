<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Helper;

use Configuration;
use DateTime;
use Tools;
use Db;
use PrestaShopLogger;

final class SimPayLogger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    private static ?int $defaultOrderId = null;

    public static function setDefaultOrderId(int $orderId): void
    {
        self::$defaultOrderId = $orderId;
    }

    public static function log(string $type, string $message, array $context = []): void
    {
        if (!isset($context['id_order']) && self::$defaultOrderId) {
            $context['id_order'] = self::$defaultOrderId;
        }

        self::writeDb($type, $message, $context);
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

        PrestaShopLogger::addLog(
            '[SimPay] ' . $message,
            3,
            0,
            'SimPay',
            0,
            true
        );
    }

    public static function respond(int $code, string $body, string $stage, array $context = []): void
    {
        self::log('resp', $stage, array_merge(['code' => $code, 'body' => $body], $context));
        http_response_code($code);
        die($body);
    }

    private static function processRecord(string $type, string $message, array $context): string
    {
        $out = self::interpolate($message, $context);

        // Uppercase level without relying on Tools (safe fallback)
        $level = class_exists(Tools::class) ? Tools::strtoupper($type) : strtoupper($type);

        return self::getTimestamp() . ' ' . $level . ' ' . $out . PHP_EOL;
    }

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
                        : (string)$val;
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
        $micro = sprintf('%06d', (int)(($now - floor($now)) * 1000000));

        $dt = DateTime::createFromFormat('U.u', sprintf('%.6f', $now));
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s.u');
        }

        // Fallback (should rarely happen)
        return date('Y-m-d H:i:s') . '.' . $micro;
    }

    private static function writeDb(string $type, string $message, array $context): void
    {
        try {
            $orderId = self::extractOrderId($context);
            if (!$orderId && self::$defaultOrderId) {
                $orderId = self::$defaultOrderId;
            }

            Db::getInstance()->insert('simpay_payment_log', [
                'id_order' => $orderId ?: null,
                'level' => pSQL($type),
                'message' => pSQL($message),
                'context' => pSQL(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                '[SimPay] Logger DB write failed: ' . $e->getMessage(),
                3,
                0,
                'SimPay',
                0,
                true
            );
        }
    }

    private static function extractOrderId(array $context): ?int
    {
        foreach (['order_id', 'id_order', 'order'] as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $val = $context[$key];

            if (is_int($val) || (is_string($val) && ctype_digit($val))) {
                return (int)$val;
            }

            if (is_object($val) && isset($val->id)) {
                return (int)$val->id;
            }

            if (is_array($val) && isset($val['id']) && ctype_digit((string)$val['id'])) {
                return (int)$val['id'];
            }
        }

        // Fallback: try to read from request
        if (class_exists(Tools::class)) {
            $reqOrderId = Tools::getValue('id_order') ?: Tools::getValue('order_id');
            if (is_string($reqOrderId) && ctype_digit($reqOrderId)) {
                return (int)$reqOrderId;
            }
        }

        return null;
    }

    public function getPaymentLogsForOrder(int $orderId, int $limit = 50): array
    {
        $sql = sprintf(
            'SELECT * FROM `%1$ssimpay_payment_log` WHERE `id_order` = %2$d ORDER BY `id_simpay_payment_log` ASC LIMIT %3$d',
            _DB_PREFIX_,
            (int) $orderId,
            (int) $limit
        );

        return (array) Db::getInstance()->executeS($sql);
    }
}
