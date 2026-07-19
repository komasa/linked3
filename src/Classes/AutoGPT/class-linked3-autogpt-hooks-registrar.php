<?php
namespace Linked3\Classes\AutoGPT;
use Linked3\Classes\AutoGPT\Cron\Linked3_AutoGPT_Cron;


if (!defined('ABSPATH')) exit;
/**
 * Autogpt hooks registrar.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT
 * @since      27.1.0
 */

final class Linked3_AutoGPT_Hooks_Registrar
{
    public static function register()
    : void {
        // Cron init.
        Linked3_AutoGPT_Cron::init();

        // AJAX actions.
        $actions = [
            'linked3_autogpt_create_task' => Ajax\Actions\Linked3_AutoGPT_Create_Task_Action::class,
            'linked3_autogpt_list_tasks'  => Ajax\Actions\Linked3_AutoGPT_List_Tasks_Action::class,
            'linked3_autogpt_toggle_task' => Ajax\Actions\Linked3_AutoGPT_Toggle_Task_Action::class,
            'linked3_autogpt_delete_task' => Ajax\Actions\Linked3_AutoGPT_Delete_Task_Action::class,
        ];
        foreach ($actions as $action => $class) {
            add_action('wp_ajax_' . $action, [new $class(), 'dispatch']);
        }

        // Admin menu.
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    }

    public static function register_admin_menu()
    : void {
        add_submenu_page('linked3-dashboard', '自动 Agent', '自动 Agent', 'manage_options', 'linked3-autogpt', [__CLASS__, 'render_admin_page']);
    }

    public static function render_admin_page()
    : void {
        if (!current_user_can('manage_options')) return;
        $repo = new Linked3_AutoGPT_Task_Repository();
        $tasks = $repo->all(get_current_user_id());
        include LINKED3_DIR . 'admin/views/autogpt/dashboard.php';
    }
}
