<?php

declare(strict_types=1);
namespace Linked3\Classes\Chat\Ajax\Actions;
use Linked3\Classes\Chat\Ajax\ChatBaseAjaxAction;
use Linked3\Classes\Chat\Storage\ChatStorage;


if (!defined('ABSPATH')) exit;
/**
 * Chat history action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Chat.Ajax.Actions
 * @since      27.1.0
 */

final class ChatHistoryAction extends ChatBaseAjaxAction
{
    public function handle()
    : void {
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $bot_id = (int) ($_POST['bot_id'] ?? 0);
        if (!$session_id) $this->send_error(__('需要会话。', 'linked3'), 400);
        $storage = new \Linked3\Classes\Chat\Storage\ChatStorage();
        $session = $storage->get_session($session_id, $bot_id, get_current_user_id());
        $this->send_success(['messages' => $session ? $session['messages'] : []]);
    }
}
