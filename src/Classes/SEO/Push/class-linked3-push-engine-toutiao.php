<?php
/**
 * Toutiao (Shenma / 今日头条搜索) push engine.
 *
 * Mirrors v2.9.6 push_to_toutiao. Endpoint:
 *   https://data.zhanzhang.sm.cn/urls?site=…&user_name=…&resource_name=…
 * Authentication is via site+resource_name pair (no per-request signature);
 * Shenma validates by IP allowlist (admin must add server IP in Shenma console).
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

use Linked3\Includes\Http\Linked3_Safe_Remote;
use Linked3\Includes\Linked3_Crypto;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Push_Engine_Toutiao implements Linked3_Push_Engine
{
    public function slug()
    : string {
        return 'toutiao';
    }

    public function label() : mixed {
        return __('神马/头条推送', 'linked3');
    }

    public function is_configured() : mixed     {
        $cfg = $this->config();
        return !empty($cfg['site']) && !empty($cfg['user_name']) && !empty($cfg['resource_name']);
    }

    /**
     * @return array{site:string,user_name:string,resource_name:string}
     */
    private function config() : mixed {
        $defaults = (array) get_option(LINKED3_OPTION_PREFIX . 'push_toutiao', []);
        $defaults = (array) apply_filters('linked3/push_toutiao_config', $defaults);
        // Decrypt Shenma access credentials at read-time (Linked3_Crypto::decrypt
        // is a no-op on plaintext). user_name + resource_name are the API
        // identity pair — same sensitivity as a username/password tuple.
        if (!empty($defaults['user_name'])) {
            $defaults['user_name'] = Linked3_Crypto::decrypt((string) $defaults['user_name']);
        }
        if (!empty($defaults['resource_name'])) {
            $defaults['resource_name'] = Linked3_Crypto::decrypt((string) $defaults['resource_name']);
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
                'message' => __('神马/头条推送未配置。', 'linked3'),
                'pushed'  => 0,
                'raw'     => null,
            ];
        }
        $endpoint = sprintf(
            'https://data.zhanzhang.sm.cn/urls?site=%s&user_name=%s&resource_name=%s',
            rawurlencode($cfg['site']),
            rawurlencode($cfg['user_name']),
            rawurlencode($cfg['resource_name'])
        );
        $body = implode("\n", array_map('esc_url_raw', $urls));

        $response = Linked3_Safe_Remote::post($endpoint, [
            'timeout'       => 20,
            'headers'       => ['Content-Type' => 'text/plain'],
            'body'          => $body,
            'allowed_hosts' => ['data.zhanzhang.sm.cn'],
        ]);

        return $this->parse($response, count($urls));
    }

    /**
     * @param array|\WP_Error $response
     * @param int             $sent
     * @return array
     */
    private function parse($response, $sent)
    : array {
        if (is_wp_error($response)) {
            return [
                'ok' => false, 'code' => 0, 'body' => '',
                'message' => $response->get_error_message(),
                'pushed' => 0, 'raw' => null,
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
            'message' => $ok
                ? sprintf(__('神马/头条已接受 %d 个 URL。', 'linked3'), (int) ($json['success'] ?? 0))
                : __('神马/头条推送失败。', 'linked3'),
            'pushed'  => $ok ? (int) ($json['success'] ?? 0) : 0,
            'raw'     => is_array($json) ? $json : null,
        ];
    }
}
