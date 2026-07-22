<?php

declare(strict_types=1);
/**
 * 微博分发器 — 发布图文/文章。
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
final class WeiboDistributor implements DistributeAdapterInterface
{
    public function slug() : string { return 'weibo'; }
    public function label() : string { return '微博'; }

    public function publish(array $post_data, array $config): array {
        $token = $config['access_token'] ?? '';
        if (!$token) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Access Token', 'linked3-ai')];
        }

        $content = $post_data['title'] ?? '';
        $url = $post_data['url'] ?? '';
        $text = mb_substr($content . ' ' . $url, 0, 140);

        $resp = SafeRemote::post('https://api.weibo.com/2/statuses/share.json', [
            'timeout' => 30,
            'body' => [
                'access_token' => $token,
                'status' => $text,
            ],
            'allowed_hosts' => ['api.weibo.com'],
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) {
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['error'] ?? '')];
        }
        return ['ok' => true, 'remote_id' => (string) ($json['id'] ?? ''), 'message' => __('已发布到微博', 'linked3-ai')];
    }

    public function test(array $config): array {
        $token = $config['access_token'] ?? '';
        if (!$token) return ['ok' => false, 'message' => __('缺少 Access Token', 'linked3-ai')];
        // v3.0.0: 真实 ping 微博 API 验证 token
        $resp = SafeRemote::get('https://api.weibo.com/2/account/get_uid.json?access_token=' . urlencode($token), [
            'timeout' => 15,
            'allowed_hosts' => ['api.weibo.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $uid = $body['uid'] ?? '';
            return ['ok' => true, 'message' => sprintf('微博已连接 (UID: %s)', $uid)];
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return ['ok' => false, 'message' => sprintf('HTTP %d: %s', $code, $body['error'] ?? '')];
    }
}
