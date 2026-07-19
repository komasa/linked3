<?php
/**
 * Content Writer hooks registrar — binds all AJAX actions + admin menu.
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter
 */

namespace Linked3\Classes\ContentWriter;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Content_Writer_Hooks_Registrar
{
    /**
     * @return void
     */
    public static function register()
    : void {
        // Ensure default templates exist for the current user.
        add_action('admin_init', static function () {
            (new Linked3_Content_Template_Manager())->ensure_defaults(get_current_user_id());
        });

        // v19.50.1: linked3_seo_system_prompt 钩子由 MetaLever_Hooks_Registrar 统一注册

        // Register AJAX actions (all admin-only, nonce+cap+plan gated via base class).
        $actions = [
            'linked3_generate_content' => Ajax\Actions\Linked3_Generate_Content_Action::class,
            'linked3_generate_title'   => Ajax\Actions\Linked3_Generate_Title_Action::class,
            'linked3_generate_meta'    => Ajax\Actions\Linked3_Generate_Meta_Action::class,
            'linked3_generate_tags'    => Ajax\Actions\Linked3_Generate_Tags_Action::class,
            'linked3_generate_excerpt' => Ajax\Actions\Linked3_Generate_Excerpt_Action::class,
            'linked3_init_stream'      => Ajax\Actions\Linked3_Init_Stream_Action::class,
        ];
        foreach ($actions as $action => $class) {
            add_action('wp_ajax_' . $action, [new $class(), 'dispatch']);
        }

        // Admin menu.
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    }

    /**
     * @return void
     */
    public static function register_admin_menu()
    : void {
        add_submenu_page('linked3-dashboard', '内容写作', '内容写作', 'edit_posts', 'linked3-content-writer', [__CLASS__, 'render_admin_page']);
    }

    /**
     * @return void
     */
    public static function render_admin_page()
    : void {
        if (!current_user_can('edit_posts')) {
            return;
        }
        $templates = (new Linked3_Content_Template_Manager())->get_for_user(get_current_user_id());
        include LINKED3_DIR . 'admin/views/content-writer/editor.php';
    }
}
