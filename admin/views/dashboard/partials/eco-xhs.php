<?php
/**
 * 小红书图文生成器 — 管理界面 v19.2.
 *
 * 吸收独立小红书生成器的 UI 精华，适配 Linked3 仪表盘风格。
 *
 * @package Linked3
 */
if (!defined('ABSPATH')) exit;

$nonce_xhs = wp_create_nonce('linked3_xhs');
$ajax_url = admin_url('admin-ajax.php');

// 获取可用风格
$styles = [
    'lifestyle'   => __('生活分享', 'linked3'),
    'tutorial'    => __('教程干货', 'linked3'),
    'food'        => __('美食探店', 'linked3'),
    'travel'      => __('旅行攻略', 'linked3'),
    'beauty'      => __('美妆穿搭', 'linked3'),
    'tech'        => __('科技数码', 'linked3'),
    'business'    => __('商业创业', 'linked3'),
    'emotion'     => __('情感故事', 'linked3'),
];
?>
<div class="linked3-eco-card">
    <h3><?php echo esc_html__('📕 小红书图文生成器', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        AI 驱动的小红书爆款图文笔记生成。自动生成标题、正文、分页内容、配图提示词，支持多风格切换和 V15 品牌上下文。
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📝 主题', 'linked3'); ?></label>
            <input type="text" id="xhs-topic" class="linked3-eco-input" style="width:100%;font-size:14px;" placeholder="<?php echo esc_attr__('例如：如何在家做出完美的拿铁咖啡', 'linked3'); ?>">
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🔑 关键词（可选）', 'linked3'); ?></label>
            <input type="text" id="xhs-keyword" class="linked3-eco-input" style="width:100%;font-size:14px;" placeholder="<?php echo esc_attr__('例如：咖啡、拿铁、居家', 'linked3'); ?>">
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🎨 风格', 'linked3'); ?></label>
            <select id="xhs-style" class="linked3-eco-input" style="width:100%;font-size:14px;">
                <?php foreach ($styles as $id => $label): ?>
                    <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📄 页数', 'linked3'); ?></label>
            <select id="xhs-page-count" class="linked3-eco-input" style="width:100%;font-size:14px;">
                <option value="3"><?php echo esc_html__('3 页', 'linked3'); ?></option>
                <option value="5" selected><?php echo esc_html__('5 页', 'linked3'); ?></option>
                <option value="6"><?php echo esc_html__('6 页', 'linked3'); ?></option>
                <option value="8"><?php echo esc_html__('8 页', 'linked3'); ?></option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🤖 模型', 'linked3'); ?></label>
            <select id="xhs-model" class="linked3-eco-input" style="width:100%;font-size:14px;">
                <option value=""><?php echo esc_html__('默认模型', 'linked3'); ?></option>
                <option value="deepseek-chat">DeepSeek Chat</option>
                <option value="gpt-4o-mini">GPT-4o Mini</option>
                <option value="gpt-4o">GPT-4o</option>
            </select>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <label class="lk3-form-label"><?php echo esc_html__('✨ 自定义风格提示词（可选）', 'linked3'); ?></label>
        <textarea id="xhs-custom-style" class="linked3-eco-input" rows="2" style="width:100%;font-size:13px;" placeholder="<?php echo esc_attr__('补充风格要求，例如：使用日系清新风格，色调偏暖，文字简洁有力', 'linked3'); ?>"></textarea>
    </div>

    <button id="xhs-generate-btn" class="button button-primary" style="background:#ff2e4d;border-color:#ff2e4d;margin-bottom:16px;">
        📕 生成小红书图文
    </button>

    <div id="xhs-result" style="display:none;">
        <div id="xhs-result-title" style="font-size:18px;font-weight:600;margin-bottom:8px;"></div>
        <div id="xhs-result-content" style="font-size:14px;color:#555;margin-bottom:16px;white-space:pre-wrap;"></div>
        <div id="xhs-result-tags" style="margin-bottom:16px;"></div>
        <div id="xhs-result-pages" style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;"></div>
    </div>

    <div id="xhs-loading" style="display:none;text-align:center;padding:40px;">
        <span class="spinner is-active"></span>
        <p style="margin-top:10px;color:#666;"><?php echo esc_html__('AI 正在创作小红书图文...', 'linked3'); ?></p>
    </div>

    <div id="xhs-error" style="display:none;" class="notice notice-error inline">
        <p id="xhs-error-msg"></p>
    </div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-xhs.js ?>
