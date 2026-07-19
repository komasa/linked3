<?php
namespace Linked3\Classes\AutoGPT\Ajax\Actions;
use Linked3\Classes\AutoGPT\Ajax\Linked3_AutoGPT_Base_Ajax_Action;


if (!defined('ABSPATH')) exit;
/**
 * Autogpt delete task action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Ajax.Actions
 * @since      27.1.0
 */

final class Linked3_AutoGPT_Delete_Task_Action extends Linked3_AutoGPT_Base_Ajax_Action
{
    public function handle()
    : void {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) $this->send_error(__('需要任务 ID。', 'linked3'), 400);
        $ok = $this->repo()->delete($id, get_current_user_id());
        $this->send_success(['deleted' => $ok]);
    }
}
