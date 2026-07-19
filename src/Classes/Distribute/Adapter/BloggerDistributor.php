<?php

declare(strict_types=1);
/**
 * Blogger 分发器 — 发布到 Google Blogger。
 *
 * @package Linked3
 * @subpackage Classes\Distribute\Adapter
 */

namespace Linked3\Classes\Distribute\Adapter;

use Linked3\Classes\Distribute\DistributeAdapterInterface;
use Linked3\Includes\Http\Linked3_Safe_Remote;



if (!defined('ABSPATH')) {
    exit;
}
final class BloggerDistributor implements DistributeAdapterInterface
{
    public function slug() : string { return 'blogger'; }
    public function label() : string { return 'Blogger (Google)'; }

    public function publish(array $post_data, array $config)
    : array {
        $token = $config['access_token'] ?? '';
        $blog_id = $config['blog_id'] ?? '';
        if (!$token || !$blog_id) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Access Token 或 Blog ID', 'linked3-ai')];
        }
        $body = [
            'kind' => 'blogger#post',
            'blog' => ['id' => $blog_id],
            'title' => $post_data['title'] ?? '',
            'content' => $post_data['content'] ?? '',
        ];
        $resp = Linked3_Safe_Remote::post("https://www.googleapis.com/blogger/v3/blogs/{$blog_id}/posts", [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => ['www.googleapis.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) return ['ok' => false, 'remote_id' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['error']['message'] ?? '')];
        return ['ok' => true, 'remote_id' => (string) ($json['id'] ?? ''), 'message' => __('已发布到 Blogger', 'linked3-ai')];
    }

    public function test(array $config)
    : array {
        $token = $config['access_token'] ?? '';
        $blog_id = $config['blog_id'] ?? '';
        if (!$token || !$blog_id) return ['ok' => false, 'message' => __('缺少 Access Token 或 Blog ID', 'linked3-ai')];
        // v3.0.0: 真实 ping Blogger API 验证 token + blog_id
        $resp = Linked3_Safe_Remote::get('https://www.googleapis.com/blogger/v3/blogs/' . urlencode($blog_id), [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'allowed_hosts' => ['www.googleapis.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 200 && !empty($body['name'])) {
            return ['ok' => true, 'message' => sprintf('Blogger 已连接 (Blog: %s)', $body['name'])];
        }
        return ['ok' => false, 'message' => sprintf('HTTP %d: %s', $code, $body['error']['message'] ?? '')];
    }
}
