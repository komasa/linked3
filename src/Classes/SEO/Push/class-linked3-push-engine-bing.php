<?php
/**
 * Bing push engine — Indexnow protocol.
 *
 * Bing accepts Indexnow POSTs to https://www.bing.com/indexnow with a
 * JSON body { host, key, keyLocation, urlList }. The `key` is a 16+ char
 * string the site publishes at /{key}.txt so Bing can verify ownership.
 *
 * Mirrors v2.9.6 push_to_bing (which used raw cURL + SSL off; this
 * version routes through Linked3_Safe_Remote with SSL verify ON).
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

use Linked3\Includes\Http\Linked3_Safe_Remote;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Push_Engine_Bing implements Linked3_Push_Engine
{
    public function slug()
    : string {
        return 'bing';
    }

    public function label() : mixed {
        return __('Bing Indexnow', 'linked3');
    }

    public function is_configured() : mixed     {
        return $this->key() !== '';
    }

    /**
     * @return string
     */
    private function key() : mixed {
        $stored = (string) get_option(LINKED3_OPTION_PREFIX . 'push_indexnow_key', '');
        if ($stored !== '') {
            return $stored;
        }
        // Auto-generate on first use.
        $key = wp_generate_password(32, false);
        update_option(LINKED3_OPTION_PREFIX . 'push_indexnow_key', $key);
        return $key;
    }

    public function push(array $urls) : mixed     {
        $key = $this->key();
        $host = (string) wp_parse_url(site_url(), PHP_URL_HOST);
        $key_location = trailingslashit(site_url()) . $key . '.txt';

        // Ensure the key file is exposed via the linked3_indexnow_key filter
        // (see Hook_Manager / SEO_Hooks_Registrar — the key file is served
        // by WordPress on demand).
        $payload = [
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => $key_location,
            'urlList'     => array_values(array_map('esc_url_raw', $urls)),
        ];

        $response = Linked3_Safe_Remote::post('https://www.bing.com/indexnow', [
            'timeout'       => 15,
            'headers'       => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'          => wp_json_encode($payload),
            'allowed_hosts' => ['www.bing.com'],
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
        // Indexnow: 200 = ok, 202 = accepted (async), 422 = invalid.
        $ok = in_array($code, [200, 202], true);
        return [
            'ok'      => $ok,
            'code'    => $code,
            'body'    => $raw_body,
            'message' => $ok
                ? sprintf(__('Bing Indexnow 已接受 %d 个 URL。', 'linked3'), $sent)
                : __('Bing Indexnow 拒绝了请求。', 'linked3'),
            'pushed'  => $ok ? $sent : 0,
            'raw'     => null,
        ];
    }
}
