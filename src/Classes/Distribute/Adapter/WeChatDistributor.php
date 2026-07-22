<?php

declare(strict_types=1);
/**
 * WeChat Official Account distributor — drafts via 公众号 API.
 *
 * Note: WeChat API requires access_token which expires every 2h and must be
 * refreshed via the client_credential grant. We cache the token in a transient.
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
final class WeChatDistributor implements DistributeAdapterInterface
{
    public function slug() : string { return 'wechat'; }
    public function label() : string { return __('微信公众号', 'linked3'); }

    public function publish(array $post_data, array $config) {
        $app_id = $config['app_id'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        if (!$app_id || !$app_secret) return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 app_id/app_secret。', 'linked3')];

        $token = $this->get_access_token($app_id, $app_secret);
        if (is_wp_error($token)) return ['ok' => false, 'remote_id' => '', 'message' => $token->get_error_message()];

        // 1) Upload thumb media (use post featured image or default).
        $thumb_media_id = $config['default_thumb_media_id'] ?? '';
        // 2) Create draft.
        $article = [
            'title' => $post_data['title'] ?? '',
            'content' => $post_data['content'] ?? '',
            'thumb_media_id' => $thumb_media_id,
            'content_source_url' => $post_data['url'] ?? '',
            'digest' => mb_substr($post_data['excerpt'] ?? '', 0, 120),
        ];
        $resp = SafeRemote::post("https://api.weixin.qq.com/cgi-bin/draft/add?access_token={$token}", [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['articles' => [$article]]),
            'allowed_hosts' => ['api.weixin.qq.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (isset($body['errcode']) && $body['errcode'] !== 0) {
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf('WeChat %d: %s', $body['errcode'], $body['errmsg'] ?? '')];
        }
        return ['ok' => true, 'remote_id' => (string) ($body['media_id'] ?? ''), 'message' => __('已在微信创建草稿(需手动审核发布)。', 'linked3')];
    }

    public function test(array $config) {
        $app_id = $config['app_id'] ?? '';
        $app_secret = $config['app_secret'] ?? '';
        if (!$app_id || !$app_secret) return ['ok' => false, 'message' => __('缺少 app_id/app_secret。', 'linked3')];
        $token = $this->get_access_token($app_id, $app_secret);
        if (is_wp_error($token)) return ['ok' => false, 'message' => $token->get_error_message()];
        return ['ok' => true, 'message' => __('微信 API 可访问。', 'linked3')];
    }

    /**
     * @param string $app_id
     * @param string $app_secret
     * @return string|\WP_Error
     */
    private function get_access_token(string $app_id, string $app_secret) : mixed {
        $cache_key = 'linked3_wechat_token_' . md5($app_id);
        $cached = get_transient($cache_key);
        if ($cached && is_string($cached)) return $cached;
        $resp = SafeRemote::get("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$app_id}&secret={$app_secret}", [
            'timeout' => 15,
            'allowed_hosts' => ['api.weixin.qq.com'],
        ]);
        if (is_wp_error($resp)) return $resp;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($body['access_token'])) {
            return new \WP_Error('wechat_token', $body['errmsg'] ?? __('无法获取 access_token。', 'linked3'));
        }
        $expires = max(60, (int) $body['expires_in'] - 300); // refresh 5 min early
        set_transient($cache_key, $body['access_token'], $expires);
        return $body['access_token'];
    }
}
