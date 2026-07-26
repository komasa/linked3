<?php
/**
 * 摘要生成子面板 v12.0 — AI摘要/摘要/TL;DR生成
 *
 * 参照国际顶级规范: Notion AI / Grammarly / QuillBot / ChatGPT
 * 功能: 摘要 / TL;DR / 关键要点 / 不同长度 / 不同语气
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_summary = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
?>

<div class="linked3-eco-card">
    <h3><?php echo esc_html__('📄 摘要生成 — AI摘要 / TL;DR / 关键要点', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        参照 Notion AI / QuillBot / Grammarly 规范。输入长文, AI自动生成摘要、TL;DR、关键要点, 支持不同长度(短/中/长)与语气(专业/轻松/学术)。
    </p>

    <div style="margin-bottom:16px;">
        <label class="lk3-form-label"><?php echo esc_html__('📝 输入文章内容', 'linked3'); ?></label>
        <textarea id="summary-input" class="linked3-eco-input" rows="8" style="width:100%;font-size:13px;line-height:1.6;" placeholder="<?php echo esc_attr__('粘贴文章内容, AI将自动生成摘要...', 'linked3'); ?>"></textarea>
        <div style="font-size:11px;color:#A1A1AA;margin-top:4px;font-variant-numeric:tabular-nums;"><?php echo esc_html__('字数:', 'linked3'); ?><span id="summary-input-count">0</span></div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <label class="lk3-form-label" style="margin:0;"><?php echo esc_html__('长度:', 'linked3'); ?></label>
        <select id="summary-length" class="linked3-eco-select" style="width:100px;">
            <option value="short"><?php echo esc_html__('短 (50字)', 'linked3'); ?></option>
            <option value="medium" selected><?php echo esc_html__('中 (150字)', 'linked3'); ?></option>
            <option value="long"><?php echo esc_html__('长 (300字)', 'linked3'); ?></option>
        </select>
        <label class="lk3-form-label" style="margin:0 0 0 8px;"><?php echo esc_html__('语气:', 'linked3'); ?></label>
        <select id="summary-tone" class="linked3-eco-select" style="width:100px;">
            <option value="professional" selected><?php echo esc_html__('专业', 'linked3'); ?></option>
            <option value="casual"><?php echo esc_html__('轻松', 'linked3'); ?></option>
            <option value="academic"><?php echo esc_html__('学术', 'linked3'); ?></option>
        </select>
        <label class="lk3-form-label" style="margin:0 0 0 8px;"><?php echo esc_html__('格式:', 'linked3'); ?></label>
        <select id="summary-format" class="linked3-eco-select" style="width:120px;">
            <option value="paragraph" selected><?php echo esc_html__('段落式', 'linked3'); ?></option>
            <option value="tldr">TL;DR</option>
            <option value="bullets"><?php echo esc_html__('要点列表', 'linked3'); ?></option>
        </select>
        <button class="linked3-eco-btn" id="summary-generate"><?php echo esc_html__('📄 生成摘要', 'linked3'); ?></button>
    </div>

    <div id="summary-result" style="display:none;">
        <div style="padding:16px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <strong style="font-size:13px;color:#18181B;"><?php echo esc_html__('生成结果', 'linked3'); ?></strong>
                <div style="display:flex;gap:6px;">
                    <button class="linked3-eco-btn linked3-eco-btn-sm" id="summary-copy"><?php echo esc_html__('📋 复制', 'linked3'); ?></button>
                    <button class="linked3-eco-btn linked3-eco-btn-sm" id="summary-regenerate"><?php echo esc_html__('🔄 重生成', 'linked3'); ?></button>
                </div>
            </div>
            <div id="summary-output" style="font-size:13px;color:#27272A;line-height:1.7;white-space:pre-wrap;"></div>
            <div style="font-size:10px;color:#A1A1AA;margin-top:8px;font-variant-numeric:tabular-nums;"><?php echo esc_html__('摘要字数:', 'linked3'); ?><span id="summary-output-count">0</span><?php echo esc_html__('· 压缩比:', 'linked3'); ?><span id="summary-ratio">0</span>%</div>
        </div>
    </div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-summary.js ?>
