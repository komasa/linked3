<?php
if (!defined('ABSPATH')) exit;

// v1.0.0 FINAL-AUDIT: added Provider configuration form so admins can
// actually configure TTS / STT credentials (previously the page only showed
// usage text + shortcode docs, but the TTS shortcode + REST endpoint could
// never authenticate because no provider key was set).

$crypto_available = class_exists('\Linked3\Includes\Crypto');
$keys_raw = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
$tts_provider = get_option(LINKED3_OPTION_PREFIX . 'tts_provider', 'openai');
$tts_model    = get_option(LINKED3_OPTION_PREFIX . 'tts_model', 'tts-1');
$stt_provider = get_option(LINKED3_OPTION_PREFIX . 'stt_provider', 'openai');
$stt_model    = get_option(LINKED3_OPTION_PREFIX . 'stt_model', 'whisper-1');

// Decrypt keys for display (Crypto::decrypt is a no-op on plaintext).
$keys_display = [];
foreach ($keys_raw as $provider => $key) {
    $keys_display[$provider] = ($crypto_available && !empty($key))
        ? \Linked3\Includes\Crypto::decrypt((string) $key)
        : (string) $key;
}

// Handle form submission.
$saved = false;
if (isset($_POST['linked3_speech_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['linked3_speech_settings_nonce'])), 'linked3_speech_settings') && current_user_can('manage_options')) {
    $tts_provider = sanitize_text_field($_POST['tts_provider'] ?? 'openai');
    $tts_model    = sanitize_text_field($_POST['tts_model'] ?? 'tts-1');
    $stt_provider = sanitize_text_field($_POST['stt_provider'] ?? 'openai');
    $stt_model    = sanitize_text_field($_POST['stt_model'] ?? 'whisper-1');

    update_option(LINKED3_OPTION_PREFIX . 'tts_provider', $tts_provider);
    update_option(LINKED3_OPTION_PREFIX . 'tts_model', $tts_model);
    update_option(LINKED3_OPTION_PREFIX . 'stt_provider', $stt_provider);
    update_option(LINKED3_OPTION_PREFIX . 'stt_model', $stt_model);

    // Update provider keys (encrypt sensitive values).
    foreach (['openai', 'deepseek', 'azure', 'kimi'] as $p) {
        $val = sanitize_text_field($_POST['key_' . $p] ?? '');
        if ($val === '') continue;
        $keys_raw[$p] = $crypto_available ? \Linked3\Includes\Crypto::encrypt($val) : $val;
        $keys_display[$p] = $val;
    }
    update_option(LINKED3_OPTION_PREFIX . 'provider_keys', $keys_raw);
    $saved = true;
}
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Dashboard</h1>
        <a href="admin.php?page=linked3-dashboard" class="button">← 返回总览</a>
    </div>
    <h1><?php echo esc_html__('语音 — TTS / STT', 'linked3'); ?></h1>

    <?php if ($saved) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'linked3'); ?></p></div>
    <?php endif; ?>

    <?php if (!$crypto_available) : ?>
        <div class="notice notice-warning"><p><?php echo esc_html__('OpenSSL AES-256-GCM 不可用,API Key 将明文存储。请升级 PHP 或安装 openssl 扩展。', 'linked3'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('linked3_speech_settings', 'linked3_speech_settings_nonce'); ?>

        <h2><?php echo esc_html__('Provider 密钥', 'linked3'); ?></h2>
        <p class="description"><?php echo esc_html__('密钥加密存储(AES-256-GCM,需 OpenSSL)。与其他 Linked3 模块(对话、写作、商品 AI)共享。', 'linked3'); ?></p>
        <table class="form-table">
            <tr>
                <th><label for="key_openai">OpenAI API Key</label></th>
                <td><input type="password" id="key_openai" name="key_openai" value="<?php echo esc_attr($keys_display['openai'] ?? ''); ?>" class="regular-text" autocomplete="off" />
                    <p class="description"><?php echo esc_html__('Used for TTS (tts-1/tts-1-hd), STT (whisper-1), and chat completions.', 'linked3'); ?></p></td>
            </tr>
            <tr>
                <th><label for="key_deepseek">DeepSeek API Key</label></th>
                <td><input type="password" id="key_deepseek" name="key_deepseek" value="<?php echo esc_attr($keys_display['deepseek'] ?? ''); ?>" class="regular-text" autocomplete="off" />
                    <p class="description"><?php echo esc_html__('Used as fallback for chat completions (no TTS/STT support).', 'linked3'); ?></p></td>
            </tr>
            <tr>
                <th><label for="key_azure">Azure OpenAI API Key</label></th>
                <td><input type="password" id="key_azure" name="key_azure" value="<?php echo esc_attr($keys_display['azure'] ?? ''); ?>" class="regular-text" autocomplete="off" />
                    <p class="description"><?php echo esc_html__('可选,当 tts_provider / stt_provider = azure 时使用。', 'linked3'); ?></p></td>
            </tr>
            <tr>
                <th><label for="key_kimi">Kimi (Moonshot) API Key</label></th>
                <td><input type="password" id="key_kimi" name="key_kimi" value="<?php echo esc_attr($keys_display['kimi'] ?? ''); ?>" class="regular-text" autocomplete="off" />
                    <p class="description"><?php echo esc_html__('可选,中文 LLM Provider(仅对话补全)。', 'linked3'); ?></p></td>
            </tr>
        </table>

        <h2><?php echo esc_html__('Text-to-Speech (TTS)', 'linked3'); ?></h2>
        <p><?php echo esc_html__('嵌入朗读按钮短代码:', 'linked3'); ?> <code>[linked3_tts text="Your text here" voice="alloy"]</code></p>
        <p><?php echo esc_html__('语音:alloy, echo, fable, onyx, nova, shimmer(OpenAI)。', 'linked3'); ?></p>
        <table class="form-table">
            <tr>
                <th><label for="tts_provider"><?php echo esc_html__('默认 Provider', 'linked3'); ?></label></th>
                <td><select id="tts_provider" name="tts_provider">
                    <option value="openai" <?php selected($tts_provider, 'openai'); ?>>OpenAI</option>
                    <option value="azure" <?php selected($tts_provider, 'azure'); ?>>Azure OpenAI</option>
                </select></td>
            </tr>
            <tr>
                <th><label for="tts_model"><?php echo esc_html__('默认模型', 'linked3'); ?></label></th>
                <td><input type="text" id="tts_model" name="tts_model" value="<?php echo esc_attr($tts_model); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__('OpenAI:tts-1(快速)或 tts-1-hd(高质量)。', 'linked3'); ?></p></td>
            </tr>
        </table>

        <h2><?php echo esc_html__('Speech-to-Text (STT)', 'linked3'); ?></h2>
        <p><?php echo esc_html__('STT is available via the REST API (POST /wp-json/linked3/v1/stt) for integration with the block editor. Upload audio → get transcription.', 'linked3'); ?></p>
        <table class="form-table">
            <tr>
                <th><label for="stt_provider"><?php echo esc_html__('默认 Provider', 'linked3'); ?></label></th>
                <td><select id="stt_provider" name="stt_provider">
                    <option value="openai" <?php selected($stt_provider, 'openai'); ?>>OpenAI Whisper</option>
                    <option value="azure" <?php selected($stt_provider, 'azure'); ?>>Azure Speech</option>
                </select></td>
            </tr>
            <tr>
                <th><label for="stt_model"><?php echo esc_html__('默认模型', 'linked3'); ?></label></th>
                <td><input type="text" id="stt_model" name="stt_model" value="<?php echo esc_attr($stt_model); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__('OpenAI:whisper-1。', 'linked3'); ?></p></td>
            </tr>
        </table>

        <h2><?php echo esc_html__('Rate Limits', 'linked3'); ?></h2>
        <ul>
            <li><?php echo esc_html__('TTS: 10 requests/hour/user (Pro+ only)', 'linked3'); ?></li>
            <li><?php echo esc_html__('STT: 10 requests/hour/user (Pro+ only)', 'linked3'); ?></li>
        </ul>

        <p><button type="submit" class="button button-primary"><?php echo esc_html__('保存设置', 'linked3'); ?></button></p>
    </form>

    <h2><?php echo esc_html__('用量', 'linked3'); ?></h2>
    <p><?php echo esc_html__('TTS/STT calls are billed via the AI Dispatcher token manager.', 'linked3'); ?></p>
</div>
