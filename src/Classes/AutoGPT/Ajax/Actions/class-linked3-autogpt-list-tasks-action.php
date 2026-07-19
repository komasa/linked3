<?php
namespace Linked3\Classes\AutoGPT\Ajax\Actions;
use Linked3\Classes\AutoGPT\Ajax\Linked3_AutoGPT_Base_Ajax_Action;


if (!defined('ABSPATH')) exit;
/**
 * Autogpt list tasks action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Ajax.Actions
 * @since      27.1.0
 */

final class Linked3_AutoGPT_List_Tasks_Action extends Linked3_AutoGPT_Base_Ajax_Action
{
    public function handle()
    : void {
        $tasks = $this->repo()->all(get_current_user_id());
        foreach ($tasks as &$t) {
            $t['config'] = json_decode($t['config'], true) ?: [];
        }
        $this->send_success(['tasks' => $tasks]);
    }
}
