<?php
/**
 * Publish + Collect hooks registrar — AJAX + admin menu.
 *
 * @package Linked3
 * @subpackage Classes\Publish
 */

namespace Linked3\Classes\Publish;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Publish_Collect_Hooks_Registrar
{
    public static function register()
    : void {
        // Publish AJAX actions.
        $pub_actions = [
            'linked3_publish_save_target'   => Ajax\Actions\Linked3_Publish_Save_Target_Action::class,
            'linked3_publish_test_target'   => Ajax\Actions\Linked3_Publish_Test_Target_Action::class,
            'linked3_publish_delete_target' => Ajax\Actions\Linked3_Publish_Delete_Target_Action::class,
            'linked3_publish_now'           => Ajax\Actions\Linked3_Publish_Now_Action::class,
        ];
        foreach ($pub_actions as $action => $class) {
            add_action('wp_ajax_' . $action, [new $class(), 'dispatch']);
        }

        // Collect AJAX actions.
        $col_actions = [
            'linked3_collect_scrape'        => \Linked3\Classes\Collect\Ajax\Actions\CollectScrapeAction::class,
            'linked3_collect_rewrite'       => \Linked3\Classes\Collect\Ajax\Actions\CollectRewriteAction::class,
            'linked3_collect_bulk_rewrite'  => \Linked3\Classes\Collect\Ajax\Actions\CollectBulkRewriteAction::class,
        ];
        foreach ($col_actions as $action => $class) {
            add_action('wp_ajax_' . $action, [new $class(), 'dispatch']);
        }

        // Admin menu.
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    }

    public static function register_admin_menu()
    : void {
        add_submenu_page('linked3-dashboard', '发布目标', '发布目标', 'edit_posts', 'linked3-publish', [__CLASS__, 'render_publish_page']);
        add_submenu_page('linked3-dashboard', '采集与改写', '采集与改写', 'edit_posts', 'linked3-collect', [__CLASS__, 'render_collect_page']);
    }

    public static function render_publish_page()
    : void {
        if (!current_user_can('edit_posts')) return;
        $repo = new Linked3_Publish_Target_Repository();
        $targets = $repo->all_for_user(get_current_user_id());
        include LINKED3_DIR . 'admin/views/publish/targets.php';
    }

    public static function render_collect_page()
    : void {
        if (!current_user_can('edit_posts')) return;
        include LINKED3_DIR . 'admin/views/collect/rewriter.php';
    }
}
