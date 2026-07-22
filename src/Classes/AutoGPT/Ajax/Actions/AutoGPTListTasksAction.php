<?php

declare(strict_types=1);
namespace Linked3\Classes\AutoGPT\Ajax\Actions;
use Linked3\Classes\AutoGPT\Ajax\AutoGPTBaseAjaxAction;


if (!defined('ABSPATH')) exit;
/**
 * Autogpt list tasks action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Ajax.Actions
 * @since      27.1.0
 */

final class AutoGPTListTasksAction extends AutoGPTBaseAjaxAction
{
    public function handle(): void {
        $tasks = $this->repo()->all(get_current_user_id());
        foreach ($tasks as &$t) {
            $t['config'] = json_decode($t['config'], true) ?: [];
        }
        $this->send_success(['tasks' => $tasks]);
    }
}
