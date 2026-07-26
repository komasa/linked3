/**
 * linked3-eco-xhs.js
 * Extracted from: admin/views/dashboard/partials/eco-xhs.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-xhs.js
 * Localized via wp_localize_script('linked3-eco-xhs', 'linked3_eco_xhs', {...})
 *   Keys: ajax_url, nonce_xhs
 */

(function(){
    var ajax_url = window.linked3_eco_xhs && window.linked3_eco_xhs.ajax_url || '';
    var nonce_xhs = window.linked3_eco_xhs && window.linked3_eco_xhs.nonce_xhs || '';


(function() {
    var btn = document.getElementById('xhs-generate-btn');
    var loading = document.getElementById('xhs-loading');
    var result = document.getElementById('xhs-result');
    var errorDiv = document.getElementById('xhs-error');
    var errorMsg = document.getElementById('xhs-error-msg');

    btn.addEventListener('click', function() {
        var topic = document.getElementById('xhs-topic').value.trim();
        if (!topic) { alert('请输入主题'); return; }

        btn.disabled = true;
        btn.textContent = '⏳ 生成中...';
        loading.style.display = 'block';
        result.style.display = 'none';
        errorDiv.style.display = 'none';

        fetch('linked3_eco_xhs.ajax_url', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'linked3_xhs_generate',
                nonce: 'linked3_eco_xhs.nonce_xhs',
                topic: topic,
                keyword: document.getElementById('xhs-keyword').value,
                style: document.getElementById('xhs-style').value,
                page_count: document.getElementById('xhs-page-count').value,
                model: document.getElementById('xhs-model').value,
                custom_style: document.getElementById('xhs-custom-style').value
            })
        })
        .then(function(r) {
            // v19.2.1 前端加固：先读 text，再尝试 JSON 解析。
            // 这样当 WP fatal handler 输出 "<p>There has been a critical error...</p>"
            // 时，能给用户一条可读的错误，而不是 "Unexpected token '<'"。
            return r.text().then(function(text) {
                var trimmed = (text || '').trim();
                if (!trimmed) {
                    throw new Error('服务器返回空响应，请检查 PHP 错误日志。');
                }
                // 不是 JSON 开头（{ 或 [）→ 一定是 HTML 错误页
                if (trimmed[0] !== '{' && trimmed[0] !== '[') {
                    // 提取 <p>...</p> 或第一行作为错误信息
                    var m = trimmed.match(/<p>([^<]+)<\/p>/i);
                    var msg = m ? m[1] : trimmed.split('\n')[0].slice(0, 120);
                    throw new Error('服务器错误: ' + msg);
                }
                try {
                    return JSON.parse(trimmed);
                } catch (e) {
                    throw new Error('响应解析失败: ' + e.message);
                }
            });
        })
        .then(function(json) {
            if (!json || !json.success) {
                throw new Error((json && json.data && json.data.message) || '生成失败');
            }
            var data = json.data || {};
            document.getElementById('xhs-result-title').textContent = data.title || '';
            document.getElementById('xhs-result-content').textContent = data.main_content || '';

            // Tags
            var tagsHtml = '';
            (data.tags || []).forEach(function(tag) {
                tagsHtml += '<span style="display:inline-block;background:#fff0f0;color:#ff2e4d;padding:2px 10px;border-radius:12px;font-size:12px;margin-right:6px;">' + tag + '</span>';
            });
            document.getElementById('xhs-result-tags').innerHTML = tagsHtml;

            // Pages
            var pagesHtml = '';
            (data.pages || []).forEach(function(page, idx) {
                var isCover = page.is_cover;
                pagesHtml += '<div style="border:1px solid #ececec;border-radius:12px;overflow:hidden;background:#fff;">';
                pagesHtml += '<div style="padding:12px;">';
                pagesHtml += '<div style="font-size:14px;font-weight:600;margin-bottom:6px;">' + (isCover ? '🔥 ' : '📄 ') + (page.title || ('第' + (idx+1) + '页')) + '</div>';
                pagesHtml += '<div style="font-size:13px;color:#555;margin-bottom:8px;white-space:pre-wrap;">' + (page.content || '') + '</div>';
                pagesHtml += '<div style="background:#f5f5f7;padding:8px;border-radius:8px;font-size:12px;color:#888;">🎨 ' + (page.image_prompt || '(无配图提示词)') + '</div>';
                pagesHtml += '</div></div>';
            });
            document.getElementById('xhs-result-pages').innerHTML = pagesHtml;

            result.style.display = 'block';
        })
        .catch(function(err) {
            errorMsg.textContent = err.message || '生成失败';
            errorDiv.style.display = 'block';
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = '📕 生成小红书图文';
            loading.style.display = 'none';
        });
    });
})();

})();
