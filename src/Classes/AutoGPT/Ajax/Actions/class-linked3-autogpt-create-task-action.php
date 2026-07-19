<?php
namespace Linked3\Classes\AutoGPT\Ajax\Actions;
use Linked3\Classes\AutoGPT\Ajax\Linked3_AutoGPT_Base_Ajax_Action;


if (!defined('ABSPATH')) exit;
/**
 * Autogpt create task action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Ajax.Actions
 * @since      27.1.0
 */

final class Linked3_AutoGPT_Create_Task_Action extends Linked3_AutoGPT_Base_Ajax_Action
{
    public function handle()
    : void {
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'task_type' => sanitize_text_field($_POST['task_type'] ?? 'content-writing'),
            'schedule' => sanitize_text_field($_POST['schedule'] ?? 'hourly'),
            'config' => $this->parse_config(),
            'user_id' => get_current_user_id(),
        ];
        $res = $this->repo()->create($data);
        if (is_wp_error($res)) $this->send_error($res->get_error_message(), 400);
        $this->send_success(['id' => $res]);
    }

    private function parse_config() : mixed {
        $cfg = wp_unslash($_POST['config'] ?? []);
        if (is_string($cfg)) $cfg = json_decode(wp_unslash($cfg), true) ?: [];
        return is_array($cfg) ? $cfg : [];
    }
}
