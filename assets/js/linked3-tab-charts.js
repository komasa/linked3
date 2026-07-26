/**
 * Linked3 Charts Tab JS
 * Extracted from: admin/views/dashboard/partials/tab-charts.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-charts.js
 * Localized via wp_localize_script('linked3-tab-charts', 'linked3_charts', {...})
 *   Keys: ajax_url, nonce, genesis_url, publish_url
 */

(function(){
    'use strict';
    var nonce = 'window.linked3_charts.nonce';
    var ajaxUrl = 'window.linked3_charts.ajax_url';
    var selectedSeedIds = [];

    // 字数统计
    var topicEl = document.getElementById('lk3-charts-topic');
    var statsEl = document.getElementById('lk3-charts-topic-stats');
    if (topicEl && statsEl) {
        topicEl.addEventListener('input', function() {
            statsEl.textContent = topicEl.value.length + ' 字';
        });
    }

    // SEED选择
    var seedPickBtn = document.getElementById('lk3-charts-seed-pick');
    if (seedPickBtn) {
        seedPickBtn.addEventListener('click', function() {
            loadSeedList();
        });
    }

    function loadSeedList() {
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_seed_list');
        fd.append('nonce', nonce);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) return;
                showSeedPicker(res.data.seeds || []);
            });
    }

    function showSeedPicker(seeds) {
        var existing = document.getElementById('lk3-charts-seed-picker-modal');
        if (existing) existing.remove();

        var html = '<div id="lk3-charts-seed-picker-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;">';
        html += '<div style="background:#fff;border-radius:10px;width:90%;max-width:500px;max-height:70vh;overflow-y:auto;padding:20px;">';
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
        html += '<h3 style="margin:0;font-size:16px;">🧬 选择SEED</h3>';
        html += '<button onclick="document.getElementById(\'lk3-charts-seed-picker-modal\').remove()" style="background:none;border:none;font-size:20px;cursor:pointer;">✕</button>';
        html += '</div>';

        if (seeds.length === 0) {
            html += '<div style="text-align:center;padding:30px;color:#71717A;">';
            html += '<p style="font-size:14px;margin:0 0 8px 0;">🧬 暂无SEED</p>';
            html += '<p style="font-size:12px;color:#9ca3af;margin:0 0 12px 0;">SEED是角色/场景/道具等视觉DNA, 需先创建才能生成图示脚本。</p>';
            html += '<a href="' + 'window.linked3_charts.genesis_url' + '" class="button button-primary" target="_blank">→ 去漫画脚本创建SEED</a>';
            html += '</div>';
        } else {
            seeds.forEach(function(s) {
                var checked = selectedSeedIds.indexOf(s.seed_id) >= 0 ? 'checked' : '';
                var catLabel = s.category || s.seed_category || '';
                html += '<label style="display:flex;align-items:center;gap:8px;padding:8px;border-bottom:1px solid #f0f0f0;cursor:pointer;font-size:12px;">';
                html += '<input type="checkbox" class="lk3-charts-seed-checkbox" value="' + escapeHtml(s.seed_id) + '" ' + checked + '>';
                html += '<div style="flex:1;"><strong>' + escapeHtml(s.name || s.seed_id) + '</strong>';
                if (catLabel) html += ' <span style="color:#999;">[' + escapeHtml(catLabel) + ']</span>';
                html += '</div></label>';
            });
        }

        html += '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">';
        html += '<button class="lk3-charts-btn lk3-charts-btn-sm" onclick="document.getElementById(\'lk3-charts-seed-picker-modal\').remove()">取消</button>';
        html += '<button class="lk3-charts-btn lk3-charts-btn-sm lk3-charts-btn-primary" id="lk3-charts-seed-confirm">确认选择</button>';
        html += '</div></div></div>';

        document.body.insertAdjacentHTML('beforeend', html);

        var confirmBtn = document.getElementById('lk3-charts-seed-confirm');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                selectedSeedIds = [];
                document.querySelectorAll('.lk3-charts-seed-checkbox:checked').forEach(function(cb) {
                    selectedSeedIds.push(cb.value);
                });
                document.getElementById('lk3-charts-seed-refs').value = selectedSeedIds.join(',');
                updateSeedSelectedList();
                document.getElementById('lk3-charts-seed-picker-modal').remove();
            });
        }
    }

    function updateSeedSelectedList() {
        var listEl = document.getElementById('lk3-charts-seed-selected-list');
        if (!listEl) return;
        if (selectedSeedIds.length === 0) {
            listEl.innerHTML = '<span style="color:#A1A1AA;font-size:12px;">未选择任何SEED — 点击「从SEED库选择」</span>';
            return;
        }
        var html = '';
        selectedSeedIds.forEach(function(id) {
            html += '<span class="lk3-charts-seed-tag">🧬 ' + escapeHtml(id) + ' <span style="cursor:pointer;color:#DC2626;" onclick="lk3ChartsRemoveSeed(\'' + escapeHtml(id) + '\')">×</span></span>';
        });
        listEl.innerHTML = html;
    }

    window.lk3ChartsRemoveSeed = function(id) {
        var idx = selectedSeedIds.indexOf(id);
        if (idx >= 0) selectedSeedIds.splice(idx, 1);
        document.getElementById('lk3-charts-seed-refs').value = selectedSeedIds.join(',');
        updateSeedSelectedList();
    };

    // 清空SEED
    var seedClearBtn = document.getElementById('lk3-charts-seed-clear');
    if (seedClearBtn) {
        seedClearBtn.addEventListener('click', function() {
            selectedSeedIds = [];
            document.getElementById('lk3-charts-seed-refs').value = '';
            updateSeedSelectedList();
        });
    }

    // 刷新SEED
    var seedRefreshBtn = document.getElementById('lk3-charts-seed-refresh');
    if (seedRefreshBtn) {
        seedRefreshBtn.addEventListener('click', loadSeedList);
    }

    // 生成
    var genBtn = document.getElementById('lk3-charts-gen');
    var spinner = document.getElementById('lk3-charts-spinner');
    var statusEl = document.getElementById('lk3-charts-status');
    var result = document.getElementById('lk3-charts-result');

    if (genBtn) {
        genBtn.addEventListener('click', function() {
            var topic = document.getElementById('lk3-charts-topic').value.trim();
            if (!topic || topic.length < 10) {
                alert('请输入至少10字的主题或内容');
                return;
            }

            genBtn.disabled = true;
            spinner.style.display = 'inline-block';
            statusEl.textContent = 'AI按结构拆分内容...';
            statusEl.style.color = '#2271b1';
            result.innerHTML = '<div style="text-align:center;padding:30px;color:#71717A;"><div class="spinner is-active" style="float:none;margin:0 auto 10px;"></div>正在生成图文脚本...</div>';

            var fd = new FormData();
            fd.append('action', 'linked3_charts_generate_v10');
            fd.append('nonce', nonce);
            fd.append('topic', topic);
            fd.append('style', document.getElementById('lk3-charts-style').value);
            fd.append('platform', document.getElementById('lk3-charts-platform').value);
            fd.append('module_count', document.getElementById('lk3-charts-module-count').value);
            fd.append('aspect_ratio', document.getElementById('lk3-charts-aspect-ratio').value);
            fd.append('seed_refs', document.getElementById('lk3-charts-seed-refs').value);
            // v11.3.0 #1: 宝玉20布局+17风格
            var layoutEl = document.getElementById('lk3-charts-layout');
            var styleEl = document.getElementById('lk3-charts-visual-style');
            if (layoutEl) fd.append('infographic_layout', layoutEl.value);
            if (styleEl) fd.append('infographic_style', styleEl.value);
            // v10.7.0: 跨生态云模版共享
            var cloudTpl = document.getElementById('lk3-charts-cloud-template');
            if (cloudTpl && cloudTpl.value) {
                fd.append('cloud_template_category', cloudTpl.value);
            }

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    genBtn.disabled = false;
                    spinner.style.display = 'none';
                    if (!res.success) {
                        statusEl.textContent = '✗ 生成失败';
                        statusEl.style.color = '#DC2626';
                        result.innerHTML = '<div style="color:#DC2626;padding:12px;">✗ ' + escapeHtml(res.data.message || '生成失败') + '</div>';
                        return;
                    }
                    statusEl.textContent = '✓ 生成完成';
                    statusEl.style.color = '#00a32a';
                    renderResult(res.data);
                })
                .catch(function(e){
                    genBtn.disabled = false;
                    spinner.style.display = 'none';
                    statusEl.textContent = '✗ 网络错误';
                    statusEl.style.color = '#DC2626';
                    result.innerHTML = '<div style="color:#DC2626;padding:12px;">✗ ' + escapeHtml(e.message) + '</div>';
                });
        });
    }

    function renderResult(data) {
        var modules = data.modules || [];
        var html = '';

        // 概览
        html += '<div style="background:#F4F4F5;border:1px solid #86efac;padding:10px 12px;margin-bottom:12px;border-radius:6px;">';
        html += '<strong style="color:#16a34a;">✓ 生成成功</strong> — ' + modules.length + ' 个模块';
        if (data.style) html += ' <span style="font-size:11px;color:#666;">| 画风: ' + escapeHtml(data.style) + '</span>';
        html += '</div>';

        // 批量操作
        html += '<div style="margin-bottom:12px;padding:10px;background:#f9fafb;border-radius:6px;">';
        html += '<strong>📦 批量操作:</strong> ';
        html += '<button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-copy-all">📋 复制全部</button> ';
        html += '<button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-download-all">⬇️ 下载全部</button> ';
        // v11.8.0: SOP闭环 — 保存草稿 + 去发布
        html += '<button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-save-draft">💾 保存为草稿</button> ';
        html += '<a href="window.linked3_charts.publish_url" class="lk3-charts-btn lk3-charts-btn-sm" style="text-decoration:none;display:inline-block;">📤 去发布</a>';
        html += '</div>';

        // v16.0.25: 集中提示词区 — 所有模块Prompt合并显示, 便于一次性复制
        var allPrompts = modules.map(function(m) {
            return '# ' + (m.scene_id || m.module_id || '') + ' ' + (m.title || '') + '\n' + (m.visual_prompt || '');
        }).join('\n\n---\n\n');
        html += '<details style="margin-bottom:12px;border:1px solid #0F172A;border-radius:6px;">';
        html += '<summary style="padding:10px 12px;cursor:pointer;font-size:13px;font-weight:600;color:#0F172A;background:#FAFAFA;border-radius:6px;">📋 集中查看全部提示词 (点击展开, 可一次性复制)</summary>';
        html += '<div style="padding:12px;"><textarea readonly style="width:100%;min-height:300px;font-family:monospace;font-size:11px;line-height:1.5;" onclick="this.select()">' + escapeHtml(allPrompts) + '</textarea>';
        html += '<div style="margin-top:6px;font-size:11px;color:#71717A;">💡 v16.3.0: 共' + modules.length + '镜, 每镜1个整体结构提示词 (非拆分)。点击文本框可全选, Ctrl+C复制。</div>';
        html += '</div></details>';

        // v16.3.0: 镜卡片 — 每镜含完整结构布局预览 + 1个整体提示词
        modules.forEach(function(m, idx) {
            var isUnified = (m.band === '4band-unified' || m.bands); // v16.3.0: 新模式标识
            html += '<div class="lk3-charts-module-card" style="border-left-color:#6366f1;">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
            html += '<div>';
            // v16.3.0: 镜标题 (非单个Band标签)
            html += '<span class="lk3-charts-band-tag" style="background:#F4F4F5;color:#4338ca;border:1px solid #6366f1;">🎬 ' + escapeHtml(m.scene_id || m.module_id || ('S' + (idx+1))) + '</span> ';
            if (m.scene_total && m.scene_total > 1) {
                html += '<span style="font-size:10px;color:#A1A1AA;">第' + escapeHtml(String(m.scene_index || (idx+1))) + '镜/共' + escapeHtml(String(m.scene_total)) + '镜</span> ';
            }
            html += '<strong style="font-size:13px;">' + escapeHtml(m.title || '') + '</strong>';
            html += '</div>';
            html += '<button type="button" class="lk3-charts-btn lk3-charts-btn-sm lk3-charts-copy" data-idx="' + idx + '">📋 复制提示词</button>';
            html += '</div>';

            // v16.3.0: 4Band布局预览 (若新模式含bands结构)
            if (isUnified && m.bands) {
                html += '<div style="margin-bottom:8px;padding:8px;background:#FAFAFA;border-radius:6px;border:1px dashed #D4D4D8;">';
                html += '<div style="font-size:11px;font-weight:600;color:#52525B;margin-bottom:6px;">📐 结构布局预览 (单张信息图内的区域)</div>';
                html += '<div style="display:grid;grid-template-rows:auto auto auto auto;gap:4px;">';
                var bandColors = {Hook:'#EF4444', Body:'#0F172A', Proof:'#10B981', CTA:'#F59E0B'};
                var bandZones = {Hook:'顶部', Body:'中部', Proof:'下部', CTA:'底部'};
                ['Hook','Body','Proof','CTA'].forEach(function(bk) {
                    var bd = m.bands[bk] || {};
                    var color = bandColors[bk] || '#71717A';
                    var zone = bandZones[bk] || '';
                    var text = bd.text_overlay || '';
                    html += '<div style="display:flex;align-items:center;gap:6px;padding:4px 8px;background:#fff;border-radius:4px;border-left:3px solid ' + color + ';">';
                    html += '<span style="font-size:10px;font-weight:700;color:' + color + ';min-width:50px;">' + escapeHtml(bk) + '</span>';
                    html += '<span style="font-size:9px;color:#A1A1AA;min-width:30px;">[' + escapeHtml(zone) + ']</span>';
                    html += '<span style="font-size:11px;color:#3F3F46;flex:1;">' + escapeHtml(text) + '</span>';
                    html += '</div>';
                });
                html += '</div>';
                html += '<div style="font-size:10px;color:#A1A1AA;margin-top:4px;">💡 以上结构区域合并为下方1个整体提示词, 生成1张含4区域的信息图</div>';
                html += '</div>';
            } else if (m.text_overlay) {
                // 向后兼容: 旧模式仅显示text_overlay
                html += '<div style="font-size:12px;color:#3F3F46;margin-bottom:4px;"><strong>画面文字:</strong> ' + escapeHtml(m.text_overlay) + '</div>';
            }

            if (m.seed_refs && m.seed_refs.length > 0) {
                html += '<div style="margin-bottom:4px;">';
                m.seed_refs.forEach(function(sr) {
                    html += '<span class="lk3-charts-seed-tag">🧬 ' + escapeHtml(sr) + '</span>';
                });
                html += '</div>';
            }
            // v16.3.0: 整体提示词 (每镜1个, 非每Band1个)
            html += '<div class="lk3-charts-prompt-box">';
            html += '<div style="font-size:10px;color:#6366f1;margin-bottom:2px;font-weight:600;">🎯 整体提示词 (含结构布局, 生成1张完整信息图)</div>';
            html += '<textarea readonly class="lk3-charts-prompt" data-idx="' + idx + '">' + escapeHtml(m.visual_prompt || '') + '</textarea>';
            html += '</div>';
            html += '</div>';
        });

        result.innerHTML = html;

        // 绑定复制
        result.querySelectorAll('.lk3-charts-copy').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = btn.dataset.idx;
                var ta = result.querySelector('.lk3-charts-prompt[data-idx="' + idx + '"]');
                if (ta) {
                    navigator.clipboard.writeText(ta.value).then(function() {
                        btn.textContent = '✓ 已复制';
                        setTimeout(function() { btn.textContent = '📋 复制'; }, 1500);
                    });
                }
            });
        });

        var copyAll = document.getElementById('lk3-charts-copy-all');
        if (copyAll) {
            copyAll.addEventListener('click', function() {
                var parts = modules.map(function(m) {
                    return '# ' + (m.module_id || '') + ' [' + (m.band || '') + '] ' + (m.title || '') + '\n' + (m.visual_prompt || '');
                });
                navigator.clipboard.writeText(parts.join('\n\n---\n\n')).then(function() {
                    alert('已复制 ' + modules.length + ' 个模块Prompt');
                });
            });
        }

        var dlBtn = document.getElementById('lk3-charts-download-all');
        if (dlBtn) {
            dlBtn.addEventListener('click', function() {
                var parts = modules.map(function(m) {
                    return '# ' + (m.module_id || '') + ' [' + (m.band || '') + '] ' + (m.title || '') + '\n' + (m.visual_prompt || '');
                });
                var blob = new Blob([parts.join('\n\n---\n\n')], {type:'text/plain'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'charts-' + Date.now() + '.txt';
                a.click();
                setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
            });
        }

        // v11.8.0: SOP闭环 — 保存为草稿
        var saveDraftBtn = document.getElementById('lk3-charts-save-draft');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', function() {
                var parts = modules.map(function(m) {
                    return '## ' + (m.module_id || '') + ' [' + (m.band || '') + '] ' + (m.title || '') + '\n\n' + (m.visual_prompt || '') + '\n\n' + (m.text_overlay || '');
                });
                var title = prompt('请输入文章标题:', '图示脚本-' + Date.now());
                if (!title) return;
                var fd = new FormData();
                fd.append('action', 'linked3_eco_save_draft');
                fd.append('nonce', nonce);
                fd.append('title', title);
                fd.append('content', parts.join('\n\n---\n\n'));
                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        alert(d.success ? '✅ 已保存为草稿' : '❌ ' + (d.data.message || '失败'));
                    });
            });
        }
    }

    function getBandColor(band) {
        var map = {Hook:'#EF4444', Body:'#0F172A', Proof:'#10B981', CTA:'#F59E0B'};
        return map[band] || '#0F172A';
    }

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }

    // v16.0.23: CSV批量生成功能
    var csvData = [];
    var csvHeaders = [];

    var dlBtn = document.getElementById('lk3-charts-csv-download-sample');
    if (dlBtn) {
        dlBtn.addEventListener('click', function(){
            var sample = 'topic,style,layout,module_count\nAI写作工具推荐,auto,auto-adapt,1\nChatGPT使用技巧,auto,linear-progression,2\n大模型微调教程,auto,hierarchical-layers,3\n';
            var blob = new Blob([sample], {type:'text/csv;charset=utf-8'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'linked3-charts-batch-sample.csv';
            a.click();
        });
    }

    var uploadBtn = document.getElementById('lk3-charts-csv-upload');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', function(){
            var fileInput = document.getElementById('lk3-charts-csv-file');
            var file = fileInput.files[0];
            if (!file) { alert('请先选择CSV文件'); return; }
            var reader = new FileReader();
            reader.onload = function(e){
                var text = e.target.result;
                var lines = text.split('\n').filter(function(l){return l.trim();});
                if (lines.length < 2) { alert('CSV至少需要1行表头+1行数据'); return; }
                csvHeaders = lines[0].split(',').map(function(s){return s.trim();});
                csvData = [];
                for (var i = 1; i < lines.length; i++) {
                    var parts = lines[i].split(',');
                    var row = {};
                    csvHeaders.forEach(function(h, idx){ row[h] = (parts[idx]||'').trim(); });
                    csvData.push(row);
                }
                var html = '<div style="font-size:12px;color:#52525B;margin-bottom:6px;">预览 ' + csvData.length + ' 条数据:</div>';
                html += '<table class="widefat striped" style="font-size:11px;"><thead><tr>';
                csvHeaders.forEach(function(h){ html += '<th>' + escapeHtml(h) + '</th>'; });
                html += '</tr></thead><tbody>';
                csvData.slice(0, 10).forEach(function(r){
                    html += '<tr>';
                    csvHeaders.forEach(function(h){ html += '<td>' + escapeHtml(r[h]||'') + '</td>'; });
                    html += '</tr>';
                });
                html += '</tbody></table>';
                if (csvData.length > 10) html += '<div style="font-size:11px;color:#71717A;margin-top:4px;">(仅显示前10条, 共' + csvData.length + '条)</div>';
                document.getElementById('lk3-charts-csv-preview').innerHTML = html;
                document.getElementById('lk3-charts-csv-generate').disabled = false;
            };
            reader.readAsText(file, 'UTF-8');
        });
    }

    var csvGenBtn = document.getElementById('lk3-charts-csv-generate');
    if (csvGenBtn) {
        csvGenBtn.addEventListener('click', function(){
            if (csvData.length === 0) { alert('请先上传CSV'); return; }
            csvGenBtn.disabled = true;
            csvGenBtn.textContent = '批量生成中...';
            var resultEl = document.getElementById('lk3-charts-csv-result');
            resultEl.innerHTML = '<div style="color:#71717A;font-size:12px;">批量生成中, 共 ' + csvData.length + ' 个主题...</div>';

            var results = [];
            var idx = 0;
            var defaultStyle = document.getElementById('lk3-charts-style').value;
            var defaultLayout = document.getElementById('lk3-charts-layout').value;
            var defaultModuleCount = document.getElementById('lk3-charts-module-count').value;
            var defaultVisualStyle = document.getElementById('lk3-charts-visual-style').value;
            var defaultAspectRatio = document.getElementById('lk3-charts-aspect-ratio').value;
            var defaultPlatform = document.getElementById('lk3-charts-platform').value;

            function processNext() {
                if (idx >= csvData.length) {
                    csvGenBtn.disabled = false;
                    csvGenBtn.textContent = '批量生成';
                    var successCount = results.filter(function(r){return r.success;}).length;
                    var html = '<div class="notice notice-success inline"><p>批量生成完成: ' + successCount + '/' + csvData.length + ' 成功</p></div>';
                    html += '<table class="widefat striped"><thead><tr><th>#</th><th>主题</th><th>状态</th><th>模块数</th></tr></thead><tbody>';
                    results.forEach(function(r, i){
                        html += '<tr><td>' + (i+1) + '</td><td>' + escapeHtml(r.topic) + '</td><td>' + (r.success ? '✅' : '❌ ' + escapeHtml(r.error||'')) + '</td><td>' + (r.module_count||0) + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    resultEl.innerHTML = html;
                    return;
                }
                var row = csvData[idx];
                var topic = row.topic || '';
                if (!topic) { results.push({topic:'(空)', success:false, error:'主题为空'}); idx++; processNext(); return; }

                var fd = new FormData();
                fd.append('action', 'linked3_charts_generate_v10');
                fd.append('nonce', nonce);
                fd.append('topic', topic);
                fd.append('style', row.style || defaultStyle);
                fd.append('infographic_layout', row.layout || defaultLayout);
                fd.append('infographic_style', defaultVisualStyle);
                fd.append('module_count', row.module_count || defaultModuleCount);
                fd.append('platform', defaultPlatform);
                fd.append('aspect_ratio', defaultAspectRatio);

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                    .then(function(data){
                        if (data.success) {
                            results.push({topic:topic, success:true, module_count:(data.data.modules||[]).length});
                        } else {
                            results.push({topic:topic, success:false, error:(data.data&&data.data.message)||'生成失败'});
                        }
                        idx++;
                        resultEl.innerHTML = '<div style="color:#71717A;font-size:12px;">批量生成中... ' + idx + '/' + csvData.length + '</div>';
                        processNext();
                    })
                    .catch(function(e){
                        results.push({topic:topic, success:false, error:e.message});
                        idx++;
                        processNext();
                    });
            }
            processNext();
        });
    }

    // v1.2: 风格库融合面板的JS逻辑已迁移至 style-fusion-panel.php 可复用组件
    // 每个实例(charts/genesis/video)通过 include 引入, 自带独立JS作用域, 避免重复
})();
