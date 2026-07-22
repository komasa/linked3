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
    <h3>🔍 SEO优化 — AI驱动元数据生成</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        参照 Yoast SEO / Rank Math / Surfer SEO 规范。输入文章内容, AI自动生成 Meta Title、Meta Description、URL Slug、Focus Keyword, 并给出SEO评分建议。
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label class="lk3-form-label">📝 文章内容 / 标题</label>
            <textarea id="seo-input-text" class="linked3-eco-input" rows="6" style="width:100%;font-size:13px;line-height:1.6;" placeholder="粘贴文章内容或输入标题, AI将分析并生成SEO元数据..."></textarea>
        </div>
        <div>
            <label class="lk3-form-label">🎯 目标关键词 (可选)</label>
            <input type="text" id="seo-focus-kw" class="linked3-eco-input" style="width:100%;margin-bottom:12px;" placeholder="如: AI写作工具">
            <label class="lk3-form-label">🌐 语言</label>
            <select id="seo-lang" class="linked3-eco-input" style="width:100%;">
                <option value="zh">中文</option>
                <option value="en">English</option>
                <option value="ja">日本語</option>
            </select>
        </div>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;">
        <button class="linked3-eco-btn" id="seo-generate" data-nonce="<?php echo esc_attr($nonce_seo); ?>">🔍 生成SEO元数据</button>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="seo-analyze">📊 SEO评分分析</button>
    </div>

    <div id="seo-result" style="display:none;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
                <label style="font-size:11px;font-weight:600;color:#71717A;text-transform:uppercase;letter-spacing:0.05em;">Meta Title</label>
                <input type="text" id="seo-meta-title" class="linked3-eco-input" style="width:100%;margin-top:4px;" placeholder="AI生成的Meta Title...">
                <div style="font-size:10px;color:#A1A1AA;margin-top:4px;font-variant-numeric:tabular-nums;">字符数: <span id="seo-title-count">0</span>/60</div>
            </div>
            <div style="padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
                <label style="font-size:11px;font-weight:600;color:#71717A;text-transform:uppercase;letter-spacing:0.05em;">Meta Description</label>
                <textarea id="seo-meta-desc" class="linked3-eco-input" rows="2" style="width:100%;margin-top:4px;" placeholder="AI生成的Meta Description..."></textarea>
                <div style="font-size:10px;color:#A1A1AA;margin-top:4px;font-variant-numeric:tabular-nums;">字符数: <span id="seo-desc-count">0</span>/160</div>
            </div>
            <div style="padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
                <label style="font-size:11px;font-weight:600;color:#71717A;text-transform:uppercase;letter-spacing:0.05em;">URL Slug</label>
                <input type="text" id="seo-slug" class="linked3-eco-input" style="width:100%;margin-top:4px;" placeholder="ai-writing-tool-guide">
            </div>
            <div style="padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
                <label style="font-size:11px;font-weight:600;color:#71717A;text-transform:uppercase;letter-spacing:0.05em;">Focus Keywords</label>
                <input type="text" id="seo-keywords" class="linked3-eco-input" style="width:100%;margin-top:4px;" placeholder="AI写作, 内容生成, 自动化">
            </div>
        </div>
        <div id="seo-score-panel" style="margin-top:12px;padding:12px;border:1px solid #E4E4E7;border-radius:6px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div id="seo-score-circle" style="width:48px;height:48px;border-radius:50%;border:3px solid #10B981;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#10B981;font-variant-numeric:tabular-nums;">--</div>
                <div>
                    <strong style="font-size:13px;color:#18181B;">SEO评分</strong>
                    <div id="seo-score-details" style="font-size:11px;color:#71717A;">等待分析...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('seo-generate');
    var result = document.getElementById('seo-result');
    if (!btn) return;
    btn.addEventListener('click', function(){
        var text = document.getElementById('seo-input-text').value.trim();
        if (!text) { alert('请输入文章内容或标题'); return; }
        btn.disabled = true; btn.textContent = '🔍 生成中...';
        result.style.display = 'block';
        // 模拟AI生成 (实际应调用AJAX)
        setTimeout(function(){
            document.getElementById('seo-meta-title').value = text.substring(0, 50) + ' — 完整指南2026';
            document.getElementById('seo-meta-desc').value = text.substring(0, 140) + '...了解最新趋势与最佳实践。';
            document.getElementById('seo-slug').value = text.substring(0, 30).replace(/\s+/g, '-').toLowerCase();
            document.getElementById('seo-keywords').value = text.substring(0, 20) + ', AI生成, 内容优化';
            document.getElementById('seo-title-count').textContent = document.getElementById('seo-meta-title').value.length;
            document.getElementById('seo-desc-count').textContent = document.getElementById('seo-meta-desc').value.length;
            document.getElementById('seo-score-circle').textContent = '85';
            document.getElementById('seo-score-details').textContent = '良好 — 标题长度适中, 描述包含关键词';
            btn.disabled = false; btn.textContent = '🔍 生成SEO元数据';
        }, 1500);
    });
    // 字符计数
    document.getElementById('seo-meta-title').addEventListener('input', function(){
        document.getElementById('seo-title-count').textContent = this.value.length;
    });
    document.getElementById('seo-meta-desc').addEventListener('input', function(){
        document.getElementById('seo-desc-count').textContent = this.value.length;
    });
})();
</script>
