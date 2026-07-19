<?php
namespace Linked3\Classes\Chat\Ajax\Actions;
use Linked3\Classes\Chat\Ajax\Linked3_Chat_Base_Ajax_Action;


if (!defined('ABSPATH')) exit;
/**
 * Chat send action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Chat.Ajax.Actions
 * @since      27.1.0
 */

final class Linked3_Chat_Send_Action extends Linked3_Chat_Base_Ajax_Action
{
    public function handle()
    : void {
        $session_id = sanitize_text_field($_POST['session_id'] ?? wp_generate_password(24, false));
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $bot_id = (int) ($_POST['bot_id'] ?? 0);
        if (empty($message)) $this->send_error(__('空消息。', 'linked3'), 400);

        $bot_config = $this->get_bot_config($bot_id);
        $result = $this->manager()->chat($session_id, $message, $bot_id, $bot_config);
        if (!$result['ok']) {
            $this->send_error($result['message'], 402);
        }
        $this->send_success([
            'reply' => $result['reply'],
            'sources' => $result['sources'],
            'usage' => $result['usage'],
        ]);
    }

    private function get_bot_config($bot_id) : mixed {
        // Bots stored as options (MVP). v0.7.10 admin page will add CRUD.
        $bots = get_option(LINKED3_OPTION_PREFIX . 'chat_bots', []);
        if (is_array($bots) && isset($bots[$bot_id])) {
            return $bots[$bot_id];
        }
        // Default bot.
        return [
            'provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'),
            'model' => 'gpt-4o-mini',
            'system_prompt' => get_option(LINKED3_OPTION_PREFIX . 'chat_system_prompt', __('您是一位乐于助人的助手,请简洁回答。', 'linked3')),
            'use_rag' => (bool) get_option(LINKED3_OPTION_PREFIX . 'chat_use_rag', false),
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ];
    }
}
