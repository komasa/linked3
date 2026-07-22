<?php

declare(strict_types=1);
namespace Linked3\Classes\AutoGPT\Ajax\Actions;
use Linked3\Classes\AutoGPT\Ajax\AutoGPTBaseAjaxAction;


if (!defined('ABSPATH')) exit;
/**
 * Autogpt toggle task action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Ajax.Actions
 * @since      27.1.0
 */

final class AutoGPTToggleTaskAction extends AutoGPTBaseAjaxAction
{
    public function handle(): void {
        $id = (int) ($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'active');
        if (!$id) $this->send_error(__('需要任务 ID。', 'linked3'), 400);
        $ok = $this->repo()->update_status($id, get_current_user_id(), $status);
        $this->send_success(['updated' => $ok]);
    }
}
