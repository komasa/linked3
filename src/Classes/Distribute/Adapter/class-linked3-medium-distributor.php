<?php
/**
 * Medium 分发器 — 发布到 Medium。
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
final class Linked3_Medium_Distributor implements Linked3_Distribute_Adapter_Interface
{
    public function slug() : string { return 'medium'; }
    public function label() : string { return 'Medium'; }

    public function publish(array $post_data, array $config)
    : array {
        $token = $config['access_token'] ?? '';
        if (!$token) return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Access Token', 'linked3-ai')];
        $body = [
            'title' => $post_data['title'] ?? '',
            'contentFormat' => 'html',
            'content' => $post_data['content'] ?? '',
            'tags' => [],
        ];
        $resp = Linked3_Safe_Remote::post('https://api.medium.com/v1/posts', [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => ['api.medium.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) return ['ok' => false, 'remote_id' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['errors'][0]['message'] ?? '')];
        return ['ok' => true, 'remote_id' => (string) ($json['data']['id'] ?? ''), 'message' => __('已发布到 Medium', 'linked3-ai')];
    }

    public function test(array $config)
    : array {
        $token = $config['access_token'] ?? '';
        if (!$token) return ['ok' => false, 'message' => __('缺少 Integration Token', 'linked3-ai')];
        // v3.0.0: 真实 ping Medium API 验证 token
        $resp = Linked3_Safe_Remote::get('https://api.medium.com/v1/me', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
            'allowed_hosts' => ['api.medium.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 200 && !empty($body['data']['username'])) {
            return ['ok' => true, 'message' => sprintf('Medium 已连接 (@%s)', $body['data']['username'])];
        }
        $err = $body['errors'][0]['message'] ?? '';
        return ['ok' => false, 'message' => sprintf('HTTP %d: %s', $code, $err)];
    }
}
