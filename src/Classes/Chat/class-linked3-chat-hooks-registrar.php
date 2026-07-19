<?php
namespace Linked3\Classes\Chat;
if (!defined('ABSPATH')) exit;

/**
 * Chat hooks registrar.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Chat
 * @since      27.1.0
 */

final class Linked3_Chat_Hooks_Registrar
{
    public static function register() : void
    {
        // AJAX actions.
        add_action('wp_ajax_linked3_chat_send', [new Ajax\Actions\Linked3_Chat_Send_Action(), 'dispatch']);
        add_action('wp_ajax_nopriv_linked3_chat_send', [new Ajax\Actions\Linked3_Chat_Send_Action(), 'dispatch']);
        add_action('wp_ajax_linked3_chat_history', [new Ajax\Actions\Linked3_Chat_History_Action(), 'dispatch']);

        // Shortcode + floating widget.
        Shortcode\Linked3_Chat_Shortcode::register();

        // Vector post-processor — auto-index on save_post.
        add_action('save_post', ['\\Linked3\\Classes\\Vector\\PostProcessor\\Linked3_Post_Processor', 'on_save_post'], 10, 3);
        add_action('delete_post', ['\\Linked3\\Classes\\Vector\\PostProcessor\\Linked3_Post_Processor', 'on_delete_post']);

        // Admin menu.
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);

        // Register settings on admin_init (v1.0.0 FINAL-AUDIT fix):
        // previously register_setting() was called inside the view template
        // (admin/views/chat/settings.php), which only renders when the user
        // visits the menu page — by then admin_init has already fired, so the
        // registration was a no-op and the settings form (POST to options.php)
        // could never be saved. Move it here so it fires on every admin page
        // load, including options.php's own admin_init invocation.
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register chat-related options so WP's options.php accepts the form
     * submission from admin/views/chat/settings.php.
     *
     * @return void
     */
    public static function register_settings() : void
    {
        $opts = [
            'linked3_chat_floating_enabled',
            'linked3_chat_title',
            'linked3_chat_greeting',
            'linked3_chat_system_prompt',
            'linked3_chat_use_rag',
            'linked3_guest_chat_limit',
            'linked3_moderation_banned_words',
            'linked3_moderation_banned_ips',
            'linked3_moderation_openai_enabled',
        ];
        foreach ($opts as $o) {
            register_setting('linked3_chat_settings', $o);
        }
    }

    public static function register_admin_menu()
    {
        add_submenu_page('linked3-dashboard', 'AI 对话', 'AI 对话', 'manage_options', 'linked3-chat', [__CLASS__, 'render_admin_page']);
    }

    public static function render_admin_page()
    {
        if (!current_user_can('manage_options')) return;
        include LINKED3_DIR . 'admin/views/chat/settings.php';
    }
}
