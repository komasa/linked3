/**
 * Linked3 Eco Keywords JS
 * Extracted from: admin/views/dashboard/partials/eco-keywords.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-keywords.js
 * Localized via wp_localize_script('linked3-eco-keywords', 'linked3_eco_keywords', {...})
 *   Keys: saved_tail_used, ajax_url, nonce, csv_url, synergy_url, synergy_url
 */

(function(){
    var ajaxUrl = 'window.linked3_eco_keywords.ajax_url';
    var nonce = 'window.linked3_eco_keywords.nonce';
    var synergyUrl = 'window.linked3_eco_keywords.synergy_url';

    function escHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function updateCount() {
        var hotLines = document.getElementById('kw-hot-list').value.split('\n').filter(function(s){return s.trim();});
        var tailLines = document.getElementById('kw-tail-list').value.split('\n').filter(function(s){return s.trim();});
        document.getElementById('kw-hot-count').textContent = '(' + hotLines.length + '个, 自动保存)';
        document.getElementById('kw-tail-count').textContent = '(' + tailLines.length + '个, 自动保存)';
    }

    function saveKeywordLib() {
        // v16.0.24修复: 分别保存热词库和长尾词库 (原代码未传type, 导致长尾词永远存到hot_keywords, 刷新后丢失)
        var hot = document.getElementById('kw-hot-list').value;
        var tail = document.getElementById('kw-tail-list').value;

        // 保存热词库
        var fdHot = new FormData();
        fdHot.append('action', 'linked3_eco_keywords_save');
        fdHot.append('nonce', nonce);
        fdHot.append('type', 'hot');
        fdHot.append('keywords', hot);
        fetch(ajaxUrl, {method:'POST', body:fdHot, credentials:'same-origin'}).then(function(r){return r.json();}).catch(function(){});

        // 保存长尾词库
        var fdTail = new FormData();
        fdTail.append('action', 'linked3_eco_keywords_save');
        fdTail.append('nonce', nonce);
        fdTail.append('type', 'tail');
        fdTail.append('keywords', tail);
        fetch(ajaxUrl, {method:'POST', body:fdTail, credentials:'same-origin'}).then(function(r){return r.json();}).catch(function(){});
    }

    document.addEventListener('DOMContentLoaded', function(){
        updateCount();

        // 热词库/长尾词库自动保存
        var saveTimer;
        ['kw-hot-list', 'kw-tail-list'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function(){
                    updateCount();
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(saveKeywordLib, 1500);
                });
            }
        });

        // 采集热词
        var fetchBtn = document.getElementById('kw-fetch-hot');
        if (fetchBtn) {
            fetchBtn.addEventListener('click', function(){
                var source = document.getElementById('kw-source').value;
                var seed = document.getElementById('kw-seed').value.trim();
                fetchBtn.disabled = true;
                fetchBtn.textContent = '采集中...';

                var fd = new FormData();
                fd.append('action', 'linked3_eco_hot_collect');
                fd.append('nonce', nonce);
                fd.append('source', source);
                fd.append('seed', seed);

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data){
                        fetchBtn.disabled = false;
                        fetchBtn.textContent = '🔥 采集热词';
                        // v11.0.2 #2: 修复字段名 hot_words (原代码误用 keywords)
                        if (data.success && (data.data.hot_words || data.data.keywords)) {
                            var newKw = data.data.hot_words || data.data.keywords || [];
                            var existing = document.getElementById('kw-hot-list').value.trim();
                            var combined = existing ? existing + '\n' + newKw.join('\n') : newKw.join('\n');
                            document.getElementById('kw-hot-list').value = combined;
                            updateCount();
                            saveKeywordLib();
                            // v11.0.2 #2: 显示具体采集到的热词 (不只是"采集成功")
                            var previewHtml = '<div class="notice notice-success inline"><p>✅ 采集到 ' + newKw.length + ' 个热词 (来源: ' + escHtml(source) + ')</p>';
                            previewHtml += '<details style="margin-top:6px;"><summary style="cursor:pointer;font-size:12px;">查看热词列表</summary><div style="background:#f9fafb;padding:8px;border-radius:4px;margin-top:4px;font-size:12px;line-height:1.8;">';
                            newKw.forEach(function(kw, i){ previewHtml += '<span style="display:inline-block;background:#F4F4F5;color:#0F172A;padding:2px 8px;border-radius:3px;margin:2px;">' + escHtml(kw) + '</span>'; });
                            previewHtml += '</div></details></div>';
                            document.getElementById('kw-result').innerHTML = previewHtml;
                        } else {
                            document.getElementById('kw-result').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '采集失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        fetchBtn.disabled = false;
                        fetchBtn.textContent = '🔥 采集热词';
                        document.getElementById('kw-result').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
            });
        }

        // AI生成长尾词
        var genBtn = document.getElementById('kw-generate');
        if (genBtn) {
            genBtn.addEventListener('click', function(){
                generateKeywords('single');
            });
        }

        // v17.2.0 R1: 用全部热词批量生成长尾词
        var genMultiBtn = document.getElementById('kw-generate-multi');
        if (genMultiBtn) {
            genMultiBtn.addEventListener('click', function(){
                generateKeywords('multi');
            });
        }

        // v17.2.0 R1: 统一关键词生成函数 (支持single/multi模式)
        function generateKeywords(mode) {
            var seedInput = document.getElementById('kw-seed').value.trim();
            var hotListRaw = document.getElementById('kw-hot-list').value;
            var hotLines = hotListRaw ? hotListRaw.split('\n').map(function(s){return s.trim();}).filter(function(s){return s;}) : [];
            var hotFirst = hotLines[0] || '';
            var seed = seedInput || hotFirst || '';
            var count = parseInt(document.getElementById('kw-count').value) || 20;
            var append = document.getElementById('kw-append').checked;

            if (mode === 'multi') {
                if (hotLines.length === 0) {
                    alert('热词库为空, 请先采集热词或手动输入热词后再用"全部热词批量生成"');
                    return;
                }
            } else {
                if (!seed) {
                    alert('请输入种子词, 或先采集热词后从热词库选一个作为种子。');
                    return;
                }
            }

            var btn = mode === 'multi' ? genMultiBtn : genBtn;
            btn.disabled = true;
            btn.textContent = '生成中...';

            var fd = new FormData();
            fd.append('action', 'linked3_eco_keywords');
            fd.append('nonce', nonce);
            fd.append('count', count);
            fd.append('mode', mode);
            if (mode === 'multi') {
                fd.append('seeds', hotLines.join('\n'));
            } else {
                fd.append('seed', seed);
            }

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data){
                        btn.disabled = false;
                        btn.textContent = mode === 'multi' ? '🔥 用全部热词批量生成' : '🔑 单种子生成长尾词';
                        if (data.success && data.data.keywords) {
                            var kw = data.data.keywords;
                            var classified = data.data.classified || {};
                            var primary = classified.primary || [];
                            var longTail = classified.long_tail || [];
                            var question = classified.question || [];

                            // 追加到长尾词库
                            if (append) {
                                var existing = document.getElementById('kw-tail-list').value.trim();
                                var combined = existing ? existing + '\n' + kw.join('\n') : kw.join('\n');
                                document.getElementById('kw-tail-list').value = combined;
                                updateCount();
                                saveKeywordLib();
                            }

                            // 三维度分类展示
                            var html = '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px;">';
                            html += '<div style="background:#F4F4F5;padding:8px;border-radius:4px;"><strong>主词 (' + primary.length + ')</strong><div style="margin-top:4px;font-size:12px;">' + (primary.slice(0,5).map(escHtml).join(', ') || '无') + '</div></div>';
                            html += '<div style="background:#dcfce7;padding:8px;border-radius:4px;"><strong>长尾 (' + longTail.length + ')</strong><div style="margin-top:4px;font-size:12px;">' + (longTail.slice(0,5).map(escHtml).join(', ') || '无') + '</div></div>';
                            html += '<div style="background:#FEF3C7;padding:8px;border-radius:4px;"><strong>疑问 (' + question.length + ')</strong><div style="margin-top:4px;font-size:12px;">' + (question.slice(0,5).map(escHtml).join(', ') || '无') + '</div></div>';
                            html += '</div>';
                            html += '<div style="background:#f9fafb;padding:8px;border-radius:4px;margin-top:8px;"><strong>全部关键词 (' + kw.length + ')</strong><div style="margin-top:4px;font-size:12px;">' + kw.map(escHtml).join(', ') + '</div></div>';
                            html += '<div style="margin-top:8px;"><a class="linked3-eco-btn linked3-eco-btn-secondary" style="display:inline-block;text-decoration:none;" href="' + synergyUrl + '&topic=' + encodeURIComponent(seed) + '">→ 送入生态生产</a></div>';
                            document.getElementById('kw-result').innerHTML = html;
                        } else {
                            document.getElementById('kw-result').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '生成失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        btn.disabled = false;
                        btn.textContent = mode === 'multi' ? '🔥 用全部热词批量生成' : '🔑 单种子生成长尾词';
                        document.getElementById('kw-result').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
        }

        // v17.2.0 R2: 长尾词库导出
        var tailExport = document.getElementById('kw-tail-export');
        if (tailExport) {
            tailExport.addEventListener('click', function(){
                var tailList = document.getElementById('kw-tail-list').value.trim();
                if (!tailList) { alert('长尾词库为空'); return; }
                var blob = new Blob([tailList], {type:'text/plain;charset=utf-8'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'linked3-tail-keywords-' + Date.now() + '.txt';
                a.click();
                setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
            });
        }

        // v17.2.0 R2: 长尾词库清空
        var tailClear = document.getElementById('kw-tail-clear');
        if (tailClear) {
            tailClear.addEventListener('click', function(){
                if (!confirm('确定清空长尾词库? 此操作不可撤销。')) return;
                document.getElementById('kw-tail-list').value = '';
                updateCount();
                saveKeywordLib();
                alert('✅ 长尾词库已清空');
            });
        }

        // 定时任务启用
        var cronEnable = document.getElementById('kw-cron-enable');
        if (cronEnable) {
            cronEnable.addEventListener('click', function(){
                var freq = document.getElementById('kw-cron-freq').value;
                var count = document.getElementById('kw-cron-count').value;
                var fd = new FormData();
                fd.append('action', 'linked3_eco_cron_enable');
                fd.append('nonce', nonce);
                fd.append('freq', freq);
                fd.append('count', count);

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        document.getElementById('kw-cron-status').innerHTML =
                            '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data && data.data.message ? data.data.message : '定时任务已启用') + '</p></div>';
                    });
            });
        }

        // 定时任务禁用
        var cronDisable = document.getElementById('kw-cron-disable');
        if (cronDisable) {
            cronDisable.addEventListener('click', function(){
                var fd = new FormData();
                fd.append('action', 'linked3_eco_cron_disable');
                fd.append('nonce', nonce);
                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        document.getElementById('kw-cron-status').innerHTML =
                            '<div class="notice notice-info inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '定时任务已禁用') + '</p></div>';
                    });
            });
        }

        // v11.0.6 #7: 长尾词库 → 生成文章入口
        // v17.2.0 R4: 修复路由 — 旧tab=creation&cr_sub=ecosystem改为tab=creation&cr_sub=ecosystem
        var csvBtn = document.getElementById('kw-to-csv-batch');
        if (csvBtn) {
            csvBtn.addEventListener('click', function(){
                var tailList = document.getElementById('kw-tail-list').value.trim();
                if (!tailList) { alert('长尾词库为空, 请先生成长尾词'); return; }
                var topics = tailList.split('\n').map(function(s){return s.trim();}).filter(function(s){return s;}).join('\n');
                var baseUrl = 'window.linked3_eco_keywords.csv_url';
                window.location.href = baseUrl + '&tail_topics=' + encodeURIComponent(topics);
            });
        }
        var synBtn = document.getElementById('kw-to-synergy');
        if (synBtn) {
            synBtn.addEventListener('click', function(){
                var tailList = document.getElementById('kw-tail-list').value.trim();
                if (!tailList) { alert('长尾词库为空, 请先生成长尾词'); return; }
                var firstTail = tailList.split('\n').map(function(s){return s.trim();}).filter(function(s){return s;})[0];
                if (!firstTail) { alert('长尾词库为空'); return; }
                var baseUrl = 'window.linked3_eco_keywords.synergy_url';
                window.location.href = baseUrl + '&topic=' + encodeURIComponent(firstTail);
                // v16.0.14: 标记该长尾词为已使用
                markTailUsed(firstTail);
            });
        }

        // v16.0.14 [公理α/β]: 长尾词使用状态管理 — 自动持久化 + 徽章更新
        var tailUsedMap = window.linked3_eco_keywords.saved_tail_used;

        function updateTailUsedBadges() {
            var tailLines = document.getElementById('kw-tail-list').value.split('\n')
                .map(function(s){return s.trim();}).filter(function(s){return s;});
            var total = tailLines.length;
            var used = 0;
            tailLines.forEach(function(kw){
                if (tailUsedMap[kw]) used++;
            });
            var usedEl = document.getElementById('kw-tail-used-count');
            var unusedEl = document.getElementById('kw-tail-unused-count');
            if (usedEl) usedEl.textContent = '已用 ' + used;
            if (unusedEl) unusedEl.textContent = '未用 ' + Math.max(0, total - used);
            // 预览前5个未用词
            var previewEl = document.getElementById('kw-tail-status-preview');
            if (previewEl) {
                var unused = tailLines.filter(function(kw){return !tailUsedMap[kw];}).slice(0, 5);
                if (unused.length > 0) {
                    previewEl.innerHTML = '📌 待用词 (前5): ' + unused.map(escHtml).join(' · ');
                } else if (total > 0) {
                    previewEl.innerHTML = '<span style="color:#16a34a;">✓ 全部长尾词已使用</span>';
                }
            }
        }

        function markTailUsed(keyword) {
            if (!keyword) return;
            tailUsedMap[keyword] = 1;
            saveTailUsed();
            updateTailUsedBadges();
        }

        function saveTailUsed() {
            var fd = new FormData();
            fd.append('action', 'linked3_eco_tail_used_save');
            fd.append('nonce', nonce);
            fd.append('used_map', JSON.stringify(tailUsedMap));
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'}).catch(function(){});
        }

        // CSV批量也标记已用
        if (csvBtn) {
            csvBtn.addEventListener('click', function(){
                var tailList = document.getElementById('kw-tail-list').value.trim();
                if (!tailList) return;
                tailList.split('\n').forEach(function(s){
                    var kw = s.trim();
                    if (kw) tailUsedMap[kw] = 1;
                });
                saveTailUsed();
            }, true); // capture phase, 先于原 handler 执行
        }

        // 重置使用状态
        var resetBtn = document.getElementById('kw-tail-reset-used');
        if (resetBtn) {
            resetBtn.addEventListener('click', function(){
                if (!confirm('确认重置所有长尾词的使用状态？此操作不可撤销。')) return;
                tailUsedMap = {};
                saveTailUsed();
                updateTailUsedBadges();
            });
        }

        updateTailUsedBadges();
    });
})();
