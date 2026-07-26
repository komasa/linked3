<?php

declare(strict_types=1);
/**
 * Chat shortcode — renders the floating chat widget.
 *
 * Usage: [linked3_chat bot_id="0"]
 *
 * @package Linked3
 * @subpackage Classes\Chat\Shortcode
 */

namespace Linked3\Classes\Chat\Shortcode;

if (!defined('ABSPATH')) {
    exit;
}

final class ChatShortcode
{
    static function register(): void {
        add_shortcode('linked3_chat', [__CLASS__, 'render']);
        add_action('wp_footer', [__CLASS__, 'render_floating_widget']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function render($atts) : mixed {
        $atts = shortcode_atts(['bot_id' => '0', 'embedded' => '0'], $atts, 'linked3_chat');
        $bot_id = (int) $atts['bot_id'];
        $embedded = !empty($atts['embedded']);
        return self::widget_html($bot_id, $embedded);
    }

    /**
     * Floating widget on every page (if enabled in settings).
     *
     * @return void
     */
    static function render_floating_widget(): void {
        if (!get_option(LINKED3_OPTION_PREFIX . 'chat_floating_enabled', 0)) return;
        if (is_admin()) return;
        echo self::widget_html(0, false);
    }

    /**
     * @param int  $bot_id
     * @param bool $embedded
     * @return string
     */
    private static function widget_html(int $bot_id, bool $embedded) : mixed {
        $nonce = wp_create_nonce('linked3_chat');
        $ajax_url = admin_url('admin-ajax.php');
        [$session_id, $is_guest] = self::init_chat_session();

        $id = 'linked3-chat-' . wp_generate_password(6, false);
        $greeting = esc_html(get_option(LINKED3_OPTION_PREFIX . 'chat_greeting', __('您好!今天有什么可以帮您?', 'linked3')));
        $title = esc_html(get_option(LINKED3_OPTION_PREFIX . 'chat_title', __('AI 助手', 'linked3')));

        ob_start();
        self::render_chat_html($id, $bot_id, $embedded, $nonce, $ajax_url, $is_guest, $session_id, $greeting, $title);
        self::render_chat_js($id);
        return ob_get_clean();
    }

    /**
     * 初始化聊天会话 (访客 cookie / 登录用户)
     * @return array{0:string,1:string} [session_id, is_guest]
     */
    private static function init_chat_session(): array {
        $is_guest = is_user_logged_in() ? '0' : '1';
        $session_id = isset($_COOKIE['linked3_chat_sid'])
            ? sanitize_text_field(wp_unslash($_COOKIE['linked3_chat_sid']))
            : wp_generate_password(24, false);
        if (!is_user_logged_in() && !headers_sent()) {
            setcookie('linked3_chat_sid', $session_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        return [$session_id, $is_guest];
    }

    /**
     * 渲染聊天 widget HTML
     */
    private static function render_chat_html(string $id, int $bot_id, bool $embedded, string $nonce, string $ajax_url, string $is_guest, string $session_id, string $greeting, string $title): void {
        ?>
        <div class="linked3-chat<?php echo $embedded ? ' linked3-chat-embedded' : ' linked3-chat-floating'; ?>" id="<?php echo esc_attr($id); ?>"
             data-bot-id="<?php echo esc_attr($bot_id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"
             data-ajax-url="<?php echo esc_attr($ajax_url); ?>" data-guest="<?php echo esc_attr($is_guest); ?>"
             data-session-id="<?php echo esc_attr($session_id); ?>">
            <?php if (!$embedded) : ?>
            <button class="linked3-chat-toggle" aria-label="<?php esc_attr_e('打开对话', 'linked3'); ?>">
                <span class="dashicons dashicons-format-chat"></span>
            </button>
            <?php endif; ?>
            <div class="linked3-chat-window"<?php if (!$embedded) echo ' style="display:none;"'; ?>>
                <div class="linked3-chat-header">
                    <span class="linked3-chat-title"><?php echo esc_html($title); ?></span>
                    <?php if (!$embedded) : ?><button class="linked3-chat-close" aria-label="<?php esc_attr_e('关闭', 'linked3'); ?>">&times;</button><?php endif; ?>
                </div>
                <div class="linked3-chat-messages">
                    <div class="linked3-chat-msg linked3-chat-bot"><?php echo esc_html($greeting); ?></div>
                </div>
                <div class="linked3-chat-sources" style="display:none;"></div>
                <div class="linked3-chat-input">
                    <textarea class="linked3-chat-text" rows="1" placeholder="<?php esc_attr_e('Type your message…', 'linked3'); ?>"></textarea>
                    <button class="linked3-chat-send"><?php esc_html_e('Send', 'linked3'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-chat-widget.js
     * The JS auto-initializes all .linked3-chat elements via DOMContentLoaded.
     * No per-instance inline script needed — data attributes carry all config.
     */
    private static function render_chat_js(string $id): void {
        // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-chat-widget.js
    }

    static function enqueue_assets(): void {
        wp_enqueue_style(
            'linked3-chat-widget',
            LINKED3_URL . 'assets/css/linked3-chat-widget.css',
            [],
            LINKED3_VERSION
        );
        wp_enqueue_script(
            'linked3-chat-widget',
            LINKED3_URL . 'assets/js/linked3-chat-widget.js',
            [],
            LINKED3_VERSION,
            true
        );
        wp_localize_script('linked3-chat-widget', 'linked3_chat_i18n', [
            'sources_label' => __('Sources:', 'linked3'),
        ]);
    }
}
