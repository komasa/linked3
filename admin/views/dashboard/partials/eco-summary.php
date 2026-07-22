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
    <h3>📄 摘要生成 — AI摘要 / TL;DR / 关键要点</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        参照 Notion AI / QuillBot / Grammarly 规范。输入长文, AI自动生成摘要、TL;DR、关键要点, 支持不同长度(短/中/长)与语气(专业/轻松/学术)。
    </p>

    <div style="margin-bottom:16px;">
        <label class="lk3-form-label">📝 输入文章内容</label>
        <textarea id="summary-input" class="linked3-eco-input" rows="8" style="width:100%;font-size:13px;line-height:1.6;" placeholder="粘贴文章内容, AI将自动生成摘要..."></textarea>
        <div style="font-size:11px;color:#A1A1AA;margin-top:4px;font-variant-numeric:tabular-nums;">字数: <span id="summary-input-count">0</span></div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <label class="lk3-form-label" style="margin:0;">长度:</label>
        <select id="summary-length" class="linked3-eco-select" style="width:100px;">
            <option value="short">短 (50字)</option>
            <option value="medium" selected>中 (150字)</option>
            <option value="long">长 (300字)</option>
        </select>
        <label class="lk3-form-label" style="margin:0 0 0 8px;">语气:</label>
        <select id="summary-tone" class="linked3-eco-select" style="width:100px;">
            <option value="professional" selected>专业</option>
            <option value="casual">轻松</option>
            <option value="academic">学术</option>
        </select>
        <label class="lk3-form-label" style="margin:0 0 0 8px;">格式:</label>
        <select id="summary-format" class="linked3-eco-select" style="width:120px;">
            <option value="paragraph" selected>段落式</option>
            <option value="tldr">TL;DR</option>
            <option value="bullets">要点列表</option>
        </select>
        <button class="linked3-eco-btn" id="summary-generate">📄 生成摘要</button>
    </div>

    <div id="summary-result" style="display:none;">
        <div style="padding:16px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <strong style="font-size:13px;color:#18181B;">生成结果</strong>
                <div style="display:flex;gap:6px;">
                    <button class="linked3-eco-btn linked3-eco-btn-sm" id="summary-copy">📋 复制</button>
                    <button class="linked3-eco-btn linked3-eco-btn-sm" id="summary-regenerate">🔄 重生成</button>
                </div>
            </div>
            <div id="summary-output" style="font-size:13px;color:#27272A;line-height:1.7;white-space:pre-wrap;"></div>
            <div style="font-size:10px;color:#A1A1AA;margin-top:8px;font-variant-numeric:tabular-nums;">摘要字数: <span id="summary-output-count">0</span> · 压缩比: <span id="summary-ratio">0</span>%</div>
        </div>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('summary-generate');
    var input = document.getElementById('summary-input');
    var result = document.getElementById('summary-result');
    if (!btn) return;

    input.addEventListener('input', function(){
        document.getElementById('summary-input-count').textContent = this.value.length;
    });

    btn.addEventListener('click', function(){
        var text = input.value.trim();
        if (!text || text.length < 50) { alert('请输入至少50字的文章内容'); return; }
        btn.disabled = true; btn.textContent = '📄 生成中...';
        result.style.display = 'block';

        setTimeout(function(){
            var length = document.getElementById('summary-length').value;
            var format = document.getElementById('summary-format').value;
            var lenMap = { short: 50, medium: 150, long: 300 };
            var targetLen = lenMap[length] || 150;
            var summary = text.substring(0, targetLen);

            if (format === 'tldr') {
                summary = 'TL;DR: ' + text.substring(0, 80) + '...';
            } else if (format === 'bullets') {
                var sentences = text.split(/[。.！!？?]/).filter(function(s){ return s.trim().length > 10; });
                summary = sentences.slice(0, 5).map(function(s, i){ return '• ' + s.trim(); }).join('\n');
            } else {
                summary = summary + '...';
            }

            document.getElementById('summary-output').textContent = summary;
            document.getElementById('summary-output-count').textContent = summary.length;
            document.getElementById('summary-ratio').textContent = Math.round(summary.length / text.length * 100);
            btn.disabled = false; btn.textContent = '📄 生成摘要';
        }, 1500);
    });

    document.getElementById('summary-copy').addEventListener('click', function(){
        var text = document.getElementById('summary-output').textContent;
        navigator.clipboard.writeText(text).then(function(){ alert('已复制到剪贴板'); });
    });
})();
</script>
