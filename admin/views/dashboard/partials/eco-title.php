<?php
/**
 * 标题生成子面板 v12.0 — AI多风格标题生成
 *
 * 参照国际顶级规范: Jasper / Copy.ai / Rytr / Writesonic
 * 功能: 多风格标题 / A/B测试候选 / 点击率优化 / 情感分析
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_title = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
?>

<div class="linked3-eco-card">
    <h3><?php echo esc_html__('💡 标题生成 — AI多风格标题 + 点击率优化', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        参照 Jasper / Copy.ai / Writesonic 规范。输入主题, AI生成多种风格标题(疑问式/数字式/如何式/对比式/情感式), 支持A/B测试候选与点击率预估。
    </p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input type="text" id="title-input-topic" class="linked3-eco-input" style="flex:1;min-width:300px;" placeholder="<?php echo esc_attr__('输入主题, 如: AI写作工具推荐', 'linked3'); ?>">
        <select id="title-style" class="linked3-eco-select" style="width:140px;">
            <option value="all"><?php echo esc_html__('全部风格', 'linked3'); ?></option>
            <option value="question"><?php echo esc_html__('疑问式', 'linked3'); ?></option>
            <option value="number"><?php echo esc_html__('数字式', 'linked3'); ?></option>
            <option value="howto"><?php echo esc_html__('如何式', 'linked3'); ?></option>
            <option value="compare"><?php echo esc_html__('对比式', 'linked3'); ?></option>
            <option value="emotion"><?php echo esc_html__('情感式', 'linked3'); ?></option>
            <option value="list"><?php echo esc_html__('清单式', 'linked3'); ?></option>
        </select>
        <select id="title-count" class="linked3-eco-select" style="width:100px;">
            <option value="5"><?php echo esc_html__('5条', 'linked3'); ?></option>
            <option value="10" selected><?php echo esc_html__('10条', 'linked3'); ?></option>
            <option value="20"><?php echo esc_html__('20条', 'linked3'); ?></option>
        </select>
        <button class="linked3-eco-btn" id="title-generate"><?php echo esc_html__('💡 生成标题', 'linked3'); ?></button>
    </div>

    <div id="title-result" style="display:none;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;" id="title-list">
            <!-- AI生成的标题将动态插入这里 -->
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
            <button class="linked3-eco-btn linked3-eco-btn-secondary" id="title-copy-all"><?php echo esc_html__('📋 复制全部', 'linked3'); ?></button>
            <button class="linked3-eco-btn linked3-eco-btn-secondary" id="title-regenerate"><?php echo esc_html__('🔄 重新生成', 'linked3'); ?></button>
        </div>
    </div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-title.js ?>
