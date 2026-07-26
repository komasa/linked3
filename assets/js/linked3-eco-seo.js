/**
 * linked3-eco-seo.js
 * Extracted from: admin/views/dashboard/partials/eco-seo.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-seo.js
 */


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
