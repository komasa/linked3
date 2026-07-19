<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('linked3_chat');
$floating_enabled = get_option(LINKED3_OPTION_PREFIX . 'chat_floating_enabled', 0);
$system_prompt = get_option(LINKED3_OPTION_PREFIX . 'chat_system_prompt', __('您是一位乐于助人的助手,请简洁回答。', 'linked3'));
$use_rag = get_option(LINKED3_OPTION_PREFIX . 'chat_use_rag', 0);
$guest_limit = get_option(LINKED3_OPTION_PREFIX . 'guest_chat_limit', 10);
$greeting = get_option(LINKED3_OPTION_PREFIX . 'chat_greeting', __('您好!今天有什么可以帮您?', 'linked3'));
$title = get_option(LINKED3_OPTION_PREFIX . 'chat_title', __('AI 助手', 'linked3'));
$mod_banned_words = get_option(LINKED3_OPTION_PREFIX . 'moderation_banned_words', '');
$mod_banned_ips = get_option(LINKED3_OPTION_PREFIX . 'moderation_banned_ips', '');
$mod_openai_enabled = get_option(LINKED3_OPTION_PREFIX . 'moderation_openai_enabled', 0);
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Settings</h1>
        <a href="admin.php?page=linked3-dashboard" class="button">← 返回总览</a>
    </div>
    <h1><?php echo esc_html__('AI 对话设置', 'linked3'); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields('linked3_chat_settings'); ?>
        <h2><?php echo esc_html__('组件', 'linked3'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php echo esc_html__('浮动组件', 'linked3'); ?></th>
                <td><label><input type="checkbox" name="linked3_chat_floating_enabled" value="1" <?php checked($floating_enabled); ?> /> <?php echo esc_html__('在所有页面显示浮动对话组件', 'linked3'); ?></label></td>
            </tr>
            <tr>
                <th><label for="chat_title"><?php echo esc_html__('组件标题', 'linked3'); ?></label></th>
                <td><input type="text" id="chat_title" name="linked3_chat_title" value="<?php echo esc_attr($title); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="chat_greeting"><?php echo esc_html__('欢迎消息', 'linked3'); ?></label></th>
                <td><input type="text" id="chat_greeting" name="linked3_chat_greeting" value="<?php echo esc_attr($greeting); ?>" class="regular-text" /></td>
            </tr>
        </table>

        <h2><?php echo esc_html__('AI 配置', 'linked3'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="chat_system_prompt"><?php echo esc_html__('系统提示词', 'linked3'); ?></label></th>
                <td><textarea id="chat_system_prompt" name="linked3_chat_system_prompt" rows="4" class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('RAG (Knowledge Base)', 'linked3'); ?></th>
                <td><label><input type="checkbox" name="linked3_chat_use_rag" value="1" <?php checked($use_rag); ?> /> <?php echo esc_html__('Retrieve context from site content (requires vector indexing)', 'linked3'); ?></label></td>
            </tr>
        </table>

        <h2><?php echo esc_html__('游客访问', 'linked3'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="guest_limit"><?php echo esc_html__('游客每日消息数', 'linked3'); ?></label></th>
                <td><input type="number" id="guest_limit" name="linked3_guest_chat_limit" value="<?php echo esc_attr($guest_limit); ?>" min="0" max="100" /> <p class="description"><?php echo esc_html__('0 = 禁用游客,登录用户按套餐配额。', 'linked3'); ?></p></td>
            </tr>
        </table>

        <h2><?php echo esc_html__('内容审核(三层防御)', 'linked3'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="mod_banned_words"><?php echo esc_html__('封禁词', 'linked3'); ?></label></th>
                <td><textarea id="mod_banned_words" name="linked3_moderation_banned_words" rows="5" class="large-text code"><?php echo esc_textarea($mod_banned_words); ?></textarea>
                    <p class="description"><?php echo esc_html__('每行一个模式。以「/」开头的行按正则(PCRE)处理,其他为不区分大小写的子串匹配。', 'linked3'); ?></p></td>
            </tr>
            <tr>
                <th><label for="mod_banned_ips"><?php echo esc_html__('封禁 IP', 'linked3'); ?></label></th>
                <td><textarea id="mod_banned_ips" name="linked3_moderation_banned_ips" rows="3" class="large-text code"><?php echo esc_textarea($mod_banned_ips); ?></textarea>
                    <p class="description"><?php echo esc_html__('每行一个 IP 或 CIDR(如 1.2.3.4 或 10.0.0.0/8),支持 IPv4 和 IPv6。', 'linked3'); ?></p></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('OpenAI 内容审核', 'linked3'); ?></th>
                <td><label><input type="checkbox" name="linked3_moderation_openai_enabled" value="1" <?php checked($mod_openai_enabled); ?> /> <?php echo esc_html__('Send each message to OpenAI Moderation API (fail-open on errors; use the linked3/moderation_fail_closed filter to enforce blocking)', 'linked3'); ?></label></td>
            </tr>
        </table>

        <?php submit_button(__('保存设置', 'linked3')); ?>
    </form>

    <h2><?php echo esc_html__('Shortcode', 'linked3'); ?></h2>
    <p><?php echo esc_html__('内嵌对话短代码:', 'linked3'); ?> <code>[linked3_chat bot_id="0" embedded="1"]</code></p>

    <h2><?php echo esc_html__('向量索引', 'linked3'); ?></h2>
    <p><?php echo esc_html__('启用 RAG 后,保存文章时自动索引。重建索引:', 'linked3'); ?></p>
    <p><button class="button" id="linked3-reindex"><?php echo esc_html__('重建全部文章索引', 'linked3'); ?></button></p>
</div>
<?php
// Settings registration now happens in
// Linked3_Chat_Hooks_Registrar::register_settings() on admin_init (v1.0.0
// FINAL-AUDIT fix) so it fires for options.php submissions too. The previous
// in-view add_action('admin_init', ...) was a no-op because admin_init had
// already fired by the time the view rendered.
