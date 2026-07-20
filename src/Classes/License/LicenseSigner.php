<?php

declare(strict_types=1);
/**
 * LicenseSigner — HMAC-SHA256 cryptographic signing for license requests.
 *
 * Extracted from LicenseService to isolate pure cryptographic logic from
 * WordPress-dependent business logic. The algorithm, key material, and
 * input/output contract are identical to the original LicenseService
 * implementation — this is a code-location refactor only.
 *
 * Security invariants (MUST NOT change):
 *   - Algorithm: HMAC-SHA256
 *   - Sign payload: `key|ts|nonce`
 *   - Verify payload: `plan|expires_at|timestamp`
 *   - Secret: master_secret (filterable) + '|' + license_key
 *
 * @package Linked3
 * @subpackage Classes\License
 */

namespace Linked3\Classes\License;

if (!defined('ABSPATH')) {
    exit;
}

final class LicenseSigner
{
    /** @var string Master secret from apply_filters('linked3/license_master_secret'). */
    private $master_secret;

    public function __construct()
    {
        $this->master_secret = (string) apply_filters(
            'linked3/license_master_secret',
            'linked3-default-master-secret-change-me'
        );
    }

    /**
     * HMAC-SHA256 signature for an outbound license request.
     *
     * @param string $key   License key (bound to the signature).
     * @param int    $ts    Unix timestamp.
     * @param string $nonce Random nonce.
     * @return string Hex HMAC.
     */
    public function sign($key, $ts, $nonce)
    {
        $secret = $this->signing_secret($key);
        $payload = $key . '|' . $ts . '|' . $nonce;
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify the license server's response signature (mutual auth).
     *
     * @param array  $response Server response with plan/expires_at/timestamp/signature.
     * @param string $key      License key (bound to the secret).
     * @return bool True if signature matches.
     */
    public function verify_server_signature($response, $key)
    {
        $secret = $this->signing_secret($key);
        $expected_fields = ['plan', 'expires_at', 'timestamp'];
        $payload_parts = [];
        foreach ($expected_fields as $f) {
            $payload_parts[] = (string) ($response[$f] ?? '');
        }
        $payload = implode('|', $payload_parts);
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, (string) ($response['signature'] ?? ''));
    }

    /**
     * Build the signing secret: master_secret + '|' + license_key.
     *
     * The master secret is captured at construction time (from filter).
     * The license key is passed as a parameter so the secret is always
     * bound to the specific key being verified.
     *
     * @param string $key License key.
     * @return string Signing secret.
     */
    private function signing_secret($key)
    {
        return $this->master_secret . '|' . $key;
    }
}
