<?php

declare(strict_types=1);
/**
 * LicenseRemoteClient — remote license server communication.
 *
 * Extracted from LicenseService to isolate HTTP concerns and eliminate
 * the code duplication between verify_remote() and activate_remote().
 *
 * Behavior is identical to the original LicenseService implementation:
 *   - SafeRemote::post() with allowed_hosts restriction
 *   - HMAC-SHA256 request signing (delegated to LicenseSigner)
 *   - Response signature verification (delegated to LicenseSigner)
 *   - 403 "site not bound" → transparent fallback to /activate
 *   - Transport errors return transport_error=true (non-fatal)
 *
 * @package Linked3
 * @subpackage Classes\License
 */

namespace Linked3\Classes\License;

use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class LicenseRemoteClient
{
    /** @var LicenseSigner */
    private $signer;

    /** @var Logger */
    private $log;

    public function __construct(LicenseSigner $signer, Logger $log)
    {
        $this->signer = $signer;
        $this->log = $log;
    }

    /**
     * Verify a license key against the remote server.
     *
     * On 403 "site not bound", transparently falls back to /activate
     * (idempotent on the server side).
     *
     * @param string $key         License key.
     * @param string $server_url  License server base URL (empty = local mode).
     * @param string $fingerprint Site fingerprint.
     * @return array{valid:bool, plan:string, message:string, transport_error?:bool}
     *         Returns ['plan'=>'free','status'=>'local'] in local mode (no 'valid' key).
     */
    public function verify($key, $server_url, $fingerprint)
    {
        // Local mode: no remote server configured.
        if ($server_url === '') {
            return ['plan' => 'free', 'status' => 'local'];
        }

        $url = rtrim($server_url, '/') . '/api/license/verify';
        $ts = time();
        $nonce = wp_generate_password(16, false);
        $signature = $this->signer->sign($key, $ts, $nonce);

        $body = [
            'license_key'      => $key,
            'site_url'         => site_url(),
            'site_fingerprint' => $fingerprint,
            'timestamp'        => $ts,
            'nonce'            => $nonce,
            'signature'        => $signature,
        ];

        $response = SafeRemote::post($url, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);

        if (is_wp_error($response)) {
            $this->log->error('license', 'License verify transport error', ['error' => $response->get_error_message()]);
            return ['valid' => false, 'plan' => 'free', 'message' => 'license server unreachable', 'transport_error' => true];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);

        // If the site is not yet bound to this license, transparently fall
        // back to /activate (idempotent on the server side). This closes the
        // v0.2.x gap where store_license_key() never called /activate,
        // causing verify to fail with "site not bound" on first use.
        if ($code === 403 && is_array($json) && stripos((string) ($json['message'] ?? ''), 'site not bound') !== false) {
            $this->log->info('license', 'Site not bound — retrying via /activate');
            return $this->activate($key, $server_url, $fingerprint);
        }

        if ($code !== 200 || !is_array($json) || empty($json['valid'])) {
            $this->log->warning('license', 'License verify rejected', ['code' => $code]);
            return ['valid' => false, 'plan' => 'free', 'message' => $json['message'] ?? 'invalid'];
        }

        // Verify the server's response signature (mutual auth).
        if (!empty($json['signature']) && !$this->signer->verify_server_signature($json, $key)) {
            $this->log->error('license', 'License server response signature mismatch');
            return ['valid' => false, 'plan' => 'free', 'message' => 'signature mismatch'];
        }

        return ['valid' => true, 'plan' => $json['plan'] ?? 'free', 'message' => 'ok'];
    }

    /**
     * Activate (bind) this site against the license server.
     *
     * Called from LicenseService::store_license_key() on key save, and
     * from verify() as a fallback when the server reports "site not bound".
     * The server's /activate endpoint is idempotent — re-activating an
     * already-bound site is a no-op.
     *
     * @param string $key         License key.
     * @param string $server_url  License server base URL (empty = local mode).
     * @param string $fingerprint Site fingerprint.
     * @return array{valid:bool, plan:string, message:string, transport_error?:bool}
     */
    public function activate($key, $server_url, $fingerprint)
    {
        // Local mode: no remote server configured.
        if ($server_url === '') {
            return ['valid' => true, 'plan' => 'free', 'message' => __('本地模式:无需远程激活', 'linked3')];
        }

        $url = rtrim($server_url, '/') . '/api/license/activate';
        $ts = time();
        $nonce = wp_generate_password(16, false);
        $signature = $this->signer->sign($key, $ts, $nonce);

        $body = [
            'license_key'      => $key,
            'site_url'         => site_url(),
            'site_fingerprint' => $fingerprint,
            'timestamp'        => $ts,
            'nonce'            => $nonce,
            'signature'        => $signature,
        ];

        $response = SafeRemote::post($url, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode($body),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);

        if (is_wp_error($response)) {
            $this->log->error('license', 'License activate transport error', ['error' => $response->get_error_message()]);
            return ['valid' => false, 'plan' => 'free', 'message' => 'license server unreachable', 'transport_error' => true];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($json) || empty($json['valid'])) {
            $this->log->warning('license', 'License activate rejected', ['code' => $code, 'message' => $json['message'] ?? '']);
            return ['valid' => false, 'plan' => 'free', 'message' => $json['message'] ?? 'invalid'];
        }

        if (!empty($json['signature']) && !$this->signer->verify_server_signature($json, $key)) {
            $this->log->error('license', 'License server activate response signature mismatch');
            return ['valid' => false, 'plan' => 'free', 'message' => 'signature mismatch'];
        }

        return ['valid' => true, 'plan' => $json['plan'] ?? 'free', 'message' => 'ok'];
    }
}
