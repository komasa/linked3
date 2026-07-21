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
    public static function register()
    : void {
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
    public static function render_floating_widget()
    : void {
        if (!get_option(LINKED3_OPTION_PREFIX . 'chat_floating_enabled', 0)) return;
        if (is_admin()) return;
        echo self::widget_html(0, false);
    }

    /**
     * @param int  $bot_id
     * @param bool $embedded
     * @return string
     */
    private static function widget_html($bot_id, $embedded) : mixed {
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
     * 渲染聊天 widget JavaScript
     */
    private static function render_chat_js(string $id): void {
        ?>
        <script>
        (function(){
            var w=document.getElementById('<?php echo esc_js($id); ?>');
            if(!w||w.dataset.init)return;w.dataset.init='1';
            var nonce=w.dataset.nonce,ajaxUrl=w.dataset.ajaxUrl,guest=w.dataset.guest==='1';
            var sid=w.dataset.sessionId,botId=w.dataset.botId;
            var msgs=w.querySelector('.linked3-chat-messages');
            var src=w.querySelector('.linked3-chat-sources');
            var toggle=w.querySelector('.linked3-chat-toggle'),win=w.querySelector('.linked3-chat-window');
            var close=w.querySelector('.linked3-chat-close'),txt=w.querySelector('.linked3-chat-text'),send=w.querySelector('.linked3-chat-send');
            if(toggle)toggle.addEventListener('click',function(){win.style.display='';});
            if(close)close.addEventListener('click',function(){win.style.display='none';});
            function scroll() {msgs.scrollTop=msgs.scrollHeight;}
            function addMsg(role,text) {
                var d=document.createElement('div');d.className='linked3-chat-msg linked3-chat-'+role;d.textContent=text;
                msgs.appendChild(d);scroll();
            }
            function sendMsg(){
                var m=txt.value.trim();if(!m)return;
                addMsg('user',m);txt.value='';
                var fd=new FormData();fd.append('action','linked3_chat_send');fd.append('nonce',nonce);
                fd.append('session_id',sid);fd.append('message',m);fd.append('bot_id',botId);
                if(guest)fd.append('guest','1');
                fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){
                    if(res.success){addMsg('bot',res.data.reply);
                        if(res.data.sources&&res.data.sources.length){
                            src.innerHTML='<strong><?php echo esc_js(__('Sources:','linked3'));?></strong> '+res.data.sources.map(function(s){return '<a href="'+s.url+'" target="_blank">'+s.title+'</a>';}).join(', ');
                            src.style.display='';
                        }else{src.style.display='none';}
                    }else{addMsg('bot',res.data&&res.data.message?res.data.message:'Error');}
                }).catch(function(e){addMsg('bot','Network error');});
            }
            send.addEventListener('click',sendMsg);
            txt.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();}});
        })();
        </script>
        <?php
    }

    public static function enqueue_assets()
    : void {
        // Inline minimal CSS via wp_head to avoid extra HTTP requests.
        add_action('wp_head', static function () {
            echo '<style>
            .linked3-chat-floating{position:fixed;bottom:20px;right:20px;z-index:99999;font-family:system-ui,sans-serif;}
            .linked3-chat-toggle{width:56px;height:56px;border-radius:50%;background:#2563eb;color:#fff;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.2);font-size:24px;}
            .linked3-chat-window{position:absolute;bottom:70px;right:0;width:340px;max-width:calc(100vw - 40px);height:480px;max-height:calc(100vh - 100px);background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.2);display:flex;flex-direction:column;overflow:hidden;}
            .linked3-chat-embedded .linked3-chat-window{position:relative;bottom:0;right:0;width:100%;height:500px;}
            .linked3-chat-header{background:#2563eb;color:#fff;padding:12px;display:flex;justify-content:space-between;align-items:center;}
            .linked3-chat-close{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;}
            .linked3-chat-messages{flex:1;overflow-y:auto;padding:12px;}
            .linked3-chat-msg{margin-bottom:10px;padding:8px 12px;border-radius:8px;max-width:85%;word-wrap:break-word;}
            .linked3-chat-user{background:#e0e7ff;margin-left:auto;text-align:right;}
            .linked3-chat-bot{background:#f1f5f9;}
            .linked3-chat-sources{padding:8px 12px;font-size:11px;background:#fef3c7;border-top:1px solid #fde68a;}
            .linked3-chat-sources a{color:#2563eb;}
            .linked3-chat-input{display:flex;padding:8px;border-top:1px solid #e5e7eb;}
            .linked3-chat-text{flex:1;border:1px solid #d1d5db;border-radius:6px;padding:8px;resize:none;font-family:inherit;font-size:14px;}
            .linked3-chat-send{margin-left:8px;background:#2563eb;color:#fff;border:none;border-radius:6px;padding:0 16px;cursor:pointer;}
            </style>';
        });
    }
}
