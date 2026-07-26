/**
 * Linked3 Genesis Tab JS
 * Extracted from: admin/views/dashboard/partials/tab-genesis.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-genesis.js
 *
 * Localized via wp_localize_script('linked3-tab-genesis', 'linked3_genesis', {...})
 *   - nonce:    AJAX nonce
 *   - ajax_url: admin-ajax.php URL
 */
(function(){
    'use strict';

    var nonce   = 'window.linked3_genesis.nonce';
    var ajaxUrl = 'window.linked3_genesis.ajax_url';

    // ============================================================
    // v10.0 新增: 阶段导航系统
    // ============================================================
    var currentStage = 0;
    var stageCompleted = {0:false, 1:false, 2:false, 3:false, 4:false};

    window.lk3GoStage = function(stage) {
        currentStage = stage;
        // 更新向导条
        document.querySelectorAll('.lk3-wizard-step').forEach(function(step) {
            var s = parseInt(step.dataset.stage);
            step.classList.remove('active', 'done');
            if (s < stage) step.classList.add('done');
            else if (s === stage) step.classList.add('active');
        });
        // 滚动到对应阶段
        var target = document.getElementById('lk3-stage-' + stage);
        if (target) {
            target.scrollIntoView({behavior:'smooth', block:'start'});
        }
        // 更新配置摘要 (进入Stage3时)
        if (stage === 3) updateSummary();
    };

    window.lk3ToggleSeedCat = function(header) {
        var body = header.nextElementSibling;
        if (body.style.display === 'none') {
            body.style.display = 'block';
        } else {
            body.style.display = body.style.display === 'none' ? 'block' : 'none';
        }
    };

    function updateSummary() {
        var styleEl = document.getElementById('linked3-genesis-style');
        var platEl  = document.getElementById('linked3-genesis-platform');
        var panEl   = document.getElementById('linked3-genesis-panel-count');
        var sSum = document.getElementById('lk3-summary-style');
        var pSum = document.getElementById('lk3-summary-platform');
        var nSum = document.getElementById('lk3-summary-panels');
        var seedSum = document.getElementById('lk3-summary-seeds');
        if (sSum && styleEl) sSum.textContent = styleEl.options[styleEl.selectedIndex] ? styleEl.options[styleEl.selectedIndex].text : '-';
        if (pSum && platEl) pSum.textContent = platEl.options[platEl.selectedIndex] ? platEl.options[platEl.selectedIndex].text : '-';
        if (nSum && panEl) nSum.textContent = panEl.value;
        var refs = document.getElementById('linked3-genesis-seed-refs').value;
        var count = refs ? refs.split(',').filter(function(s){return s;}).length : 0;
        if (seedSum) seedSum.textContent = count;
    }

    // 剧本字数统计
    var scriptEl = document.getElementById('linked3-genesis-script');
    var statsEl = document.getElementById('lk3-script-stats');
    if (scriptEl && statsEl) {
        scriptEl.addEventListener('input', function() {
            statsEl.textContent = scriptEl.value.length + ' 字';
        });
    }

    // ============================================================
    // v10.0 新增: SEED 中心 — 加载并渲染分类卡片
    // ============================================================
    var selectedSeedIds = [];

    function loadSeedCenter() {
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_seed_list');
        fd.append('nonce', nonce);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) return;
                renderSeedCenter(res.data.seeds || []);
                // 同步到原有 select (JS兼容)
                syncSeedSelect(res.data.seeds || []);
            })
            .catch(function(){ /* 静默失败, 不阻塞 */ });
    }

    function renderSeedCenter(seeds) {
        var categories = ['character','scene','prop','style','brand','palette'];
        var grouped = {};
        categories.forEach(function(c){ grouped[c] = []; });

        seeds.forEach(function(s) {
            var cat = s.category || s.seed_type || 'character';
            if (!grouped[cat]) cat = 'character';
            grouped[cat].push(s);
        });

        categories.forEach(function(cat) {
            var catStr = String(cat || '');
            var body = document.getElementById('lk3-seed-cat-' + catStr);
            var countEl = document.querySelector('[data-count="' + catStr + '"]');
            if (!body) return;
            var list = grouped[cat];
            if (countEl) countEl.textContent = list.length;

            if (list.length === 0) {
                body.innerHTML = '<div class="lk3-seed-empty" style="text-align:center;padding:20px;">' +
                    '<p style="font-size:13px;color:#71717A;margin:0 0 8px 0;">🧬 暂无 SEED</p>' +
                    '<p style="font-size:11px;color:#9ca3af;margin:0 0 12px 0;">SEED是角色/场景/道具等视觉DNA, 创建后可用于漫画/图示/视频脚本生成。</p>' +
                    '<button class="button button-small lk3-seed-new-btn" data-cat="' + escHtml(cat) + '" style="margin-right:6px;">+ 新建 SEED</button>' +
                    '<button class="button button-small lk3-seed-import-btn" data-cat="' + escHtml(cat) + '">📥 从剧本导入</button>' +
                    '</div>';
                return;
            }

            var html = '';
            list.forEach(function(s) {
                var isFixed = (s.lock === true || s.seed_type === 'fixed');
                var isSelected = selectedSeedIds.indexOf(s.seed_id) >= 0;
                var lockIcon = isFixed ? '🔒' : '🔄';
                var typeLabel = isFixed ? 'fixed' : 'variable';
                html += '<div class="lk3-seed-item' + (isSelected ? ' selected' : '') + '" data-seed-id="' + escapeHtml(s.seed_id) + '" onclick="lk3ToggleSeedSelect(\'' + escapeHtml(s.seed_id) + '\', \'' + escapeHtml(s.name || '') + '\')">';
                html += '<span class="lk3-seed-lock">' + lockIcon + '</span>';
                html += '<span class="lk3-seed-name">' + escapeHtml(s.name || '未命名') + '</span>';
                html += '<span class="lk3-seed-type ' + typeLabel + '">' + typeLabel + '</span>';
                html += '<span class="lk3-seed-actions">';
                html += '<button title="编辑" onclick="event.stopPropagation();lk3EditSeed(\'' + escapeHtml(s.seed_id) + '\')">✏️</button>';
                html += '<button title="删除" onclick="event.stopPropagation();lk3DeleteSeed(\'' + escapeHtml(s.seed_id) + '\')">🗑️</button>';
                html += '</span>';
                html += '</div>';
            });
            body.innerHTML = html;
        });
    }

    function syncSeedSelect(seeds) {
        var select = document.getElementById('linked3-genesis-seed-select');
        if (!select) return;
        var current = select.value;
        select.innerHTML = '<option value="">不使用 (全新创建)</option>';
        seeds.forEach(function(s) {
            var opt = document.createElement('option');
            opt.value = s.seed_id;
            opt.textContent = s.name + (s.style_name ? ' (' + s.style_name + ')' : '') + (s.created_at ? ' - ' + s.created_at : '');
            select.appendChild(opt);
        });
        select.value = current;
    }

    window.lk3ToggleSeedSelect = function(seedId, seedName) {
        var idx = selectedSeedIds.indexOf(seedId);
        if (idx >= 0) {
            selectedSeedIds.splice(idx, 1);
        } else {
            selectedSeedIds.push(seedId);
        }
        // 更新 hidden input (兼容原有逻辑)
        document.getElementById('linked3-genesis-seed-refs').value = selectedSeedIds.join(',');
        // 更新标签区
        updateSeedSelectedList(selectedSeedIds);
        // 重新渲染卡片选中态
        loadSeedCenter();
    };

    window.lk3EditSeed = function(seedId) {
        // v10.1.0: 增强SEED编辑 — 弹窗显示DNA并可编辑
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_seed_export');
        fd.append('nonce', nonce);
        fd.append('seed_id', seedId);

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) {
                    alert('获取SEED详情失败: ' + (res.data.message || ''));
                    return;
                }
                var seedData = {};
                try { seedData = JSON.parse(res.data.json || '{}'); } catch(e) {}
                showSeedEditModal(seedId, seedData);
            })
            .catch(function(e){
                alert('网络错误: ' + e.message);
            });
    };

    // v10.1.0: SEED编辑弹窗
    function showSeedEditModal(seedId, seedData) {
        // 移除已有弹窗
        var existing = document.getElementById('lk3-seed-edit-modal');
        if (existing) existing.remove();

        var visualDna = seedData.visual_dna || {};
        var personalityDna = seedData.personality_dna || {};
        var lock = seedData.lock || [];
        var priority = seedData.priority || {};
        var aiAdapter = seedData.ai_adapter || {};

        // 构建visual_dna可编辑字段
        var visualHtml = '';
        if (Object.keys(visualDna).length === 0) {
            visualHtml = '<div style="text-align:center;padding:16px;background:#f9fafb;border:1px dashed #d1d5db;border-radius:4px;">' +
                '<p style="font-size:12px;color:#71717A;margin:0 0 8px 0;">🎨 暂无视觉DNA数据</p>' +
                '<p style="font-size:11px;color:#9ca3af;margin:0 0 10px 0;">视觉DNA定义角色的画风/色彩/构图等, 填写后可保证跨分镜一致性。</p>' +
                '<button class="button button-small" onclick="document.getElementById(\'lk3-seed-visual-dna-generate\') && document.getElementById(\'lk3-seed-visual-dna-generate\').click()">🤖 AI生成视觉DNA</button>' +
                '</div>';
        } else {
            Object.keys(visualDna).forEach(function(key) {
                var val = typeof visualDna[key] === 'object' ? JSON.stringify(visualDna[key]) : visualDna[key];
                visualHtml += '<div style="margin-bottom:6px;"><label style="font-size:11px;color:#52525B;display:block;margin-bottom:2px;">' + escapeHtml(key) + '</label>';
                visualHtml += '<textarea class="lk3-form-control" data-dna-key="' + escapeHtml(key) + '" style="font-size:11px;min-height:40px;">' + escapeHtml(val) + '</textarea></div>';
            });
        }

        // 构建AI适配字段
        var adapterHtml = '';
        ['mj','sd','flux','dalle'].forEach(function(platform) {
            var val = aiAdapter[platform] || '';
            adapterHtml += '<div style="margin-bottom:4px;"><label style="font-size:10px;color:#71717A;">' + platform.toUpperCase() + '</label>';
            adapterHtml += '<input type="text" class="lk3-form-control" data-adapter-key="' + platform + '" value="' + escapeHtml(val) + '" style="font-size:11px;"></div>';
        });

        var html = '<div id="lk3-seed-edit-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;">';
        html += '<div style="background:#fff;border-radius:10px;width:90%;max-width:600px;max-height:85vh;overflow-y:auto;padding:20px;">';
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
        html += '<h3 style="margin:0;font-size:16px;">🧬 SEED 编辑: ' + escapeHtml(seedData.title || seedId) + '</h3>';
        html += '<button onclick="document.getElementById(\'lk3-seed-edit-modal\').remove()" style="background:none;border:none;font-size:20px;cursor:pointer;">✕</button>';
        html += '</div>';

        // 基本信息
        html += '<div style="background:#FAFAFA;padding:10px;border-radius:6px;margin-bottom:12px;font-size:11px;">';
        html += '<div><strong>SEED ID:</strong> ' + escapeHtml(seedId) + '</div>';
        html += '<div><strong>分类:</strong> ' + escapeHtml(seedData.seed_category || '-') + ' | <strong>类型:</strong> ' + escapeHtml(seedData.seed_type || '-') + '</div>';
        html += '<div><strong>锁定项:</strong> ' + escapeHtml(Array.isArray(lock) ? lock.join(', ') : JSON.stringify(lock)) + '</div>';
        html += '</div>';

        // 视觉DNA
        html += '<div style="margin-bottom:12px;">';
        html += '<div style="font-size:13px;font-weight:700;margin-bottom:6px;color:#52525B;">👁️ 视觉DNA (Visual DNA)</div>';
        html += '<div style="font-size:10px;color:#A1A1AA;margin-bottom:8px;">💡 角色的外貌/服装/特征等视觉基因。修改后保存生效。</div>';
        html += visualHtml;
        html += '</div>';

        // AI适配
        html += '<div style="margin-bottom:12px;">';
        html += '<div style="font-size:13px;font-weight:700;margin-bottom:6px;color:#52525B;">🤖 AI平台适配Prompt</div>';
        html += '<div style="font-size:10px;color:#A1A1AA;margin-bottom:8px;">💡 各生图平台的专属Prompt片段, 会注入到对应平台的输出中。</div>';
        html += adapterHtml;
        html += '</div>';

        // 操作按钮
        html += '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid #E4E4E7;">';
        html += '<button class="lk3-btn lk3-btn-sm" onclick="document.getElementById(\'lk3-seed-edit-modal\').remove()">取消</button>';
        html += '<button class="lk3-btn lk3-btn-sm lk3-btn-primary" onclick="lk3SaveSeedEdit(\'' + escapeHtml(seedId) + '\')">💾 保存修改</button>';
        html += '</div>';

        html += '</div></div>';

        document.body.insertAdjacentHTML('beforeend', html);
    }

    window.lk3SaveSeedEdit = function(seedId) {
        // 收集编辑后的数据
        var visualDna = {};
        document.querySelectorAll('#lk3-seed-edit-modal textarea[data-dna-key]').forEach(function(el) {
            var key = el.dataset.dnaKey;
            var val = el.value;
            // 尝试解析JSON
            try { visualDna[key] = JSON.parse(val); } catch(e) { visualDna[key] = val; }
        });
        var aiAdapter = {};
        document.querySelectorAll('#lk3-seed-edit-modal input[data-adapter-key]').forEach(function(el) {
            aiAdapter[el.dataset.adapterKey] = el.value;
        });

        var fd = new FormData();
        fd.append('action', 'linked3_save_seed');
        fd.append('nonce', nonce);
        fd.append('seed_id', seedId);
        fd.append('visual_dna', JSON.stringify(visualDna));
        fd.append('ai_adapter', JSON.stringify(aiAdapter));

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    alert('✓ SEED 已保存');
                    document.getElementById('lk3-seed-edit-modal').remove();
                    loadSeedCenter();
                } else {
                    alert('保存失败: ' + (res.data.message || ''));
                }
            })
            .catch(function(e){
                alert('网络错误: ' + e.message);
            });
    };

    window.lk3DeleteSeed = function(seedId) {
        if (!confirm('确定删除此 SEED? 此操作不可撤销。')) return;
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_seed_delete');
        fd.append('nonce', nonce);
        fd.append('seed_id', seedId);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    // 从已选列表移除
                    var idx = selectedSeedIds.indexOf(seedId);
                    if (idx >= 0) selectedSeedIds.splice(idx, 1);
                    document.getElementById('linked3-genesis-seed-refs').value = selectedSeedIds.join(',');
                    updateSeedSelectedList(selectedSeedIds);
                    loadSeedCenter();
                } else {
                    alert('删除失败: ' + (res.data && res.data.message ? res.data.message : ''));
                }
            });
    };

    // 保留原有 updateSeedSelectedList 函数 (增强版)
    function updateSeedSelectedList(seeds) {
        var listEl = document.getElementById('linked3-genesis-seed-selected-list');
        var countEl = document.getElementById('seed-ref-count');
        var emptyHint = document.getElementById('seed-empty-hint');
        if (!listEl) return;

        if (!seeds || seeds.length === 0) {
            listEl.innerHTML = '<span style="color:#A1A1AA;font-size:12px;" id="seed-empty-hint">未选择任何 SEED — 点击「从库中选择」或上方卡片</span>';
            if (countEl) countEl.textContent = '0';
            return;
        }

        var html = '';
        // seeds 可能是 ID 数组, 也可能是对象数组
        seeds.forEach(function(s) {
            var id = typeof s === 'string' ? s : (s.seed_id || s.id || '');
            var name = typeof s === 'string' ? s : (s.name || s.seed_id || id);
            html += '<span class="lk3-seed-tag">';
            html += '<span>🧬 ' + escapeHtml(name) + '</span>';
            html += '<span class="lk3-seed-tag-remove" onclick="lk3ToggleSeedSelect(\'' + escapeHtml(id) + '\',\'' + escapeHtml(name) + '\')">×</span>';
            html += '</span>';
        });
        listEl.innerHTML = html;
        if (countEl) countEl.textContent = seeds.length;
    }

    // 从库中选择按钮 (复用原有逻辑)
    var seedPickBtn = document.getElementById('linked3-genesis-seed-pick');
    if (seedPickBtn) {
        seedPickBtn.addEventListener('click', function() {
            // 滚动到 SEED 网格区
            document.getElementById('lk3-seed-grid').scrollIntoView({behavior:'smooth', block:'start'});
        });
    }

    // 清空按钮
    var seedClearBtn = document.getElementById('linked3-genesis-seed-clear');
    if (seedClearBtn) {
        seedClearBtn.addEventListener('click', function() {
            selectedSeedIds = [];
            document.getElementById('linked3-genesis-seed-refs').value = '';
            updateSeedSelectedList([]);
            loadSeedCenter();
        });
    }

    // 新建 SEED 按钮
    var seedCreateBtn = document.getElementById('lk3-seed-create-new');
    if (seedCreateBtn) {
        seedCreateBtn.addEventListener('click', function() {
            var panel = document.getElementById('linked3-genesis-seed-panel');
            if (panel) {
                panel.style.display = 'block';
                panel.scrollIntoView({behavior:'smooth'});
                var nameInput = document.getElementById('linked3-genesis-seed-name');
                if (nameInput) { nameInput.value = ''; nameInput.focus(); }
            }
        });
    }

    // 从模板导入按钮
    var seedImportBtn = document.getElementById('lk3-seed-import-tpl');
    if (seedImportBtn) {
        seedImportBtn.addEventListener('click', function() {
            if (!confirm('从 lib/seeds/ 模板库导入预设 SEED 到 CPT? \n(角色5个 + 场景5个 + 风格3个)')) return;
            var btn = this;
            btn.disabled = true;
            btn.textContent = '⏳ 导入中...';
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_import_templates');
            fd.append('nonce', nonce);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.disabled = false;
                    btn.textContent = '📥 从模板导入';
                    if (res.success) {
                        alert('导入成功: ' + (res.data.count || 0) + ' 个 SEED');
                        loadSeedCenter();
                    } else {
                        alert('导入失败: ' + (res.data && res.data.message ? res.data.message : '未知错误') + '\n\n(如该AJAX未注册, 请手动在 SEED CPT 管理页创建)');
                        // 降级: 直接刷新列表
                        loadSeedCenter();
                    }
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '📥 从模板导入';
                    // AJAX 可能未注册, 静默降级
                    loadSeedCenter();
                });
        });
    }

    // 刷新按钮
    var seedRefreshCatsBtn = document.getElementById('lk3-seed-refresh-cats');
    if (seedRefreshCatsBtn) {
        seedRefreshCatsBtn.addEventListener('click', loadSeedCenter);
    }

    // ============================================================
    // v10.0.2 新增: SEED 脚本生成器 — 从全剧本一键生成 SEED 库
    // ============================================================
    var seedGenBtn = document.getElementById('lk3-seedgen-run');
    if (seedGenBtn) {
        seedGenBtn.addEventListener('click', function() {
            var script = document.getElementById('lk3-seedgen-script').value.trim();
            if (!script || script.length < 20) {
                alert('请输入至少 20 字的剧本内容');
                return;
            }
            var scriptType = document.getElementById('lk3-seedgen-script-type').value;
            var styleId = document.getElementById('lk3-seedgen-style').value;
            var statusEl = document.getElementById('lk3-seedgen-status');
            var resultEl = document.getElementById('lk3-seedgen-result');
            var btn = this;

            btn.disabled = true;
            btn.textContent = '⏳ 生成中...';
            statusEl.textContent = 'AI 正在分析剧本, 提取角色/场景/道具/风格 DNA...';
            resultEl.innerHTML = '<div style="text-align:center;padding:16px;color:#7C3AED;"><div class="spinner is-active" style="float:none;margin:0 auto 8px;"></div>正在生成 SEED 库...</div>';

            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_generate');
            fd.append('nonce', nonce);
            fd.append('script', script);
            fd.append('style', styleId === 'auto' ? 'documentary_photo' : styleId);
            fd.append('seed_name', 'SEED库_' + scriptType + '_' + new Date().toLocaleDateString());
            // v10.0.2: 传递脚本类型, 后端可根据类型调整提取侧重点
            fd.append('script_type', scriptType);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    btn.disabled = false;
                    btn.textContent = '🥚 从剧本生成 SEED';
                    if (!res.success) {
                        statusEl.textContent = '✗ 生成失败';
                        statusEl.style.color = '#DC2626';
                        resultEl.innerHTML = '<div style="color:#DC2626;padding:8px;">✗ ' + escapeHtml(res.data.message || '生成失败') + '<br><br><strong>建议:</strong> 检查 AI API 配置, 或先使用「从模板导入」加载预设 SEED。</div>';
                        return;
                    }
                    // v10.0.3: 适配新的6类SEED生成结果
                    var dna = res.data.dna || {};
                    var created = res.data.created || {};
                    var seedId = res.data.seed_id || '';
                    var html = '<div style="background:#F4F4F5;padding:12px;border-radius:6px;border:1px solid #86efac;">';
                    html += '<div style="font-weight:700;color:#16a34a;margin-bottom:8px;">✓ SEED 库生成成功! 共 ' + (created.total || 0) + ' 个 SEED</div>';

                    // 显示6类SEED生成结果
                    if (created.characters && created.characters.length) {
                        html += '<div style="margin-bottom:6px;"><strong>👤 角色 (' + created.characters.length + '):</strong> ';
                        html += created.characters.map(function(c) { return escapeHtml(c.name || c.seed_id); }).join(' · ');
                        html += '</div>';
                    }
                    if (created.scenes && created.scenes.length) {
                        html += '<div style="margin-bottom:6px;"><strong>🏞️ 场景 (' + created.scenes.length + '):</strong> ';
                        html += created.scenes.map(function(s) { return escapeHtml(s.name || s.seed_id); }).join(' · ');
                        html += '</div>';
                    }
                    if (created.props && created.props.length) {
                        html += '<div style="margin-bottom:6px;"><strong>⚔️ 道具 (' + created.props.length + '):</strong> ';
                        html += created.props.map(function(p) { return escapeHtml(p.name || p.seed_id); }).join(' · ');
                        html += '</div>';
                    }
                    if (created.style) {
                        html += '<div style="margin-bottom:6px;"><strong>🎨 风格:</strong> ' + escapeHtml(created.style.name || created.style.seed_id) + '</div>';
                    }
                    if (created.palette) {
                        html += '<div style="margin-bottom:6px;"><strong>🌈 色板:</strong> 已生成</div>';
                    }
                    if (created.brand) {
                        html += '<div style="margin-bottom:6px;"><strong>🏷️ 品牌:</strong> ' + escapeHtml(created.brand.name || created.brand.seed_id) + '</div>';
                    }

                    // 兼容旧格式 (dna.characters等)
                    if (!created.characters && dna.characters && dna.characters.length) {
                        html += '<div style="margin-bottom:6px;"><strong>👤 角色 (DNA):</strong> ';
                        html += dna.characters.map(function(c) { return escapeHtml(c.name || '未知'); }).join(' · ');
                        html += '</div>';
                    }

                    html += '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #86efac;font-size:11px;color:#16a34a;">';
                    html += '✅ ' + (created.total || 0) + ' 个 SEED 已入库, 可在上方卡片中查看。现在可以进入 Stage 1 输入剧本, 开始生成漫画/图文/视频分镜。';
                    html += '</div>';
                    html += '</div>';

                    statusEl.textContent = '✓ 生成成功';
                    statusEl.style.color = '#16a34a';
                    resultEl.innerHTML = html;

                    // 刷新 SEED 中心
                    loadSeedCenter();
                })
                .catch(function(e) {
                    btn.disabled = false;
                    btn.textContent = '🥚 从剧本生成 SEED';
                    statusEl.textContent = '✗ 网络错误';
                    statusEl.style.color = '#DC2626';
                    resultEl.innerHTML = '<div style="color:#DC2626;padding:8px;">✗ ' + escapeHtml(e.message) + '<br><br>可能是 AJAX 请求失败。请检查网络连接, 或查看 PHP error_log。</div>';
                });
        });
    }

    // 页面加载时拉取 SEED 列表
    loadSeedCenter();

    // ============================================================
    // 保留原有逻辑: v9 三轴联动
    // ============================================================
    var l1 = document.getElementById('linked3-genesis-l1');
    var l2 = document.getElementById('linked3-genesis-l2');
    var l3 = document.getElementById('linked3-genesis-l3');
    var skeletonHint = document.getElementById('linked3-genesis-skeleton-hint');

    function updateSkeletonHint() {
        if (!l1 || !l2 || !l3 || !skeletonHint) return;
        var v1 = l1.value, v2 = l2.value, v3 = l3.value;
        var labels = {l1:{}, l2:{}, l3:{}};
        if (l1.selectedOptions[0]) labels.l1 = l1.selectedOptions[0].text;
        if (l2.selectedOptions[0]) labels.l2 = l2.selectedOptions[0].text;
        if (l3.selectedOptions[0]) labels.l3 = l3.selectedOptions[0].text;
        // v10.0.5: 处理"无"和"自动"选项
        var parts = [];
        if (v1 !== 'none') parts.push(labels.l1);
        if (v2 !== 'none') parts.push(labels.l2);
        if (v3 !== 'none') parts.push(labels.l3);
        var skeleton = 'documentary_photo';
        if (v2 !== 'none' && v2 !== 'auto' && v3 !== 'none' && v3 !== 'auto') {
            skeleton = v2 + '_' + v3;
        } else if (v2 !== 'none' && v2 !== 'auto') {
            skeleton = v2;
        }
        var hint = parts.length > 0 ? parts.join(' × ') + ' → <strong>' + escapeHtml(skeleton) + '</strong>' : '<strong>仅用画风风格控制 (三轴已跳过)</strong>';
        skeletonHint.innerHTML = '骨架路由: ' + hint;
    }
    if (l1) l1.addEventListener('change', updateSkeletonHint);
    if (l2) l2.addEventListener('change', updateSkeletonHint);
    if (l3) l3.addEventListener('change', updateSkeletonHint);
    updateSkeletonHint();

    // ============================================================
    // 保留原有逻辑: 错误分类系统
    // ============================================================
    function classifyError(e, httpStatus) {
        var msg = (e && e.message) ? e.message : String(e);
        var info = {type:'unknown', title:'生成失败', detail:msg, causes:[], actions:[]};

        if (httpStatus === 0 || /NetworkError|Failed to fetch|network/i.test(msg)) {
            info.type = 'network';
            info.title = '网络连接失败';
            info.detail = '无法连接到服务器。可能是网络中断或服务器未响应。';
            info.causes = ['网络连接不稳定', '服务器超时 (PHP max_execution_time)', '防火墙拦截'];
            info.actions = ['重试', '检查网络连接', '查看 PHP error_log'];
        } else if (httpStatus === 403) {
            info.type = 'forbidden';
            info.title = '权限被拒 (403)';
            info.detail = '服务器拒绝了请求。可能是 nonce 验证失败或权限不足。';
            info.causes = ['Nonce 过期', '用户角色无权限', '安全插件拦截'];
            info.actions = ['刷新页面重试', '检查用户角色权限', '暂时禁用安全插件测试'];
        } else if (httpStatus === 500) {
            info.type = 'server_error';
            info.title = '服务器内部错误 (500)';
            info.detail = '服务器遇到内部错误。通常是 PHP Fatal Error。';
            info.causes = ['PHP Fatal Error', '内存不足', '插件冲突'];
            info.actions = ['查看 PHP error_log', '增加 PHP memory_limit', '禁用其他插件排查冲突'];
        } else if (httpStatus === 504 || /timeout/i.test(msg)) {
            info.type = 'timeout';
            info.title = '请求超时';
            info.detail = '服务器响应超时。生成任务可能仍在后台运行。';
            info.causes = ['AI API 响应慢', 'PHP max_execution_time 过短', '分镜数过多'];
            info.actions = ['减少分镜数重试', '增加 PHP 超时', '查看任务是否在后台完成'];
        } else if (/api|key|unauthorized|401/i.test(msg)) {
            info.type = 'api_error';
            info.title = 'AI API 错误';
            info.detail = 'AI 服务商返回错误。可能是 API Key 无效或余额不足。';
            info.causes = ['API Key 无效', 'API 余额不足', 'API 限流'];
            info.actions = ['检查 API Key 配置', '查看 API 余额', '更换 AI 服务商'];
        } else {
            info.type = 'unknown';
            info.title = '生成失败';
            info.detail = msg;
            info.causes = ['未知原因'];
            info.actions = ['重试', '查看 PHP error_log', '联系技术支持'];
        }
        return info;
    }

    function renderClassifiedError(errInfo, rawMsg) {
        var html = '<div style="background:#fef2f2;border:1px solid #FECACA;border-radius:8px;padding:16px;">';
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">';
        html += '<span style="font-size:24px;">❌</span>';
        html += '<div><div style="font-size:15px;font-weight:700;color:#DC2626;">' + escapeHtml(errInfo.title) + '</div>';
        html += '<div style="font-size:12px;color:#991b1b;">类型: ' + escapeHtml(errInfo.type) + '</div></div>';
        html += '</div>';
        html += '<div style="font-size:13px;color:#7f1d1d;margin-bottom:10px;">' + escapeHtml(errInfo.detail) + '</div>';
        if (errInfo.causes.length) {
            html += '<div style="font-size:12px;margin-bottom:8px;"><strong>可能原因:</strong><ul style="margin:4px 0 0 20px;">';
            errInfo.causes.forEach(function(c){ html += '<li>' + escapeHtml(c) + '</li>'; });
            html += '</ul></div>';
        }
        if (errInfo.actions.length) {
            html += '<div style="font-size:12px;margin-bottom:10px;"><strong>建议操作:</strong><ul style="margin:4px 0 0 20px;color:#16a34a;">';
            errInfo.actions.forEach(function(a){ html += '<li>' + escapeHtml(a) + '</li>'; });
            html += '</ul></div>';
        }
        html += '<div style="display:flex;gap:8px;margin-top:12px;">';
        html += '<button type="button" class="lk3-btn lk3-btn-primary lk3-btn-sm" id="linked3-genesis-retry">↻ 重试</button>';
        html += '<button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-test-conn">🔌 测试连接</button>';
        html += '</div>';
        if (rawMsg) {
            html += '<details style="margin-top:10px;"><summary style="font-size:11px;color:#A1A1AA;cursor:pointer;">原始错误信息</summary>';
            html += '<pre style="font-size:10px;background:#18181B;color:#E4E4E7;padding:8px;border-radius:4px;overflow-x:auto;margin-top:6px;">' + escapeHtml(rawMsg) + '</pre></details>';
        }
        html += '</div>';
        return html;
    }

    // ============================================================
    // 保留原有逻辑: 测试连接
    // ============================================================
    function testConnection() {
        var btn = document.getElementById('linked3-genesis-test-btn');
        if (btn) { btn.disabled = true; btn.textContent = '⏳ 测试中...'; }
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_test_connection');
        fd.append('nonce', nonce);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (btn) { btn.disabled = false; btn.textContent = '🔌 测试连接'; }
                if (res.success) {
                    alert('✓ 连接正常!\n\nAI 服务商: ' + (res.data.provider || 'unknown') + '\n模型: ' + (res.data.model || 'unknown') + '\n延迟: ' + (res.data.latency || '?') + 'ms');
                } else {
                    alert('✗ 连接失败: ' + (res.data && res.data.message ? res.data.message : '未知错误'));
                }
            })
            .catch(function(e){
                if (btn) { btn.disabled = false; btn.textContent = '🔌 测试连接'; }
                alert('✗ 请求失败: ' + e.message);
            });
    }

    // ============================================================
    // 保留原有逻辑: 生成按钮 (v9模式 + 经典模式)
    // ============================================================
    var genBtn = document.getElementById('linked3-genesis-gen');
    var spinner = document.getElementById('linked3-genesis-spinner');
    var statusEl = document.getElementById('linked3-genesis-status');
    var result = document.getElementById('linked3-genesis-result');
    var cancelBtn = null; // 可扩展
    var currentJobId = null;
    var pollTimer = null;

    if (genBtn) {
        genBtn.addEventListener('click', function() {
            var script = document.getElementById('linked3-genesis-script').value.trim();
            if (!script) {
                alert('请先在 Stage 1 输入剧本');
                lk3GoStage(1);
                return;
            }
            var panelCount = document.getElementById('linked3-genesis-panel-count').value || 8;

            // v9 模式 (默认启用)
            var v9Mode = document.getElementById('linked3-genesis-v9-mode');
            var useV9 = v9Mode ? v9Mode.checked : true;

            if (useV9) {
                runV9Mode(script);
                return;
            }

            // 经典模式
            runClassicMode(script, panelCount);
        });
    }

    // v9 模式生成
    function runV9Mode(script) {
        genBtn.disabled = true;
        spinner.style.display = 'inline-block';
        statusEl.textContent = 'Stage 1: 拆解剧本...';
        statusEl.style.color = '#2271b1';
        result.innerHTML = '<div style="text-align:center;padding:30px;color:#71717A;"><div class="spinner is-active" style="float:none;margin:0 auto 10px;"></div>正在拆解剧本, 提取语义核节点...</div>';

        var l1 = document.getElementById('linked3-genesis-l1').value;
        var l2 = document.getElementById('linked3-genesis-l2').value;
        var l3 = document.getElementById('linked3-genesis-l3').value;
        var seedRefs = document.getElementById('linked3-genesis-seed-refs').value;

        var fd = new FormData();
        fd.append('action', 'linked3_genesis_v9_stage1');
        fd.append('nonce', nonce);
        fd.append('script', script);
        // v10.0.2 修复: 后端期望 l1_type/l2_column/l3_soul, 不是 l1/l2/l3
        fd.append('l1_type', l1);
        fd.append('l2_column', l2);
        fd.append('l3_soul', l3);
        fd.append('seed_refs', seedRefs);
        fd.append('style', document.getElementById('linked3-genesis-style').value);
        fd.append('platform', document.getElementById('linked3-genesis-platform').value);
        // v10.0.3 Bug2修复: 传递panel_count和split_mode, 后端按此控制beats数量
        fd.append('panel_count', document.getElementById('linked3-genesis-panel-count').value);
        fd.append('split_mode', document.getElementById('linked3-genesis-split-mode').value);
        // v11.0: 漫画分镜布局+画幅比例+渲染技法 (参照图示脚本大格局补全)
        fd.append('panel_layout', (document.getElementById('linked3-genesis-panel-layout')||{}).value || 'auto');
        fd.append('aspect_ratio', (document.getElementById('linked3-genesis-aspect-ratio')||{}).value || '3:4');
        fd.append('rendering_tech', (document.getElementById('linked3-genesis-rendering-tech')||{}).value || 'auto');
        // v10.0.2 修复: 后端期望 gen_mode 参数
        fd.append('gen_mode', 'local');

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) {
                    var errInfo = classifyError(new Error(res.data.message || 'Stage 1 失败'), null);
                    result.innerHTML = renderClassifiedError(errInfo, res.data.message);
                    bindErrorButtons();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '✗ Stage 1 失败';
                    statusEl.style.color = '#DC2626';
                    return;
                }
                // Stage 1 成功, 进入 Stage 2
                statusEl.textContent = 'Stage 2: 批量生成 Prompt...';
                result.innerHTML = '<div style="text-align:center;padding:30px;color:#71717A;"><div class="spinner is-active" style="float:none;margin:0 auto 10px;"></div>Stage 1 完成, 拆解出 ' + (res.data.beat_count || (res.data.beats ? res.data.beats.length : 0)) + ' 个分镜节点<br>正在批量生成 Prompt...</div>';

                // v10.0.2 修复: 后端 Stage2 期望 beats/characters/theme/skeleton_id/gen_mode
                // 不是 cores/cores_data/job_id
                var fd2 = new FormData();
                fd2.append('action', 'linked3_genesis_v9_stage2');
                fd2.append('nonce', nonce);
                fd2.append('beats', JSON.stringify(res.data.beats || []));
                fd2.append('characters', JSON.stringify(res.data.characters || []));
                fd2.append('theme', res.data.theme || '');
                fd2.append('skeleton_id', res.data.skeleton_id || 'documentary_photo');
                fd2.append('style', document.getElementById('linked3-genesis-style').value);
                fd2.append('platform', document.getElementById('linked3-genesis-platform').value);
                fd2.append('seed_refs', seedRefs);
                fd2.append('gen_mode', 'local');

                return fetch(ajaxUrl, {method:'POST', body:fd2, credentials:'same-origin'});
            })
            .then(function(r){ if (r) return r.json(); })
            .then(function(res2){
                if (!res2) return;
                spinner.style.display = 'none';
                genBtn.disabled = false;
                if (res2.success) {
                    statusEl.textContent = '✓ 生成完成';
                    statusEl.style.color = '#00a32a';
                    renderResult({success:true, data:res2.data}, result);
                    // 显示 Stage 4
                    var stage4 = document.getElementById('lk3-stage-4');
                    if (stage4) {
                        stage4.style.display = 'block';
                        stageCompleted[3] = true;
                    }
                } else {
                    var errInfo = classifyError(new Error(res2.data.message || 'Stage 2 失败'), null);
                    result.innerHTML = renderClassifiedError(errInfo, res2.data.message);
                    bindErrorButtons();
                    statusEl.textContent = '✗ Stage 2 失败';
                    statusEl.style.color = '#DC2626';
                }
            })
            .catch(function(e){
                spinner.style.display = 'none';
                genBtn.disabled = false;
                var errInfo = classifyError(e, null);
                result.innerHTML = renderClassifiedError(errInfo, e.message);
                bindErrorButtons();
                statusEl.textContent = '✗ 错误';
                statusEl.style.color = '#DC2626';
            });
    }

    // 经典模式生成 (异步任务)
    function runClassicMode(script, panelCount) {
        genBtn.disabled = true;
        spinner.style.display = 'inline-block';
        statusEl.textContent = '启动任务...';
        statusEl.style.color = '#2271b1';

        var fd = new FormData();
        fd.append('action', 'linked3_genesis_start_job');
        fd.append('nonce', nonce);
        fd.append('script', script);
        fd.append('style', document.getElementById('linked3-genesis-style').value);
        fd.append('platform', document.getElementById('linked3-genesis-platform').value);
        fd.append('panel_count', panelCount);
        fd.append('split_mode', document.getElementById('linked3-genesis-split-mode').value);
        fd.append('chapter_marker', document.getElementById('linked3-genesis-chapter-marker').value);
        // v11.0: 漫画分镜布局+画幅比例+渲染技法
        fd.append('panel_layout', (document.getElementById('linked3-genesis-panel-layout')||{}).value || 'auto');
        fd.append('aspect_ratio', (document.getElementById('linked3-genesis-aspect-ratio')||{}).value || '3:4');
        fd.append('rendering_tech', (document.getElementById('linked3-genesis-rendering-tech')||{}).value || 'auto');
        var seedSelect = document.getElementById('linked3-genesis-seed-select');
        if (seedSelect && seedSelect.value) fd.append('seed_id', seedSelect.value);
        // v10.0: 传递多 SEED 引用
        var seedRefs = document.getElementById('linked3-genesis-seed-refs').value;
        if (seedRefs) fd.append('seed_refs', seedRefs);

        var controller = new AbortController();
        var timeoutId = setTimeout(function(){ controller.abort(); }, 10000);

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin', signal: controller.signal})
            .then(function(r){
                clearTimeout(timeoutId);
                if (!r.ok) {
                    return r.text().then(function(t){
                        var msg = 'HTTP ' + r.status;
                        try { var j = JSON.parse(t); msg = (j.data && j.data.message) ? j.data.message : msg; } catch(e) { msg += ': ' + t.substring(0, 300); }
                        var err = new Error(msg); err.httpStatus = r.status; throw err;
                    });
                }
                return r.json();
            })
            .then(function(res){
                if (!res.success) {
                    var errInfo = classifyError(new Error(res.data.message || '启动失败'), null);
                    result.innerHTML = renderClassifiedError(errInfo, res.data.message);
                    bindErrorButtons();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '✗ 启动失败';
                    statusEl.style.color = '#DC2626';
                    return;
                }
                currentJobId = res.data.job_id;
                statusEl.textContent = '任务运行中 (job: ' + currentJobId.substring(0, 12) + '...)';
                pollJob(currentJobId);
            })
            .catch(function(e){
                clearTimeout(timeoutId);
                var errInfo = classifyError(e, e.httpStatus || null);
                result.innerHTML = renderClassifiedError(errInfo, e.message);
                bindErrorButtons();
                spinner.style.display = 'none';
                genBtn.disabled = false;
                statusEl.textContent = '✗ 启动失败';
                statusEl.style.color = '#DC2626';
            });
    }

    function pollJob(jobId) {
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_poll_job');
        fd.append('nonce', nonce);
        fd.append('job_id', jobId);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) {
                    stopPolling();
                    showError(res.data && res.data.message ? res.data.message : '轮询失败');
                    return;
                }
                var data = res.data;
                updateProgress(data);
                if (data.status === 'done') {
                    stopPolling();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '✓ 完成';
                    statusEl.style.color = '#00a32a';
                    renderResult({success:true, data:data.result}, result);
                    var stage4 = document.getElementById('lk3-stage-4');
                    if (stage4) { stage4.style.display = 'block'; stageCompleted[3] = true; }
                } else if (data.status === 'error') {
                    stopPolling();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '✗ 错误';
                    statusEl.style.color = '#DC2626';
                    var errInfo = {type:'job_error', title:'生成失败: ' + (data.error_class || 'Error'), detail:data.error || '未知错误', causes:[], actions:['重试','检查 API 配置','查看 PHP error_log']};
                    result.innerHTML = renderClassifiedError(errInfo, data.error);
                    bindErrorButtons();
                } else if (data.status === 'cancelled') {
                    stopPolling();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '已取消';
                    statusEl.style.color = '#666';
                } else {
                    pollTimer = setTimeout(function(){ pollJob(jobId); }, 2000);
                }
            })
            .catch(function(e){
                if (!pollJob._retries) pollJob._retries = 0;
                pollJob._retries++;
                if (pollJob._retries < 3) {
                    pollTimer = setTimeout(function(){ pollJob(jobId); }, 3000);
                } else {
                    stopPolling();
                    showError('轮询失败 (连续 3 次网络错误): ' + e.message);
                }
            });
    }

    function updateProgress(data) {
        if (data.progress !== undefined) {
            statusEl.textContent = '进度: ' + data.progress + '% — ' + (data.stage || '');
        }
    }

    function stopPolling() {
        if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
    }

    function showError(msg) {
        spinner.style.display = 'none';
        genBtn.disabled = false;
        statusEl.textContent = '✗ 错误';
        statusEl.style.color = '#DC2626';
        var errInfo = classifyError(new Error(msg), null);
        result.innerHTML = renderClassifiedError(errInfo, msg);
        bindErrorButtons();
    }

    function bindErrorButtons() {
        var retryBtn = document.getElementById('linked3-genesis-retry');
        if (retryBtn) retryBtn.addEventListener('click', function(){ genBtn.click(); });
        var testBtn = document.getElementById('linked3-genesis-test-conn');
        if (testBtn) testBtn.addEventListener('click', testConnection);
    }

    // ============================================================
    // 保留原有逻辑: 测试连接按钮 + 诊断按钮
    // ============================================================
    var testBtnTop = document.getElementById('linked3-genesis-test-btn');
    if (testBtnTop) testBtnTop.addEventListener('click', testConnection);

    var diagBtn = document.getElementById('linked3-genesis-diag-btn');
    if (diagBtn) {
        diagBtn.addEventListener('click', function(){
            var btn = this;
            btn.disabled = true;
            btn.textContent = '诊断中...';
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_server_diagnostic');
            fd.append('nonce', nonce);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.disabled = false;
                    btn.textContent = '🔧 服务器诊断';
                    if (!res.success) { alert('诊断失败: ' + (res.data.message || '')); return; }
                    var d = res.data;
                    var msg = '=== 服务器诊断报告 ===\n\n';
                    msg += '【PHP】\n  版本: ' + d.php.version + ' (SAPI: ' + d.php.sapi + ')\n  max_execution_time: ' + d.php.max_execution_time + 's\n  memory_limit: ' + d.php.memory_limit + '\n\n';
                    msg += '【curl】\n  启用: ' + (d.curl.enabled ? '✓' : '✗') + ' (v' + d.curl.version + ')\n  multi: ' + (d.curl.multi_enabled ? '✓' : '✗') + '\n\n';
                    msg += '【WordPress】\n  版本: ' + d.wordpress.version + '\n  WP_DEBUG: ' + (d.wordpress.wp_debug ? '开' : '关') + '\n\n';
                    msg += '【服务器】\n  软件: ' + d.server.software + '\n  fastcgi_finish_request: ' + (d.server.fastcgi_finish ? '✓' : '✗') + '\n\n';
                    msg += '【Genesis 类加载】\n';
                    if (d.genesis && d.genesis.classes_loaded) {
                        Object.keys(d.genesis.classes_loaded).forEach(function(k){
                            msg += '  ' + k + ': ' + (d.genesis.classes_loaded[k] ? '✓' : '✗') + '\n';
                        });
                    }
                    msg += '\n【预检结果】\n  ' + (d.genesis && d.genesis.preflight ? (d.genesis.preflight.ok ? '✓ 通过' : '✗ 失败: ' + d.genesis.preflight.message) : 'N/A') + '\n';
                    if (d.recommendations) {
                        msg += '\n【建议】\n';
                        d.recommendations.forEach(function(r){ msg += '  ' + r + '\n'; });
                    }
                    alert(msg);
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '🔧 服务器诊断';
                    alert('诊断请求失败: ' + e.message + '\n\n这本身就是一个信号 — 说明 AJAX 端点可能未注册或 PHP 有 Fatal Error。');
                });
        });
    }

    // ============================================================
    // 保留原有逻辑: Seed DNA 系统 (生成/导出/删除)
    // ============================================================
    var seedBtn = document.getElementById('linked3-genesis-seed-btn');
    if (seedBtn) {
        seedBtn.addEventListener('click', function(){
            var panel = document.getElementById('linked3-genesis-seed-panel');
            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                loadSeedList();
            } else {
                panel.style.display = 'none';
            }
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
                syncSeedSelect(res.data.seeds || []);
                renderSeedCenter(res.data.seeds || []);
            });
    }

    var seedGenBtn = document.getElementById('linked3-genesis-seed-gen');
    if (seedGenBtn) {
        seedGenBtn.addEventListener('click', function(){
            var script = document.getElementById('linked3-genesis-script').value.trim();
            if (!script) { alert('请先在 Stage 1 输入剧本'); lk3GoStage(1); return; }
            var seedName = document.getElementById('linked3-genesis-seed-name').value.trim() || '未命名 Seed';
            var styleId = document.getElementById('linked3-genesis-style').value;
            var btn = this;
            btn.disabled = true;
            btn.textContent = '🧬 生成中...';
            var resultEl = document.getElementById('linked3-genesis-seed-result');
            resultEl.innerHTML = '<p style="color:#666;">AI 分析剧本, 提取角色/场景/色彩 DNA...</p>';

            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_generate');
            fd.append('nonce', nonce);
            fd.append('script', script);
            fd.append('style', styleId);
            fd.append('seed_name', seedName);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.disabled = false;
                    btn.textContent = '🧬 AI 提取 Seed DNA';
                    if (!res.success) {
                        resultEl.innerHTML = '<p style="color:#DC2626;">✗ ' + escapeHtml(res.data.message || '失败') + '</p>';
                        return;
                    }
                    var dna = res.data.dna;
                    var html = '<div style="background:#F4F4F5;padding:8px;border-radius:4px;">';
                    html += '<p><strong>✓ Seed DNA 生成成功</strong> (ID: ' + res.data.seed_id + ')</p>';
                    if (dna.characters && dna.characters.length) {
                        html += '<p><strong>角色:</strong> ' + dna.characters.map(function(c){ return c.name + '(' + (c.appearance||'') + ')'; }).join(', ') + '</p>';
                    }
                    if (dna.scenes && dna.scenes.length) {
                        html += '<p><strong>场景:</strong> ' + dna.scenes.map(function(s){ return s.name; }).join(', ') + '</p>';
                    }
                    if (dna.color_palette) {
                        html += '<p><strong>色彩:</strong> ' + JSON.stringify(dna.color_palette) + '</p>';
                    }
                    html += '</div>';
                    resultEl.innerHTML = html;
                    loadSeedCenter();
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '🧬 AI 提取 Seed DNA';
                    resultEl.innerHTML = '<p style="color:#DC2626;">✗ ' + escapeHtml(e.message) + '</p>';
                });
        });
    }

    var seedExportBtn = document.getElementById('linked3-genesis-seed-export');
    if (seedExportBtn) {
        seedExportBtn.addEventListener('click', function(){
            var seedId = document.getElementById('linked3-genesis-seed-select').value;
            if (!seedId) { alert('请先选择一个 Seed'); return; }
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_export');
            fd.append('nonce', nonce);
            fd.append('seed_id', seedId);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (!res.success) { alert('导出失败'); return; }
                    var blob = new Blob([res.data.json], {type:'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'seed-dna-' + seedId + '.json';
                    a.click();
                    setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
                });
        });
    }

    var seedDeleteBtn = document.getElementById('linked3-genesis-seed-delete');
    if (seedDeleteBtn) {
        seedDeleteBtn.addEventListener('click', function(){
            var seedId = document.getElementById('linked3-genesis-seed-select').value;
            if (!seedId) { alert('请先选择一个 Seed'); return; }
            if (!confirm('确定删除此 Seed DNA?')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_delete');
            fd.append('nonce', nonce);
            fd.append('seed_id', seedId);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res.success) { alert('已删除'); loadSeedCenter(); }
                    else alert('删除失败');
                });
        });
    }

    // ============================================================
    // 保留原有逻辑: renderResult (结果渲染)
    // ============================================================
    function renderResult(res, el) {
        if (!res.success) {
            el.innerHTML = '<div class="notice notice-error inline"><p><strong>✗ 生成失败:</strong> ' + escapeHtml(res.data.message || '未知错误') + '</p></div>';
            return;
        }
        var d = res.data || {};
        var panels = d.panels || [];
        var total = d.total_panels || 0;
        var sceneCount = d.total_scenes || 0;

        if (total === 0) {
            var html = '<div class="notice notice-error inline" style="padding:14px;">';
            html += '<p><strong>✗ 生成 0 个分镜</strong> — 任务完成但未产出任何分镜</p>';
            html += '<div style="margin-top:10px;font-size:12px;color:#666;">';
            html += '<p><strong>诊断信息:</strong></p><ul style="margin-left:20px;">';
            html += '<li>FP 剥骨节点数: ' + (d.fp_cores || 0) + '</li>';
            html += '<li>并发模式: ' + escapeHtml(d.parallel_mode || 'unknown') + '</li>';
            if (d.error) html += '<li>错误: ' + escapeHtml(d.error) + '</li>';
            html += '</ul></div>';
            html += '<div style="margin-top:10px;font-size:12px;"><p><strong>建议操作:</strong></p><ul style="margin-left:20px;color:#16a34a;">';
            html += '<li>点击「🔌 测试连接」验证 API 是否正常</li>';
            html += '<li>点击「🔧 服务器诊断」检查配置</li>';
            html += '<li>查看 PHP error_log 获取详细错误</li>';
            html += '<li>尝试输入更长的剧本 (至少 100 字)</li>';
            html += '</ul></div>';
            html += '<p style="margin-top:12px;"><button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-retry">↻ 重试</button></p>';
            html += '</div>';
            el.innerHTML = html;
            var retryBtn = document.getElementById('linked3-genesis-retry');
            if (retryBtn) retryBtn.addEventListener('click', function(){ genBtn.click(); });
            return;
        }

        var html = '';
        // 概览
        html += '<div style="background:#F4F4F5;border:1px solid #86efac;padding:10px 12px;margin-bottom:12px;border-radius:6px;">';
        html += '<strong style="color:#16a34a;">✓ 生成成功</strong> — ' + sceneCount + ' 个场景, <strong>' + total + '</strong> 个分镜';
        if (d.target_panels) html += ' <span style="font-size:11px;color:#666;">(目标: ' + d.target_panels + (d.is_auto ? ' · auto 动态' : '') + ')</span>';
        html += '<span style="font-size:11px;color:#666;margin-left:10px;">| 风格: ' + escapeHtml(d.style || '') + ' | 平台: ' + escapeHtml(d.platform || '') + '</span>';
        if (d.pipeline) html += '<div style="margin-top:4px;font-size:10px;color:#2271b1;">📋 流程: ' + escapeHtml(d.pipeline) + '</div>';
        if (d.fp_cores) html += '<div style="margin-top:6px;font-size:11px;color:#7c3aed;">🦴 FP剥骨: 提纯 ' + d.fp_cores + ' 个语义核节点 → 每节点独立 AI 调用生成画面 Prompt</div>';
        if (d.ai_generated_count !== undefined) {
            var mode = d.parallel_mode || 'unknown';
            var modeLabel = mode === 'curl_multi' ? '⚡ curl_multi 真并发' : (mode === 'serial' ? '🔄 串行降级' : mode);
            var elapsed = d.parallel_elapsed_ms || 0;
            var ai = d.ai_generated_count || 0;
            var retry = d.ai_retry_count || 0;
            var local = d.local_fallback_count || 0;
            var qualityColor = (ai + retry) > local ? '#16a34a' : ((ai + retry) > 0 ? '#F59E0B' : '#DC2626');
            html += '<div style="margin-top:4px;font-size:11px;color:' + qualityColor + ';">';
            html += '🤖 ' + modeLabel + ' (' + (elapsed / 1000).toFixed(1) + 's) — AI优质: ' + ai + ' 个';
            if (retry > 0) html += ', <span style="color:#7c3aed;">AI重试成功: ' + retry + ' 个</span>';
            html += ', 本地兜底: ' + local + ' 个';
            html += '</div>';
        }
        if (d.v7_manifest) {
            var m = d.v7_manifest;
            html += '<div style="margin-top:6px;font-size:11px;color:#2563eb;">🔧 v7流水线: 变异' + (m.survivors + m.culled) + '个 → 存活' + m.survivors + '个 → 绞杀' + m.culled + '个 → 锁定' + m.total + '个</div>';
        }
        html += '</div>';

        // 批量操作
        html += '<div style="margin-bottom:12px;padding:10px;background:#f9fafb;border-radius:6px;">';
        html += '<strong>📦 批量操作:</strong> ';
        html += '<button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-copy-all">📋 复制全部 Prompt</button> ';
        html += '<button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-download-all">⬇️ 下载全部</button>';
        html += '</div>';

        // 分镜卡片
        panels.forEach(function(p, idx) {
            var sceneColor = ['#4A90E2','#F5A623','#7ED321','#D0506E','#9013FE','#50C8D6'][idx % 6];
            html += '<div class="lk3-panel-card" style="border-left-color:' + sceneColor + ';">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
            html += '<div>';
            html += '<span style="background:' + sceneColor + ';color:#fff;padding:2px 6px;border-radius:3px;font-weight:bold;font-size:11px;">' + escapeHtml(p.panel_id) + '</span> ';
            html += '<span style="font-weight:600;font-size:13px;">' + escapeHtml(p.location || '') + '</span>';
            html += '<span style="font-size:11px;color:#666;margin-left:6px;">' + escapeHtml(p.shot || '') + '/' + escapeHtml(p.angle || '') + '/' + escapeHtml(p.comp || '') + '</span>';
            if (p.prompt_source === 'ai_retry') {
                html += ' <span style="display:inline-block;background:#F4F4F5;color:#7c3aed;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:bold;">AI重试</span>';
            } else if (p.prompt_source === 'ai' && p.ai_degraded) {
                html += ' <span style="display:inline-block;background:#FECACA;color:#b91c1c;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:bold;">AI劣化</span>';
            } else if (p.prompt_source === 'ai') {
                html += ' <span style="display:inline-block;background:#dcfce7;color:#16a34a;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:bold;">AI</span>';
            } else if (p.prompt_source === 'local') {
                html += ' <span style="display:inline-block;background:#FEF3C7;color:#d97706;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:bold;">本地</span>';
            }
            html += '</div>';
            html += '<button type="button" class="lk3-btn lk3-btn-sm linked3-genesis-copy" data-idx="' + idx + '">📋 复制</button>';
            html += '</div>';
            if (p.action) html += '<div style="font-size:12px;color:#3F3F46;margin-bottom:3px;"><strong>动作:</strong> ' + escapeHtml(p.action) + '</div>';
            if (p.mood) html += '<div style="font-size:11px;color:#71717A;margin-bottom:3px;"><strong>氛围:</strong> ' + escapeHtml(p.mood) + '</div>';
            if (p.core_info || p.plot_point) {
                html += '<div style="background:#faf5ff;border-left:3px solid #7c3aed;padding:4px 8px;margin:4px 0;border-radius:3px;font-size:11px;">';
                if (p.core_info) html += '<div style="color:#7c3aed;"><strong>🦴 语义核:</strong> ' + escapeHtml(p.core_info) + '</div>';
                if (p.plot_point) html += '<div style="color:#9333ea;margin-top:2px;"><strong>📍 情节点:</strong> ' + escapeHtml(p.plot_point) + '</div>';
                html += '</div>';
            }
            if (p.character_details && p.character_details.length > 0) {
                html += '<div style="margin-bottom:4px;">';
                p.character_details.forEach(function(c) {
                    html += '<span style="display:inline-block;background:#E0F2FE;color:#0369A1;padding:1px 6px;border-radius:3px;font-size:10px;margin-right:3px;">' + escapeHtml(c.id) + ' ' + escapeHtml(c.role) + '</span>';
                });
                html += '</div>';
            }
            html += '<div class="lk3-prompt-box">';
            html += '<textarea readonly class="linked3-genesis-prompt" data-idx="' + idx + '">' + escapeHtml(p.prompt_with_params || p.prompt_en || '') + '</textarea>';
            html += '</div>';
            if (p.pqs) {
                var pp = p.pqs.passed || 0;
                var tt = p.pqs.total || 0;
                html += '<div style="margin-top:3px;font-size:10px;">PQS: ' + pp + '/' + tt + ' ' + (pp === tt ? '✅' : '⚠️') + '</div>';
            }
            if (p.v7_pipeline) {
                var v7Status = p.v7_status || 'unknown';
                var v7Score = p.v7_score || 0;
                var v7Color = v7Status === 'locked' ? '#16a34a' : (v7Status === 'fallback' ? '#F59E0B' : '#71717A');
                html += '<div style="margin-top:2px;font-size:10px;color:' + v7Color + ';">🔧 v7: ' + v7Status + ' (score: ' + v7Score + '/40)</div>';
            }
            html += '</div>';
        });

        el.innerHTML = html;

        // 绑定复制
        el.querySelectorAll('.linked3-genesis-copy').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = btn.dataset.idx;
                var ta = el.querySelector('.linked3-genesis-prompt[data-idx="' + idx + '"]');
                if (ta) {
                    navigator.clipboard.writeText(ta.value).then(function() {
                        btn.textContent = '✓ 已复制';
                        setTimeout(function() { btn.textContent = '📋 复制'; }, 1500);
                    });
                }
            });
        });

        var copyAll = document.getElementById('linked3-genesis-copy-all');
        if (copyAll) {
            copyAll.addEventListener('click', function() {
                var parts = panels.map(function(p) {
                    return '# ' + p.panel_id + ' ' + p.location + '\n' + (p.prompt_with_params || p.prompt_en || '');
                });
                navigator.clipboard.writeText(parts.join('\n\n---\n\n')).then(function() {
                    alert('已复制 ' + panels.length + ' 个分镜 Prompt');
                });
            });
        }

        var dlBtn = document.getElementById('linked3-genesis-download-all');
        if (dlBtn) {
            dlBtn.addEventListener('click', function() {
                var parts = panels.map(function(p) {
                    return '# ' + p.panel_id + ' ' + p.location + '\n' + (p.prompt_with_params || p.prompt_en || '');
                });
                var blob = new Blob([parts.join('\n\n---\n\n')], {type:'text/plain'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'genesis-panels-' + Date.now() + '.txt';
                a.click();
                setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
            });
        }
    }

    // ============================================================
    // 工具函数
    // ============================================================
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }

    // 暴露给 onclick 使用的函数
    window.lk3EscapeHtml = escapeHtml;

})();