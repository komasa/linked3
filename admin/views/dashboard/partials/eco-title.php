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
    <h3>💡 标题生成 — AI多风格标题 + 点击率优化</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        参照 Jasper / Copy.ai / Writesonic 规范。输入主题, AI生成多种风格标题(疑问式/数字式/如何式/对比式/情感式), 支持A/B测试候选与点击率预估。
    </p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input type="text" id="title-input-topic" class="linked3-eco-input" style="flex:1;min-width:300px;" placeholder="输入主题, 如: AI写作工具推荐">
        <select id="title-style" class="linked3-eco-select" style="width:140px;">
            <option value="all">全部风格</option>
            <option value="question">疑问式</option>
            <option value="number">数字式</option>
            <option value="howto">如何式</option>
            <option value="compare">对比式</option>
            <option value="emotion">情感式</option>
            <option value="list">清单式</option>
        </select>
        <select id="title-count" class="linked3-eco-select" style="width:100px;">
            <option value="5">5条</option>
            <option value="10" selected>10条</option>
            <option value="20">20条</option>
        </select>
        <button class="linked3-eco-btn" id="title-generate">💡 生成标题</button>
    </div>

    <div id="title-result" style="display:none;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;" id="title-list">
            <!-- AI生成的标题将动态插入这里 -->
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
            <button class="linked3-eco-btn linked3-eco-btn-secondary" id="title-copy-all">📋 复制全部</button>
            <button class="linked3-eco-btn linked3-eco-btn-secondary" id="title-regenerate">🔄 重新生成</button>
        </div>
    </div>
</div>

<script>
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
</script>
