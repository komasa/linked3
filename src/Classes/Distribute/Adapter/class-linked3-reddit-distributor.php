<?php
/**
 * Reddit 分发器 — 发布到 Reddit。
 *
 * @package Linked3
 * @subpackage Classes\Distribute\Adapter
 */

namespace Linked3\Classes\Distribute\Adapter;

use Linked3\Classes\Distribute\Linked3_Distribute_Adapter_Interface;
use Linked3\Includes\Http\Linked3_Safe_Remote;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Reddit_Distributor implements Linked3_Distribute_Adapter_Interface
{
    public function slug() : string { return 'reddit'; }
    public function label() : string { return 'Reddit'; }

    public function publish(array $post_data, array $config)
    : array {
        $token = $config['access_token'] ?? '';
        $subreddit = $config['subreddit'] ?? '';
        if (!$token || !$subreddit) return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Access Token 或 Subreddit', 'linked3-ai')];
        $body = [
            'kind' => 'self',
            'sr' => $subreddit,
            'title' => $post_data['title'] ?? '',
            'text' => $post_data['content'] ?? '',
            'url' => $post_data['url'] ?? '',
        ];
        $resp = Linked3_Safe_Remote::post('https://oauth.reddit.com/api/submit', [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'User-Agent' => 'Linked3/1.0', 'Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query($body),
            'allowed_hosts' => ['oauth.reddit.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400 || empty($json['json']['data']['id'])) return ['ok' => false, 'remote_id' => '', 'message' => sprintf('HTTP %d', $code)];
        return ['ok' => true, 'remote_id' => $json['json']['data']['id'], 'message' => __('已发布到 Reddit', 'linked3-ai')];
    }

    public function test(array $config)
    : array {
        $token = $config['access_token'] ?? '';
        if (!$token) return ['ok' => false, 'message' => __('缺少 Access Token', 'linked3-ai')];
        // v3.0.0: 真实 ping Reddit API 验证 token
        $resp = Linked3_Safe_Remote::get('https://oauth.reddit.com/api/v1/me', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'User-Agent' => 'Linked3/1.0'],
            'allowed_hosts' => ['oauth.reddit.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 200 && !empty($body['name'])) {
            return ['ok' => true, 'message' => sprintf('Reddit 已连接 (u/%s)', $body['name'])];
        }
        return ['ok' => false, 'message' => sprintf('HTTP %d: %s', $code, $body['message'] ?? 'token 可能已过期')];
    }
}
