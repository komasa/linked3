/**
 * linked3-eco-summary.js
 * Extracted from: admin/views/dashboard/partials/eco-summary.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-summary.js
 */


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
