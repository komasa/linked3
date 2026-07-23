<?php

declare(strict_types=1);
/**
 * Discord distributor — posts to a channel via webhook URL.
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
final class DiscordDistributor implements DistributeAdapterInterface
{
    public function slug() : string { return 'discord'; }
    public function label() : string { return __('Discord', 'linked3'); }

    public function publish(array $post_data, array $config): array {
        $webhook = $config['webhook_url'] ?? '';
        if (!$webhook) return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Webhook URL。', 'linked3')];

        $title = $post_data['title'] ?? '';
        $url = $post_data['url'] ?? '';
        $excerpt = $post_data['excerpt'] ?? '';
        $image = $post_data['image_url'] ?? '';

        $payload = [
            'username' => $config['bot_name'] ?? 'Linked3',
            'embeds' => [[
                'title' => $title,
                'description' => mb_substr($excerpt, 0, 2048),
                'url' => $url,
                'color' => 0x2563eb,
            ]],
        ];
        if ($image) $payload['embeds'][0]['image'] = ['url' => $image];

        $resp = SafeRemote::post($webhook, [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'allowed_hosts' => [wp_parse_url($webhook, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        if ($code !== 204 && $code !== 200) {
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf(__('HTTP %d:%s', 'linked3'), $code, substr($body, 0, 200))];
        }
        return ['ok' => true, 'remote_id' => '', 'message' => 'ok'];
    }

    public function test(array $config) : mixed {
        $webhook = $config['webhook_url'] ?? '';
        if (!$webhook) return ['ok' => false, 'message' => __('缺少 Webhook URL。', 'linked3')];
        $resp = SafeRemote::post($webhook, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['content' => '🔗 Linked3 connection test — you can delete this message.']),
            'allowed_hosts' => [wp_parse_url($webhook, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        return in_array($code, [200, 204], true)
            ? ['ok' => true, 'message' => __('Discord Webhook 可访问。', 'linked3')]
            : ['ok' => false, 'message' => sprintf(__('HTTP %d', 'linked3'), $code)];
    }
}
