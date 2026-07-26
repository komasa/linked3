<?php
/**
 * SEO优化子面板 v12.0 — AI驱动的SEO元数据生成
 *
 * 参照国际顶级规范: Yoast SEO / Rank Math / Surfer SEO / Clearscope
 * 功能: Meta Title / Meta Description / Slug / Focus Keyword / 内容评分
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_seo = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
?>

<div class="linked3-eco-card">
    <h3><?php echo esc_html__('🔍 SEO优化 — AI驱动元数据生成', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        参照 Yoast SEO / Rank Math / Surfer SEO 规范。输入文章内容, AI自动生成 Meta Title、Meta Description、URL Slug、Focus Keyword, 并给出SEO评分建议。
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📝 文章内容 / 标题', 'linked3'); ?></label>
            <textarea id="seo-input-text" class="linked3-eco-input" rows="6" style="width:100%;font-size:13px;line-height:1.6;" placeholder="<?php echo esc_attr__('粘贴文章内容或输入标题, AI将分析并生成SEO元数据...', 'linked3'); ?>"></textarea>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🎯 目标关键词 (可选)', 'linked3'); ?></label>
            <input type="text" id="seo-focus-kw" class="linked3-eco-input" style="width:100%;margin-bottom:12px;" placeholder="<?php echo esc_attr__('如: AI写作工具', 'linked3'); ?>">
            <label class="lk3-form-label"><?php echo esc_html__('🌐 语言', 'linked3'); ?></label>
            <select id="seo-lang" class="linked3-eco-input" style="width:100%;">
                <option value="zh"><?php echo esc_html__('中文', 'linked3'); ?></option>
                <option value="en">English</option>
                <option value="ja"><?php echo esc_html__('日本語', 'linked3'); ?></option>
            </select>
        </div>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;">
        <button class="linked3-eco-btn" id="seo-generate" data-nonce="<?php echo esc_attr($nonce_seo); ?>">🔍 生成SEO元数据</button>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="seo-analyze"><?php echo esc_html__('📊 SEO评分分析', 'linked3'); ?></button>
    </div>

    <div id="seo-result" style="display:none;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
                <label style="font-size:11px;font-weight:600;color:#71717A;text-transform:uppercase;letter-spacing:0.05em;">Meta Title</label>
                <input type="text" id="seo-meta-title" class="linked3-eco-input" style="width:100%;margin-top:4px;" placeholder="<?php echo esc_attr__('AI生成的Meta Title...', 'linked3'); ?>">
                <div style="font-size:10px;color:#A1A1AA;margin-top:4px;font-variant-numeric:tabular-nums;"><?php echo esc_html__('字符数:', 'linked3'); ?><span id="seo-title-count">0</span>/60</div>
            </div>
            <div style="padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
                <label style="font-size:11px;font-weight:600;color:#71717A;text-transform:uppercase;letter-spacing:0.05em;">Meta Description</label>
                <textarea id="seo-meta-desc" class="linked3-eco-input" rows="2" style="width:100%;margin-top:4px;" placeholder="<?php echo esc_attr__('AI生成的Meta Description...', 'linked3'); ?>"></textarea>
                <div style="font-size:10px;color:#A1A1AA;margin-top:4px;font-variant-numeric:tabular-nums;"><?php echo esc_html__('字符数:', 'linked3'); ?><span id="seo-desc-count">0</span>/160</div>
            </div>
            <div style="padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
                <label style="font-size:11px;font-weight:600;color:#71717A;text-transform:uppercase;letter-spacing:0.05em;">URL Slug</label>
                <input type="text" id="seo-slug" class="linked3-eco-input" style="width:100%;margin-top:4px;" placeholder="ai-writing-tool-guide">
            </div>
            <div style="padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
                <label style="font-size:11px;font-weight:600;color:#71717A;text-transform:uppercase;letter-spacing:0.05em;">Focus Keywords</label>
                <input type="text" id="seo-keywords" class="linked3-eco-input" style="width:100%;margin-top:4px;" placeholder="<?php echo esc_attr__('AI写作, 内容生成, 自动化', 'linked3'); ?>">
            </div>
        </div>
        <div id="seo-score-panel" style="margin-top:12px;padding:12px;border:1px solid #E4E4E7;border-radius:6px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div id="seo-score-circle" style="width:48px;height:48px;border-radius:50%;border:3px solid #10B981;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#10B981;font-variant-numeric:tabular-nums;">--</div>
                <div>
                    <strong style="font-size:13px;color:#18181B;"><?php echo esc_html__('SEO评分', 'linked3'); ?></strong>
                    <div id="seo-score-details" style="font-size:11px;color:#71717A;"><?php echo esc_html__('等待分析...', 'linked3'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-seo.js ?>
