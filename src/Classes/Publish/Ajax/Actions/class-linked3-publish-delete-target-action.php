<?php
namespace Linked3\Classes\Publish\Ajax\Actions;
use Linked3\Classes\Publish\Ajax\Linked3_Publish_Base_Ajax_Action;


if (!defined('ABSPATH')) exit;
/**
 * Publish delete target action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Publish.Ajax.Actions
 * @since      27.1.0
 */

final class Linked3_Publish_Delete_Target_Action extends Linked3_Publish_Base_Ajax_Action
{
    public function handle()
    : void {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) $this->send_error(__('需要目标 ID。', 'linked3'), 400);
        $ok = $this->repo()->delete($id, get_current_user_id());
        $this->send_success(['deleted' => $ok]);
    }
}
