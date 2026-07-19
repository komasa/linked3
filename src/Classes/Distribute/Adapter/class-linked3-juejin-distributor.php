<?php
/**
 * 掘金分发器 — 发布技术文章到掘金。
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
final class Linked3_Juejin_Distributor implements Linked3_Distribute_Adapter_Interface
{
    public function slug() : string { return 'juejin'; }
    public function label() : string { return '掘金'; }

    public function publish(array $post_data, array $config)
    : array {
        $token = $config['access_token'] ?? '';
        $category = $config['category_id'] ?? '6809637767543259144'; // 默认"前端"
        if (!$token) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Access Token', 'linked3-ai')];
        }

        $body = [
            'title' => mb_substr($post_data['title'] ?? '', 0, 100),
            'content' => $post_data['content'] ?? '',
            'category_id' => $category,
            'source_url' => $post_data['url'] ?? '',
        ];

        $resp = Linked3_Safe_Remote::post('https://api.juejin.cn/content_api/v1/article/publish', [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => ['api.juejin.cn'],
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400 || empty($json['data']['article_id'])) {
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['message'] ?? '')];
        }
        return ['ok' => true, 'remote_id' => $json['data']['article_id'], 'message' => __('已发布到掘金', 'linked3-ai')];
    }

    public function test(array $config)
    : array {
        $token = $config['access_token'] ?? '';
        if (!$token) return ['ok' => false, 'message' => __('缺少 Access Token', 'linked3-ai')];
        // v3.0.0: 真实 ping 掘金用户接口验证 token (注意: 掘金实际用 cookie 鉴权,Bearer 可能不通)
        $resp = Linked3_Safe_Remote::get('https://api.juejin.cn/user_api/v1/user/get', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'allowed_hosts' => ['api.juejin.cn'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 200 && !empty($body['data']['user_name'])) {
            return ['ok' => true, 'message' => sprintf('掘金已连接 (@%s)', $body['data']['user_name'])];
        }
        return ['ok' => false, 'message' => sprintf('HTTP %d: %s (注意:掘金可能需要 Cookie 鉴权)', $code, $body['message'] ?? '')];
    }
}
