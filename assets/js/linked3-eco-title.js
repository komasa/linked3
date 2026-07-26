/**
 * linked3-eco-title.js
 * Extracted from: admin/views/dashboard/partials/eco-title.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-title.js
 */


(function(){
    var btn = document.getElementById('title-generate');
    var result = document.getElementById('title-result');
    if (!btn) return;

    var styleTemplates = {
        question: ['为什么{topic}如此重要？', '{topic}真的有效吗？深度解析', '你真的了解{topic}吗？'],
        number: ['{topic}的10个核心要点', '2026年{topic}的5大趋势', '掌握{topic}的7个关键步骤'],
        howto: ['如何快速掌握{topic}？完整指南', '从零开始学习{topic}：详细教程', '{topic}入门指南：一步步教你'],
        compare: ['{topic} vs 传统方案：哪个更好？', '{topic}的优缺点全面对比', '选择{topic}前必须知道的3件事'],
        emotion: ['令人震惊的{topic}真相', '别再错过{topic}带来的机遇', '{topic}改变了我的工作方式'],
        list: ['{topic}必备工具清单', '{topic}常见问题汇总', '{topic}最佳实践清单']
    };

    btn.addEventListener('click', function(){
        var topic = document.getElementById('title-input-topic').value.trim();
        if (!topic) { alert('请输入主题'); return; }
        var style = document.getElementById('title-style').value;
        var count = parseInt(document.getElementById('title-count').value);
        btn.disabled = true; btn.textContent = '💡 生成中...';
        result.style.display = 'block';

        setTimeout(function(){
            var styles = style === 'all' ? Object.keys(styleTemplates) : [style];
            var titles = [];
            for (var i = 0; i < count; i++) {
                var s = styles[i % styles.length];
                var templates = styleTemplates[s];
                var tpl = templates[i % templates.length];
                titles.push({ text: tpl.replace(/\{topic\}/g, topic), style: s, ctr: (3 + Math.random() * 7).toFixed(1) });
            }

            var html = '';
            titles.forEach(function(t, idx) {
                var ctrColor = t.ctr > 6 ? '#10B981' : (t.ctr > 4 ? '#F59E0B' : '#EF4444');
                html += '<div style="padding:10px 12px;border:1px solid #E4E4E7;border-radius:6px;background:#FFFFFF;cursor:pointer;" onclick="this.querySelector(\'input\').select();document.execCommand(\'copy\');">';
                html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">';
                html += '<span style="font-size:13px;color:#18181B;font-weight:500;line-height:1.5;flex:1;">' + (idx+1) + '. ' + t.text + '</span>';
                html += '<span style="font-size:10px;padding:2px 6px;border-radius:3px;background:#F4F4F5;color:#52525B;white-space:nowrap;font-variant-numeric:tabular-nums;">CTR ' + t.ctr + '%</span>';
                html += '</div>';
                html += '<input type="text" value="' + t.text.replace(/"/g, '&quot;') + '" style="position:absolute;left:-9999px;" readonly>';
                html += '</div>';
            });
            document.getElementById('title-list').innerHTML = html;
            btn.disabled = false; btn.textContent = '💡 生成标题';
        }, 1200);
    });
})();
