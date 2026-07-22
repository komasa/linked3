<?php

declare(strict_types=1);
/**
 * Payload Sanitizer — strips secrets from data before it hits the log file.
 *
 * Direct port of aipower's AIPKit_Payload_Sanitizer philosophy:
 *   - Base64 images are replaced with [REDACTED base64_size=X mime=Y]
 *   - Known secret keys (api_key, Authorization, password, etc.) are masked
 *   - Recursive walk over arrays/objects
 *
 * @package Linked3
 * @subpackage Log
 */

namespace Linked3\Includes\Log;

if (!defined('ABSPATH')) {
    exit;
}

final class PayloadSanitizer
{
    const SECRET_KEYS = [
        'api_key', 'apikey', 'api-key',
        'authorization', 'auth',
        'password', 'passwd', 'pwd',
        'secret', 'client_secret', 'access_token', 'refresh_token',
        'license_key', 'license',
        'stripe_key', 'stripe_secret',
        'private_key', 'cert',
        'session_id', 'csrf', 'nonce',
    ];

    const MAX_STRING_LEN = 8192;

    /**
     * @param mixed $data
     * @return mixed
     */
    public static function sanitize_for_logging($data)
    {
        return self::walk($data, 0);
    }

    /**
     * @param mixed $data
     * @param int   $depth
     * @return mixed
     */
    private static function walk($data, int $depth)
    {
        if ($depth > 8) {
            return '[truncated:depth]';
        }
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = self::is_secret_key($k)
                    ? (is_scalar($v) ? self::mask((string) $v) : '[redacted]')
                    : self::walk($v, $depth + 1);
            }
            return $out;
        }
        if (is_object($data)) {
            $arr = (array) $data;
            return self::walk($arr, $depth);
        }
        if (is_string($data)) {
            return self::sanitize_string($data);
        }
        return $data;
    }

    /**
     * @param string $key
     * @return bool
     */
    static function is_secret_key(string $key): bool {
        $k = strtolower((string) $key);
        if (in_array($k, self::SECRET_KEYS, true)) {
            return true;
        }
        foreach (self::SECRET_KEYS as $s) {
            if (strpos($k, $s) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $s
     * @return string
     */
    private static function mask(string $s): string
    {
        $len = strlen($s);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($s, 0, 2) . str_repeat('*', min(8, $len - 4)) . substr($s, -2);
    }

    /**
     * @param string $s
     * @return string
     */
    private static function sanitize_string(string $s): string
    {
        // Detect base64 image payloads.
        if (preg_match('/^data:(image\/[a-z+]+);base64,/i', $s, $m)) {
            $size = strlen($s);
            return '[REDACTED base64_size=' . $size . ' mime=' . $m[1] . ']';
        }
        // Raw base64 of plausible image size (>= 1KB, mostly base64 chars).
        if (strlen($s) > 1024 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $s)) {
            return '[REDACTED base64_size=' . strlen($s) . ']';
        }
        if (strlen($s) > self::MAX_STRING_LEN) {
            return substr($s, 0, self::MAX_STRING_LEN) . '...[truncated:' . strlen($s) . ']';
        }
        return $s;
    }
}
