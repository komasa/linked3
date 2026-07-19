<?php

declare(strict_types=1);
/**
 * Indexnow push engine — direct to api.indexnow.org (cross-engine fan-out).
 *
 * Unlike the Bing-specific engine, this one POSTs to the Indexnow root
 * endpoint which fans out to all participating engines (Bing, Yandex,
 * Naver, Seznam, …). Reuses the same site-verification key file as the
 * Bing engine.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

use Linked3\Includes\Http\SafeRemote;



if (!defined('ABSPATH')) {
    exit;
}
final class PushEngineIndexnow implements PushEngine
{
    public function slug()
    : string {
        return 'indexnow';
    }

    public function label() : mixed {
        return __('Indexnow(多引擎)', 'linked3');
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
        $key = wp_generate_password(32, false);
        update_option(LINKED3_OPTION_PREFIX . 'push_indexnow_key', $key);
        return $key;
    }

    public function push(array $urls)
    : array {
        $key = $this->key();
        $host = (string) wp_parse_url(site_url(), PHP_URL_HOST);
        $payload = [
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => trailingslashit(site_url()) . $key . '.txt',
            'urlList'     => array_values(array_map('esc_url_raw', $urls)),
        ];
        $response = SafeRemote::post('https://api.indexnow.org/IndexNow', [
            'timeout'       => 15,
            'headers'       => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'          => wp_json_encode($payload),
            'allowed_hosts' => ['api.indexnow.org', 'www.indexnow.org'],
        ]);
        if (is_wp_error($response)) {
            return [
                'ok' => false, 'code' => 0, 'body' => '',
                'message' => $response->get_error_message(),
                'pushed' => 0, 'raw' => null,
            ];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $ok = in_array($code, [200, 202], true);
        return [
            'ok'      => $ok,
            'code'    => $code,
            'body'    => $raw_body,
            'message' => $ok
                ? sprintf(__('Indexnow 已接受 %d 个 URL。', 'linked3'), count($urls))
                : __('Indexnow 拒绝了请求。', 'linked3'),
            'pushed'  => $ok ? count($urls) : 0,
            'raw'     => null,
        ];
    }
}
