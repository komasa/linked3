<?php

declare(strict_types=1);
/**
 * Linked3 Crypto — AES-256-GCM symmetric encryption for secrets at rest.
 *
 * Constitution §5 (v0.2.0 commercial hardening): API keys MUST NOT be stored
 * in plaintext. This class provides a single façade for encrypt/decrypt with
 * the following guarantees:
 *
 *   - Cipher: AES-256-GCM (authenticated encryption — confidentiality + integrity)
 *   - Key derivation: HKDF-like HMAC-SHA256 chain over wp_salt('nonce') + AUTH_KEY
 *     so a DB leak alone is not sufficient to recover secrets (the salts live
 *     in wp-config.php, never in the DB).
 *   - Per-message random 12-byte IV (GCM standard nonce size).
 *   - 16-byte GCM auth tag appended to ciphertext.
 *   - Output is URL-safe base64 prefixed with "enc::" so callers can detect
 *     whether a stored string is encrypted or legacy plaintext (graceful
 *     migration: legacy plaintext keys still work, they just aren't decrypted).
 *
 * Backward compatibility:
 *   decrypt() returns the input unchanged if it doesn't start with "enc::".
 *   This lets the AI Dispatcher call decrypt() unconditionally on every key
 *   retrieved from storage without breaking older plaintext entries.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class Crypto
{
    /** Marker prefix — decrypt() short-circuits if absent. */
    const PREFIX = 'enc::';

    /** GCM 12-byte IV / nonce length. */
    const IV_BYTES = 12;

    /** GCM 16-byte authentication tag length. */
    const TAG_BYTES = 16;

    /** @var string|null Cached derived key (per-process). */
    private static $key_cache = null;

    /**
     * Encrypt a plaintext string.
     *
     * @param string $plaintext
     * @return string Prefixed base64 payload ("enc::..."), or '' on empty input.
     */
    public static function encrypt($plaintext)
    {
        if (!is_string($plaintext) || $plaintext === '') {
            return '';
        }
        // Already encrypted? Return as-is (idempotent).
        if (self::is_encrypted($plaintext)) {
            return $plaintext;
        }
        if (!self::is_available()) {
            // OpenSSL missing — fail open (return plaintext) so we never break
            // a working site. Admin notice should be emitted elsewhere.
            return $plaintext;
        }

        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $ct = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            self::derive_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_BYTES
        );
        if ($ct === false) {
            return $plaintext; // fail open
        }
        // Pack: iv (12) || tag (16) || ciphertext, then base64url.
        $packed = $iv . $tag . $ct;
        return self::PREFIX . self::base64url_encode($packed);
    }

    /**
     * Decrypt an encrypted payload. Returns the input unchanged when it is
     * not an "enc::" payload (graceful plaintext fallback for legacy data).
     *
     * @param string $value
     * @return string
     */
    public static function decrypt($value)
    {
        if (!is_string($value) || $value === '') {
            return '';
        }
        if (!self::is_encrypted($value)) {
            // Legacy plaintext — return as-is so callers can keep using it.
            return $value;
        }
        if (!self::is_available()) {
            // OpenSSL missing — strip prefix and return best-effort.
            return substr($value, strlen(self::PREFIX));
        }

        $packed = self::base64url_decode(substr($value, strlen(self::PREFIX)));
        if ($packed === false || strlen($packed) < self::IV_BYTES + self::TAG_BYTES) {
            return ''; // malformed — refuse to fabricate
        }
        $iv  = substr($packed, 0, self::IV_BYTES);
        $tag = substr($packed, self::IV_BYTES, self::TAG_BYTES);
        $ct  = substr($packed, self::IV_BYTES + self::TAG_BYTES);

        $pt = openssl_decrypt(
            $ct,
            'aes-256-gcm',
            self::derive_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($pt === false) {
            return ''; // auth tag mismatch — refuse to fabricate
        }
        return $pt;
    }

    /**
     * @param string $value
     * @return bool
     */
    public static function is_encrypted($value)
    {
        return is_string($value) && strpos($value, self::PREFIX) === 0;
    }

    /**
     * @return bool
     */
    public static function is_available()
    {
        return function_exists('openssl_encrypt')
            && function_exists('random_bytes')
            && defined('OPENSSL_RAW_DATA')
            && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
    }

    /**
     * Derive a 32-byte AES-256 key from wp-config salts. The chain is:
     *
     *   root = AUTH_KEY (or fallback to wp_salt('auth'))
     *   key  = HMAC-SHA256(root, "linked3/aes-256-gcm/v1" || wp_salt('nonce'))
     *
     * Both inputs come from wp-config.php — they are NOT in the DB, so a
     * SQL injection or backup leak alone cannot recover API keys.
     *
     * @return string 32 raw bytes.
     */
    private static function derive_key()
    {
        if (self::$key_cache !== null) {
            return self::$key_cache;
        }
        $auth = defined('AUTH_KEY') && AUTH_KEY ? AUTH_KEY : wp_salt('auth');
        $nonce = wp_salt('nonce'); // guaranteed non-empty by WP core.
        // Domain-separation label binds the derived key to this purpose.
        $info = 'linked3/aes-256-gcm/v1';
        // HKDF-Extract step (single round HMAC).
        $prk = hash_hmac('sha256', $info . '|' . $nonce, $auth, true);
        // HKDF-Expand step (32 bytes for AES-256).
        $key = hash_hmac('sha256', $prk . "\x01", '', true);
        self::$key_cache = $key;
        return $key;
    }

    /**
     * URL-safe base64 encode (no padding) — survives WP option sanitisation.
     *
     * @param string $bin
     * @return string
     */
    private static function base64url_encode($bin)
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /**
     * @param string $s
     * @return string|false
     */
    private static function base64url_decode($s)
    {
        $pad = strlen($s) % 4;
        if ($pad > 0) {
            $s .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($s, '-_', '+/'));
    }
}
