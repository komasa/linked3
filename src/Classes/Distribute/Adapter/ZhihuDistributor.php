<?php

declare(strict_types=1);
/**
 * v3.2.0: 知乎分发器 — MCP 中转模式
 *
 * ⚠️ 重要说明:
 *   知乎于 2019 年关闭了官方 OpenAPI,无官方发布接口。
 *   本适配器采用 MCP (Model Context Protocol) 中转模式:
 *   1. 用户自备 MCP 中转服务 (如部署开源 zhihu-mcp 项目)
 *   2. 配置 MCP API URL + 知乎 Cookie 或 MCP Token
 *   3. 插件只负责 HTTP 转发,不对中转服务稳定性负责
 *
 * 配置字段:
 *   - api_url: MCP 中转 API 地址 (如 https://mcp.example.com/zhihu/publish)
 *   - access_token: 知乎 Cookie z_c0 或 MCP Token
 *   - column_id: 专栏 ID (可选,留空发到默认专栏)
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
final class ZhihuDistributor implements DistributeAdapterInterface
{
    public function slug() : string { return 'zhihu'; }
    public function label() : string { return '知乎 (MCP 中转)'; }

    public function publish(array $post_data, array $config)
    : array {
        $api_url = $config['api_url'] ?? '';
        $token = $config['access_token'] ?? '';
        $column_id = $config['column_id'] ?? '';

        if (!$api_url || !$token) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 MCP API 地址或 Access Token (需自备知乎 MCP 中转服务)', 'linked3-ai')];
        }

        $body = [
            'title'      => mb_substr($post_data['title'] ?? '', 0, 100),
            'content'    => $post_data['content'] ?? '',
            'source_url' => $post_data['url'] ?? '',
        ];
        if ($column_id) $body['column_id'] = $column_id;

        $resp = SafeRemote::post($api_url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code >= 400) {
            $msg = $json['error'] ?? ($json['message'] ?? "HTTP {$code}");
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf('知乎 MCP 推送失败: %s', $msg)];
        }

        $remote_id = (string) ($json['id'] ?? ($json['article_id'] ?? ''));
        return ['ok' => true, 'remote_id' => $remote_id, 'message' => __('已通过 MCP 推送到知乎', 'linked3-ai')];
    }

    public function test(array $config)
    : array {
        $api_url = $config['api_url'] ?? '';
        $token = $config['access_token'] ?? '';
        if (!$api_url || !$token) {
            return ['ok' => false, 'message' => __('缺少 MCP API 地址或 Access Token', 'linked3-ai')];
        }
        // 验证: GET {api_url}/me (MCP 服务通常提供 /me 端点)
        $me_url = rtrim($api_url, '/') . '/me';
        $resp = SafeRemote::get($me_url, [
            'timeout' => 10,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code === 200) {
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $name = $body['name'] ?? ($body['username'] ?? '');
            return ['ok' => true, 'message' => sprintf('知乎 MCP 已连接 (%s)', $name)];
        }
        return ['ok' => false, 'message' => sprintf('HTTP %d — MCP 服务不可用或 Token 无效', $code)];
    }
}
