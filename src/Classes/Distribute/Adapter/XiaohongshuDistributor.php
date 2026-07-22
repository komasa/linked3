<?php

declare(strict_types=1);
/**
 * 小红书分发器 — 通过小红书创作者 API 或 MCP 发布。
 *
 * 小红书支持 MCP (Model Context Protocol),可通过第三方中转 API 发布。
 * 用户需配置小红书创作者平台的 cookie 或 access_token。
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
final class XiaohongshuDistributor implements DistributeAdapterInterface
{
    public function slug() : string { return 'xiaohongshu'; }
    public function label() : string { return '小红书'; }

    public function publish(array $post_data, array $config) {
        $api_url = $config['api_url'] ?? '';
        $token = $config['access_token'] ?? '';
        if (!$api_url || !$token) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 API 地址或 Access Token', 'linked3-ai')];
        }

        $title = $post_data['title'] ?? '';
        $content = $post_data['content'] ?? '';
        $image = $post_data['image_url'] ?? '';

        // 小红书笔记格式:标题 + 正文 + 图片(必填)
        $body = [
            'title' => mb_substr($title, 0, 20),
            'content' => mb_substr($content, 0, 1000),
            'image_url' => $image,
            'type' => 'normal',
        ];

        $resp = SafeRemote::post($api_url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'allowed_hosts' => [wp_parse_url($api_url, PHP_URL_HOST)],
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) {
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['message'] ?? '')];
        }
        return ['ok' => true, 'remote_id' => (string) ($json['note_id'] ?? ''), 'message' => __('已发布到小红书', 'linked3-ai')];
    }

    public function test(array $config) {
        $api_url = $config['api_url'] ?? '';
        $token = $config['access_token'] ?? '';
        if (!$api_url || !$token) {
            return ['ok' => false, 'message' => __('缺少 API 地址或 Access Token', 'linked3-ai')];
        }
        return ['ok' => true, 'message' => __('配置已保存(实际连通性在发布时验证)', 'linked3-ai')];
    }
}
