<?php
/**
 * CSDN 分发器 — 发布技术博客到 CSDN。
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
final class Linked3_CSDN_Distributor implements Linked3_Distribute_Adapter_Interface
{
    public function slug() : string { return 'csdn'; }
    public function label() : string { return 'CSDN'; }

    public function publish(array $post_data, array $config)
    : array {
        $token = $config['access_token'] ?? '';
        if (!$token) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Access Token (Cookie)', 'linked3-ai')];
        }

        $body = [
            'title' => mb_substr($post_data['title'] ?? '', 0, 100),
            'content' => $post_data['content'] ?? '',
            'source_url' => $post_data['url'] ?? '',
        ];

        $resp = Linked3_Safe_Remote::post('https://bizapi.csdn.net/blog-console-api/v1/blog/save', [
            'timeout' => 30,
            'headers' => ['Cookie' => $token, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => ['bizapi.csdn.net'],
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400 || empty($json['data']['blogId'])) {
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['msg'] ?? '')];
        }
        return ['ok' => true, 'remote_id' => $json['data']['blogId'], 'message' => __('已发布到 CSDN', 'linked3-ai')];
    }

    public function test(array $config)
    : array {
        $token = $config['access_token'] ?? '';
        if (!$token) return ['ok' => false, 'message' => __('缺少 Cookie', 'linked3-ai')];
        // v3.0.0: 真实 ping CSDN 用户接口验证 Cookie
        $resp = Linked3_Safe_Remote::get('https://bizapi.csdn.net/user-api/v1/user/info', [
            'timeout' => 15,
            'headers' => ['Cookie' => $token],
            'allowed_hosts' => ['bizapi.csdn.net'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 200 && !empty($body['data']['username'])) {
            return ['ok' => true, 'message' => sprintf('CSDN 已连接 (@%s)', $body['data']['username'])];
        }
        return ['ok' => false, 'message' => sprintf('HTTP %d: %s (Cookie 可能已过期)', $code, $body['msg'] ?? '')];
    }
}
