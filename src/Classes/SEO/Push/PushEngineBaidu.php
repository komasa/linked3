<?php

declare(strict_types=1);
/**
 * Baidu push engine.
 *
 * Mirrors v2.9.6 push_to_baidu. Baidu accepts POST of newline-separated
 * URLs to https://data.zhanzhang.baidu.com/urls?site=…&token=…, returns
 * a JSON with `success` / `remain` / `not_same_site` / `not_valid` fields.
 *
 * The site/token pair is stored in the `linked3_push_baidu` option
 * (filterable via `linked3/push_baidu_config`). The token is the Baidu
 * webmaster "推送准入密钥" — admins set it from the SEO settings page.
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
final class PushEngineBaidu implements PushEngine
{
    public function slug(): string
    : string {
        return 'baidu';
    }

    public function label() : mixed {
        return __('百度站点推送', 'linked3');
    }

    public function is_configured() : mixed     {
        $cfg = $this->config();
        return !empty($cfg['site']) && !empty($cfg['token']);
    }

    /**
     * @return array{site:string,token:string}
     */
    private function config() : mixed {
        $defaults = (array) get_option(LINKED3_OPTION_PREFIX . 'push_baidu', []);
        $defaults = (array) apply_filters('linked3/push_baidu_config', $defaults);
        // Decrypt the token at read-time (Crypto::decrypt is a
        // no-op on plaintext, so legacy options still work after the
        // v0.5.0 hardening).
        if (!empty($defaults['token'])) {
            $defaults['token'] = Crypto::decrypt((string) $defaults['token']);
        }
        return $defaults;
    }

    public function push(array $urls) : mixed     {
        $cfg = $this->config();
        if (!$this->is_configured()) {
            return [
                'ok'      => false,
                'code'    => 0,
                'body'    => '',
                'message' => __('百度推送未配置(缺少站点/Token)。', 'linked3'),
                'pushed'  => 0,
                'raw'     => null,
            ];
        }
        $endpoint = sprintf(
            'https://data.zhanzhang.baidu.com/urls?site=%s&token=%s',
            rawurlencode($cfg['site']),
            rawurlencode($cfg['token'])
        );
        $body = implode("\n", array_map('esc_url_raw', $urls));

        $response = SafeRemote::post($endpoint, [
            'timeout'       => 20,
            'headers'       => ['Content-Type' => 'text/plain'],
            'body'          => $body,
            'allowed_hosts' => ['data.zhanzhang.baidu.com'],
        ]);

        return $this->parse($response, count($urls));
    }

    /**
     * @param array|\WP_Error $response
     * @param int             $sent
     * @return array
     */
    private function parse(array|WP_Error $response, int $sent)
    : array {
        if (is_wp_error($response)) {
            return [
                'ok'      => false,
                'code'    => 0,
                'body'    => '',
                'message' => $response->get_error_message(),
                'pushed'  => 0,
                'raw'     => null,
            ];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $json = json_decode($raw_body, true);
        $ok = $code === 200 && is_array($json) && !empty($json['success']);
        return [
            'ok'      => $ok,
            'code'    => $code,
            'body'    => $raw_body,
            'message' => $ok ? (string) sprintf(__('已推送 %d 个 URL 到百度。', 'linked3'), (int) ($json['success'] ?? 0)) : __('百度推送失败。', 'linked3'),
            'pushed'  => $ok ? (int) ($json['success'] ?? 0) : 0,
            'raw'     => is_array($json) ? $json : null,
        ];
    }
}
