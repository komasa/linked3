<?php
/**
 * Telegram distributor — posts to a channel via Bot API.
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
final class Linked3_Telegram_Distributor implements Linked3_Distribute_Adapter_Interface
{
    public function slug() : string { return 'telegram'; }
    public function label() : mixed { return __('Telegram', 'linked3'); }

    public function publish(array $post_data, array $config)
    : array {
        $token = $config['bot_token'] ?? '';
        $chat_id = $config['chat_id'] ?? '';
        if (!$token || !$chat_id) return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Bot Token 或 Chat ID。', 'linked3')];

        $title = $post_data['title'] ?? '';
        $url = $post_data['url'] ?? '';
        $excerpt = $post_data['excerpt'] ?? '';
        $text = sprintf("<b>%s</b>\n\n%s\n\n<a href=\"%s\">%s</a>",
            esc_html($title), esc_html(mb_substr($excerpt, 0, 300)), esc_url($url), esc_html__('阅读全文', 'linked3'));

        $resp = Linked3_Safe_Remote::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'timeout' => 30,
            'body' => [
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => 'false',
            ],
            'allowed_hosts' => ['api.telegram.org'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code !== 200 || empty($body['ok'])) {
            return ['ok' => false, 'remote_id' => '', 'message' => $body['description'] ?? "HTTP {$code}"];
        }
        return ['ok' => true, 'remote_id' => (string) ($body['result']['message_id'] ?? ''), 'message' => 'ok'];
    }

    public function test(array $config) : mixed {
        $token = $config['bot_token'] ?? '';
        $chat_id = $config['chat_id'] ?? '';
        if (!$token || !$chat_id) return ['ok' => false, 'message' => __('缺少 Bot Token 或 Chat ID。', 'linked3')];
        $resp = Linked3_Safe_Remote::post("https://api.telegram.org/bot{$token}/sendChatAction", [
            'timeout' => 15,
            'body' => ['chat_id' => $chat_id, 'action' => 'typing'],
            'allowed_hosts' => ['api.telegram.org'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return !empty($body['ok'])
            ? ['ok' => true, 'message' => __('Telegram 已连接。', 'linked3')]
            : ['ok' => false, 'message' => $body['description'] ?? 'Failed'];
    }
}
