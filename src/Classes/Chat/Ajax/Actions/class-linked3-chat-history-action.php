<?php
namespace Linked3\Classes\Chat\Ajax\Actions;
use Linked3\Classes\Chat\Ajax\Linked3_Chat_Base_Ajax_Action;
use Linked3\Classes\Chat\Storage\Linked3_Chat_Storage;


if (!defined('ABSPATH')) exit;
/**
 * Chat history action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Chat.Ajax.Actions
 * @since      27.1.0
 */

final class Linked3_Chat_History_Action extends Linked3_Chat_Base_Ajax_Action
{
    public function handle()
    : void {
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $bot_id = (int) ($_POST['bot_id'] ?? 0);
        if (!$session_id) $this->send_error(__('需要会话。', 'linked3'), 400);
        $storage = new \Linked3\Classes\Chat\Storage\Linked3_Chat_Storage();
        $session = $storage->get_session($session_id, $bot_id, get_current_user_id());
        $this->send_success(['messages' => $session ? $session['messages'] : []]);
    }
}
