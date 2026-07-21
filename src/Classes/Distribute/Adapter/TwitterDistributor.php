<?php

declare(strict_types=1);
/**
 * Twitter/X distributor — v3.0.0 重写为 OAuth 1.0a HMAC-SHA1 签名
 *
 * 旧版用 Bearer Token 发 POST /2/tweets 永远 401 (Bearer 只能读不能写)。
 * Twitter API v2 写操作必须用 OAuth 1.0a User Context:
 *   - consumer_key + consumer_secret (App credentials)
 *   - access_token + access_token_secret (User credentials, 通过 OAuth 1.0a 流程获取)
 *
 * @package Linked3
 * @subpackage Classes\Distribute\Adapter
 */

namespace Linked3\Classes\Distribute\Adapter;

use Linked3\Classes\Distribute\DistributeAdapterInterface;
use Linked3\Includes\Http\SafeRemote;



if (!defined('ABSPATH')) {
    exit;
}
final class TwitterDistributor implements DistributeAdapterInterface
{
    public function slug() : string { return 'twitter'; }
    public function label() : mixed { return __('Twitter / X', 'linked3'); }

    /**
     * 所需配置字段 (v3.0.0):
     *   - consumer_key:        App 的 API Key
     *   - consumer_secret:     App 的 API Key Secret
     *   - access_token:        用户的 Access Token
     *   - access_token_secret: 用户的 Access Token Secret
     */
    public function publish(array $post_data, array $config)
    : array {
        $ck = $config['consumer_key'] ?? '';
        $cs = $config['consumer_secret'] ?? '';
        $at = $config['access_token'] ?? '';
        $ats = $config['access_token_secret'] ?? '';
        if (!$ck || !$cs || !$at || !$ats) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 OAuth 1.0a 凭证(需 consumer_key/secret + access_token/secret)。', 'linked3')];
        }

        // 组合推文 (max 280 chars; 含 URL)
        $title = $post_data['title'] ?? '';
        $url = $post_data['url'] ?? '';
        $text = $title;
        $max_text = 280 - strlen($url) - 1;
        if (mb_strlen($text) > $max_text) {
            $text = mb_substr($text, 0, $max_text - 1) . '…';
        }
        $text .= ' ' . $url;

        // OAuth 1.0a 签名
        $auth_header = $this->build_oauth1_header(
            'POST',
            'https://api.twitter.com/2/tweets',
            ['text' => $text],
            $ck, $cs, $at, $ats
        );

        $resp = SafeRemote::post('https://api.twitter.com/2/tweets', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['text' => $text]),
            'allowed_hosts' => ['api.twitter.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) {
            $msg = $body['detail'] ?? ($body['title'] ?? "HTTP {$code}");
            if (is_array($msg)) $msg = wp_json_encode($msg);
            return ['ok' => false, 'remote_id' => '', 'message' => $msg];
        }
        return ['ok' => true, 'remote_id' => (string) ($body['data']['id'] ?? ''), 'message' => 'ok'];
    }

    public function test(array $config)
    : array {
        $ck = $config['consumer_key'] ?? '';
        $cs = $config['consumer_secret'] ?? '';
        $at = $config['access_token'] ?? '';
        $ats = $config['access_token_secret'] ?? '';
        if (!$ck || !$cs || !$at || !$ats) {
            return ['ok' => false, 'message' => __('缺少 OAuth 1.0a 凭证。', 'linked3')];
        }

        // 用 OAuth 1.0a 调 GET /2/users/me 验证凭证
        $auth_header = $this->build_oauth1_header(
            'GET',
            'https://api.twitter.com/2/users/me',
            [],
            $ck, $cs, $at, $ats
        );

        $resp = SafeRemote::get('https://api.twitter.com/2/users/me', [
            'timeout' => 15,
            'headers' => ['Authorization' => $auth_header],
            'allowed_hosts' => ['api.twitter.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $username = $body['data']['username'] ?? '';
            return ['ok' => true, 'message' => sprintf(__('Twitter 已连接 (@%s)。', 'linked3'), $username)];
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $msg = $body['detail'] ?? ($body['title'] ?? "HTTP {$code}");
        return ['ok' => false, 'message' => $msg];
    }

    /**
     * 构建 OAuth 1.0a Authorization header (HMAC-SHA1 签名)。
     *
     * @param string $http_method GET/POST
     * @param string $url 不含 query string 的完整 URL
     * @param array  $body_or_query POST body (关联数组) 或 GET query 参数
     * @param string $consumer_key
     * @param string $consumer_secret
     * @param string $access_token
     * @param string $access_token_secret
     * @return string "OAuth oauth_..." 头
     */
    private function build_oauth1_header(string $http_method, string $url, array $body_or_query, $ck, $cs, $at, $ats)
    : string {
        // 收集所有参数 (body/query 参数 + oauth_* 参数) 用于签名
        $oauth_params = [
            'oauth_consumer_key'     => $ck,
            'oauth_nonce'            => $this->generate_nonce(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => (string) time(),
            'oauth_token'            => $at,
            'oauth_version'          => '1.0',
        ];

        // 合并业务参数 (POST body 的 JSON 字段也算 query 参数参与签名)
        $all_params = array_merge($oauth_params, is_array($body_or_query) ? $body_or_query : []);

        // 按 key 字典序排序 + RFC3986 编码
        uksort($all_params, 'strcmp');
        $param_pairs = [];
        foreach ($all_params as $k => $v) {
            $param_pairs[] = rawurlencode($k) . '=' . rawurlencode((string) $v);
        }
        $param_string = implode('&', $param_pairs);

        // 构造 signature base string
        $base_string = strtoupper($http_method) . '&' . rawurlencode($url) . '&' . rawurlencode($param_string);

        // 签名密钥
        $signing_key = rawurlencode($cs) . '&' . rawurlencode($ats);

        // HMAC-SHA1 签名
        $signature = base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));

        // 把签名加入 oauth_params
        $oauth_params['oauth_signature'] = $signature;

        // 构造 Authorization header (仅 oauth_* 参数,按字典序)
        uksort($oauth_params, 'strcmp');
        $header_pairs = [];
        foreach ($oauth_params as $k => $v) {
            $header_pairs[] = rawurlencode($k) . '="' . rawurlencode((string) $v) . '"';
        }
        return 'OAuth ' . implode(', ', $header_pairs);
    }

    /**
     * 生成 OAuth nonce (32 字符随机串)。
     */
    private function generate_nonce() : mixed {
        return md5(uniqid(mt_rand(), true) . microtime());
    }
}
