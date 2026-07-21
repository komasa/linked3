<?php

declare(strict_types=1);
/**
 * Google Indexing API push engine (JWT auth).
 *
 * Mirrors v2.9.6 push_to_google (JWT signed with service-account private
 * key). Requires admin to upload a Google service-account JSON; we cache
 * the parsed key + email in the `linked3_push_google` option.
 *
 * Endpoint: https://indexing.googleapis.com/v3/urlNotifications:publish
 * Body:     { url, type: 'URL_UPDATED' }
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Crypto;



if (!defined('ABSPATH')) {
    exit;
}
final class PushEngineGoogleJWT implements PushEngine
{
    public function slug(): string
    {
        return 'google';
    }

    public function label() : string {
        return __('Google 索引 API', 'linked3');
    }

    public function is_configured() : bool {
        $cfg = $this->config();
        return !empty($cfg['client_email']) && !empty($cfg['private_key']);
    }

    /**
     * @return array{client_email:string,private_key:string}
     */
    private function config() : mixed {
        $defaults = (array) get_option(LINKED3_OPTION_PREFIX . 'push_google', []);
        $defaults = (array) apply_filters('linked3/push_google_config', $defaults);
        // Decrypt the service-account credentials at read-time (Crypto::decrypt
        // is a no-op on plaintext, so legacy options still work). The private_key
        // is the most sensitive credential in the plugin (full SA identity); the
        // client_email is also encrypted for defence-in-depth.
        if (!empty($defaults['client_email'])) {
            $defaults['client_email'] = Crypto::decrypt((string) $defaults['client_email']);
        }
        if (!empty($defaults['private_key'])) {
            $defaults['private_key'] = Crypto::decrypt((string) $defaults['private_key']);
        }
        return $defaults;
    }

    public function push(array $urls)
    : array {
        $cfg = $this->config();
        if (!$this->is_configured()) {
            return [
                'ok'      => false,
                'code'    => 0,
                'body'    => '',
                'message' => __('Google 索引 API 未配置(缺少服务账号凭证)。', 'linked3'),
                'pushed'  => 0,
                'raw'     => null,
            ];
        }
        $token = $this->fetch_access_token($cfg);
        if ($token === '') {
            return [
                'ok'      => false,
                'code'    => 0,
                'body'    => '',
                'message' => __('获取 Google OAuth2 访问令牌失败。', 'linked3'),
                'pushed'  => 0,
                'raw'     => null,
            ];
        }
        $ok_count = 0;
        $last_code = 0;
        $last_body = '';
        foreach ($urls as $url) {
            $url = esc_url_raw($url);
            if ($url === '') {
                continue;
            }
            $response = SafeRemote::post('https://indexing.googleapis.com/v3/urlNotifications:publish', [
                'timeout'       => 15,
                'headers'       => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'body'          => wp_json_encode(['url' => $url, 'type' => 'URL_UPDATED']),
                'allowed_hosts' => ['indexing.googleapis.com'],
            ]);
            if (is_wp_error($response)) {
                $last_body = $response->get_error_message();
                continue;
            }
            $last_code = (int) wp_remote_retrieve_response_code($response);
            $last_body = (string) wp_remote_retrieve_body($response);
            if ($last_code === 200) {
                $ok_count++;
            }
        }
        return [
            'ok'      => $ok_count > 0,
            'code'    => $last_code,
            'body'    => $last_body,
            'message' => sprintf(__('Google 索引 API:已推送 %d / %d 个 URL。', 'linked3'), $ok_count, count($urls)),
            'pushed'  => $ok_count,
            'raw'     => null,
        ];
    }

    /**
     * Build a JWT, exchange for an OAuth2 access token (15-min cache).
     *
     * @param array $cfg
     * @return string
     */
    private function fetch_access_token(array $cfg) : mixed     {
        $cache_key = LINKED3_OPTION_PREFIX . 'google_jwt_token';
        $cached = get_transient($cache_key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $jwt = $this->build_jwt($cfg);
        if ($jwt === '') {
            return '';
        }
        $response = SafeRemote::post('https://oauth2.googleapis.com/token', [
            'timeout'       => 15,
            'headers'       => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'          => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            'allowed_hosts' => ['oauth2.googleapis.com'],
        ]);
        if (is_wp_error($response)) {
            return '';
        }
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($json) || empty($json['access_token'])) {
            return '';
        }
        $ttl = max(60, (int) ($json['expires_in'] ?? 3600) - 60);
        set_transient($cache_key, (string) $json['access_token'], $ttl);
        return (string) $json['access_token'];
    }

    /**
     * Build an unsigned-then-RSA-signed JWT for the Google service account.
     *
     * @param array $cfg
     * @return string
     */
    private function build_jwt(array $cfg) : mixed {
        if (empty($cfg['client_email']) || empty($cfg['private_key'])) {
            return '';
        }
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim = [
            'iss'   => $cfg['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];
        $b64 = static function ($arr) {
            return rtrim(strtr(base64_encode(wp_json_encode($arr)), '+/', '-_'), '=');
        };
        $signing_input = $b64($header) . '.' . $b64($claim);
        $signature = '';
        // Handle PKCS#8 (modern) and PKCS#1 (legacy RSA key format).
        $ok = openssl_sign(
            $signing_input,
            $signature,
            (string) $cfg['private_key'],
            'sha256WithRSAEncryption'
        );
        if (!$ok) {
            return '';
        }
        return $signing_input . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
