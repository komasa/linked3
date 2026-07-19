<?php

declare(strict_types=1);
namespace Linked3\Classes\Publish\Ajax\Actions;
use Linked3\Classes\Publish\Ajax\PublishBaseAjaxAction;


if (!defined('ABSPATH')) exit;
/**
 * Publish test target action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Publish.Ajax.Actions
 * @since      27.1.0
 */

final class PublishTestTargetAction extends PublishBaseAjaxAction
{
    public function handle()
    : void {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) $this->send_error(__('需要目标 ID。', 'linked3'), 400);
        $res = $this->manager()->test_target($id, get_current_user_id());
        if ($res['ok']) {
            $this->send_success($res);
        } else {
            $this->send_error($res['message'], 400);
        }
    }
}
