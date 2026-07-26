/**
 * Linked3 Cognitive OS Tab JS
 * Extracted from: admin/views/dashboard/partials/tab-cognitive-os.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-cognitive-os.js
 * Localized via wp_localize_script('linked3-tab-cognitive-os', 'linked3_cos', {...})
 *   Keys: ajax_url, nonce
 */

(function(){
    'use strict';
    var ajaxUrl = 'window.linked3_cos.ajax_url';
    var nonce   = 'window.linked3_cos.nonce';

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        for (var k in data) { if (data.hasOwnProperty(k)) fd.append(k, data[k]); }
        // v20.4-fix14: 客户端超时从 75s → 65s
        // v20.4-fix25: 客户端超时60s→65s, 配合动态timeout(后期杠杆45s)
        // v27.8.8-fix: 客户端超时65s→120s, 配合后端set_time_limit(120) — 演化需要更多时间
        // v27.8.9-fix: 客户端超时120s→180s, 配合后端set_time_limit(180) — G1可能需要更长时间
        var controller = new AbortController();
        var timeoutId = setTimeout(function(){ controller.abort(); }, 180000);
        return fetch(ajaxUrl, {method: 'POST', body: fd, credentials: 'same-origin', signal: controller.signal})
            .then(function(r){
                clearTimeout(timeoutId);
                // v20.4-fix7: 先检查 HTTP 状态码, 非 200 直接报错
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status + ' ' + r.statusText);
                }
                return r.text();
            })
            .then(function(text){
                // v20.4-fix7: 容错解析 JSON, 非 JSON 时返回错误信息
                try {
                    return JSON.parse(text);
                } catch(e) {
                    // 截取前 200 字符帮助诊断
                    var preview = text.substring(0, 200);
                    throw new Error('AJAX 返回非 JSON: ' + preview);
                }
            })
            .catch(function(err){
                clearTimeout(timeoutId);
                // v20.4-fix14: 超时 abort 抛 AbortError, 转成更友好的中文提示
                if (err.name === 'AbortError') {
                    throw new Error('请求超时 (180秒), 服务器未响应。建议: 1)点击"重置 AI 熔断器" 2)减少勾选的杠杆数量 3)重试。');
                }
                throw err;
            });
    }

    // ── v20.4-fix2: 版本探针 — 页面加载时自动检测部署状态 ──
    function checkVersion() {
        var badge = document.getElementById('cos-patch-badge');
        if (!badge) return;
        post('linked3_cos_version', {}).then(function(res){
            if (!res.success || !res.data) {
                badge.textContent = '⚠ 版本未知';
                badge.style.background = 'rgba(254,243,199,0.9)';
                badge.style.color = '#92400e';
                return;
            }
            var d = res.data;
            var allOk = d.checks && d.checks.extract_rules_is_public && d.checks.chat_has_3_args && d.checks.registry_auto_init && d.checks.chain_chunked_fix10;
            // v27.6.19-fix: 不再硬编码版本号，检查 patch_version 非空且 allOk
            if (d.patch_version && d.patch_version !== 'unknown' && allOk) {
                badge.textContent = '✓ ' + d.patch_version + ' 已生效';
                badge.style.background = 'rgba(209,250,229,0.9)';
                badge.style.color = '#065f46';
            } else {
                badge.textContent = '✗ 旧代码仍在运行 (' + d.patch_version + ')';
                badge.style.background = 'rgba(254,226,226,0.9)';
                badge.style.color = '#991b1b';
                badge.title = '修复未生效! 检查项: ' + JSON.stringify(d.checks) + '\n需要: 1)重新上传zip 2)清OPcache 3)重启PHP-FPM';
            }
        }).catch(function(){
            badge.textContent = '⚠ 探针失败';
            badge.style.background = 'rgba(254,243,199,0.9)';
            badge.style.color = '#92400e';
        });
    }
    checkVersion();

    // ── v20.4-fix12: 领域下拉 + 自定义切换 ──
    var domainSelect = document.getElementById('cos-domain-input');
    var domainCustom = document.getElementById('cos-domain-custom');
    if (domainSelect && domainCustom) {
        domainSelect.addEventListener('change', function(){
            domainCustom.style.display = (this.value === '__custom__') ? 'block' : 'none';
        });
    }
    function getDomain() {
        if (!domainSelect) return 'ecommerce';
        if (domainSelect.value === '__custom__') {
            return (domainCustom.value || '').trim() || 'general';
        }
        return domainSelect.value;
    }

    // v27.8.11 (审计Phase2): 问题描述失焦时动态评分杠杆
    var problemInput = document.getElementById('cos-problem-input');
    if (problemInput) {
        var scoreTimer = null;
        problemInput.addEventListener('blur', function(){
            var problem = this.value.trim();
            if (!problem || problem.length < 4) return; // 太短不评分
            clearTimeout(scoreTimer);
            scoreTimer = setTimeout(function(){
                post('linked3_cos_score_levers', { problem: problem }).then(function(res){
                    if (!res.success || !res.data || !res.data.scores) return;
                    // 更新所有 lever-fitness 元素
                    document.querySelectorAll('.lever-fitness').forEach(function(el){
                        var defaultVal = el.getAttribute('data-default');
                        // 复合杠杆的 id 映射 (简化: 用文本匹配)
                        var labelText = el.parentElement.textContent.trim();
                        var updated = false;
                        for (var leverId in res.data.scores) {
                            var score = res.data.scores[leverId];
                            if (labelText.indexOf(score.label) !== -1) {
                                el.textContent = '适应度' + score.fitness;
                                el.style.fontWeight = score.match_count > 0 ? '700' : '400';
                                updated = true;
                                break;
                            }
                        }
                        if (!updated) {
                            // 没匹配到的保持默认
                            el.textContent = '适应度' + defaultVal;
                            el.style.fontWeight = '400';
                        }
                    });
                }).catch(function(){});
            }, 500);
        });
        // 页面加载时也触发一次
        setTimeout(function(){ problemInput.blur(); }, 1000);
    }

    // ── STEP 1+2: 启动演化 (v20.4-fix8: 异步逐代调用) ──
    var evolveBtn = document.getElementById('cos-evolve-btn');
    var resultDiv = document.getElementById('cos-evolve-result');

    // v27.8.12: runGen 函数定义在外部, 供 runGenDirect 和预检成功后共用
    function runGen(gen, baseline) {
        resultDiv.innerHTML = '<div style="padding: 16px; text-align: center; color: #6b7280;"><div style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: cos-spin 0.8s linear infinite;"></div><div style="margin-top: 8px; font-size: 13px;">运行 ' + gen + ' 演化中 (AI 生成方案)...</div></div>';

        var postData = {
            problem: problem,
            generation: gen,
            domain: domain,
        };
        if (baseline) postData.baseline = JSON.stringify(baseline);

        return post('linked3_cos_evolve_gen', postData).then(function(res){
            if (!res.success) {
                var errMsg = (res.data && res.data.message) ? res.data.message : (gen + ' 演化失败');
                var elapsed = (res.data && res.data.elapsed) ? ' (耗时 ' + res.data.elapsed + 's)' : '';
                var diagInfo = '';
                if (res.data && res.data.diagnosis) {
                    var d = res.data.diagnosis;
                    if (d.ai_config) {
                        if (!d.ai_config.any_provider_has_key) {
                            diagInfo = '\n⚠️ 诊断: 未配置任何 AI Provider 的 API Key, 请到「系统设置→API设置」配置。';
                        } else if (!d.ai_config.default_has_key) {
                            diagInfo = '\n⚠️ 诊断: 默认 Provider "' + d.ai_config.default_provider + '" 未配置 Key, 但其他 Provider 有 Key。请切换默认 Provider 或配置对应 Key。';
                        }
                    }
                }
                var err = new Error(errMsg + elapsed + diagInfo);
                err.diagnosis = res.data && res.data.diagnosis;
                throw err;
            }
            var genResult = res.data;
            if (genResult.status && genResult.status !== 'pass') {
                var failMsg = genResult.message || (gen + ' 演化状态: ' + genResult.status);
                if (genResult.failed_at) failMsg += ' (失败部门: ' + genResult.failed_at + ')';
                throw new Error(failMsg);
            }
            generations.push(genResult);
            if (genResult.mvp) {
                finalMvp = genResult.mvp;
            }
            return genResult;
        });
    }

    // v27.8.12: 演化链执行函数 (G1→G2→G3→finalize)
    function runEvolutionChain() {
        runGen('G1', null)
            .then(function(g1){ return runGen('G2', g1.mvp); })
            .then(function(g2){ return runGen('G3', g2.mvp); })
            .then(function(){
                if (!finalMvp) throw new Error('未获得最终 MVP');
                return post('linked3_cos_evolve_finalize', {
                    problem: problem,
                    domain: domain,
                    mvp: JSON.stringify(finalMvp),
                    generations: JSON.stringify(generations),
                });
            })
            .then(function(res){
                evolveBtn.disabled = false;
                evolveBtn.textContent = '▶ 启动演化';
                if (res.success) {
                    var compatResult = {
                        final_status: 'success',
                        final_mvp: finalMvp,
                        generations: generations,
                    };
                    renderEvolveResult(compatResult);
                    refreshDashboard();
                    if (finalMvp && finalMvp.approach) {
                        autoRecommendLevers(problem, domain, finalMvp.approach);
                    }
                } else {
                    resultDiv.innerHTML = '<div style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px;">❌ 结晶失败: ' + escapeHtml(res.data?.message || '未知错误') + '</div>';
                }
            })
            .catch(function(err){
                evolveBtn.disabled = false;
                evolveBtn.textContent = '▶ 启动演化';
                var errHtml = '<div style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px;">';
                errHtml += '❌ 演化失败: ' + escapeHtml(String(err.message || err));
                errHtml += '<br><br><button id="cos-diag-btn" style="background: #1f2937; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-size: 12px; cursor: pointer;">🔍 运行 AI 诊断</button>';
                if (err.diagnosis) {
                    errHtml += ' <button id="cos-show-diag" style="background: #6b7280; color: #fff; border: none; padding: 6px 16px; border-radius: 6px; font-size: 12px; cursor: pointer; margin-left: 8px;">📋 查看诊断详情</button>';
                }
                errHtml += '<div id="cos-diag-result" style="margin-top: 8px;"></div>';
                errHtml += '</div>';
                resultDiv.innerHTML = errHtml;
                var diagBtn = document.getElementById('cos-diag-btn');
                if (diagBtn) diagBtn.addEventListener('click', runDiagnose);
                var showDiagBtn = document.getElementById('cos-show-diag');
                if (showDiagBtn) {
                    showDiagBtn.addEventListener('click', function(){
                        var diagHtml = '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 10px; margin-top: 8px; font-size: 12px; color: #856404; white-space: pre-wrap;">';
                        diagHtml += '<strong>诊断详情:</strong>\n' + escapeHtml(JSON.stringify(err.diagnosis, null, 2));
                        diagHtml += '</div>';
                        document.getElementById('cos-diag-result').innerHTML = diagHtml;
                    });
                }
            });
    }

    evolveBtn.addEventListener('click', function(){
        var problem = document.getElementById('cos-problem-input').value.trim();
        var domain  = getDomain();
        if (!problem) { alert('请输入问题描述'); return; }

        evolveBtn.disabled = true;
        evolveBtn.textContent = '演化中...';
        resultDiv.style.display = 'block';

        // v27.8.12: 移除阻塞式预检 (ajax_diagnose 会做AI调用, 60s超时)
        // 改为直接开始演化 — 如果 AI 未配置, 演化会返回 fallback 方案 (v27.8.10 已实现)
        // 或在 catch 中显示诊断信息引导用户配置
        var generations = [];
        var finalMvp = null;

        resultDiv.innerHTML = '<div style="padding: 16px; text-align: center; color: #6b7280;"><div style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: cos-spin 0.8s linear infinite;"></div><div style="margin-top: 8px; font-size: 13px;">运行 G1 演化中 (AI 生成方案)...</div></div>';

        runEvolutionChain();
    });

    // v20.4-fix6: AI 诊断功能
    function runDiagnose() {
        var diagResult = document.getElementById('cos-diag-result');
        if (!diagResult) return;
        diagResult.innerHTML = '<div style="padding: 8px; color: #6b7280; font-size: 12px;">诊断中...</div>';
        post('linked3_cos_diagnose', {}).then(function(res){
            if (!res || !res.success || !res.data) {
                var errMsg = (res && res.data && res.data.message) ? res.data.message : '诊断失败 - AJAX 返回非 JSON';
                diagResult.innerHTML = '<div style="padding: 8px; color: #991b1b; font-size: 12px;">' + escapeHtml(errMsg) + '</div>';
                return;
            }
            var d = res.data;
            var html = '<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; font-size: 11px; color: #374151; line-height: 1.8;">';
            html += '<strong>PHP 版本:</strong> ' + escapeHtml(d.php_version) + '<br>';
            html += '<strong>max_execution_time:</strong> ' + escapeHtml(d.max_execution) + ' 秒<br>';
            html += '<strong>set_time_limit 可用:</strong> ' + (d.set_time_limit ? '✓' : '✗') + '<br>';
            html += '<strong>AI Dispatcher:</strong> ' + (d.ai_dispatcher ? '✓ 已加载' : '✗ 未加载') + '<br>';
            html += '<strong>默认 Provider:</strong> ' + escapeHtml(d.default_provider) + '<br>';
            html += '<strong>Provider Keys:</strong><br>';
            for (var slug in d.provider_keys) {
                html += '&nbsp;&nbsp;' + slug + ': ' + d.provider_keys[slug] + '<br>';
            }
            if (d.test_result) {
                html += '<strong style="color: #065f46;">AI 测试:</strong> ' + escapeHtml(d.test_result) + '<br>';
            }
            if (d.test_error) {
                html += '<strong style="color: #991b1b;">AI 错误:</strong> ' + escapeHtml(d.test_error) + '<br>';
                // v20.4-fix12: AI 错误时显示"重置熔断器"按钮
                html += '<button id="cos-reset-circuit-btn" style="background: #dc2626; color: #fff; border: none; padding: 4px 12px; border-radius: 4px; font-size: 11px; cursor: pointer; margin-top: 6px;">🔄 重置 AI 熔断器</button>';
            }
            html += '</div>';
            diagResult.innerHTML = html;
            // v20.4-fix12: 绑定重置熔断器按钮
            var resetBtn = document.getElementById('cos-reset-circuit-btn');
            if (resetBtn) resetBtn.addEventListener('click', resetCircuit);
        }).catch(function(err){
            diagResult.innerHTML = '<div style="padding: 8px; color: #991b1b; font-size: 12px;">诊断请求失败: ' + escapeHtml(String(err)) + '</div>';
        });
    }

    // v20.4-fix12: 重置 AI 熔断器
    function resetCircuit() {
        if (!confirm('确认重置所有 AI provider 的熔断器?\n\n这会清除所有 provider 的失败计数, 让被熔断的 provider 立即恢复可用。\n\n适用场景: AI 曾因超时失败触发熔断, 但 API 已恢复, 想立即重试。')) return;
        post('linked3_cos_reset_circuit', {}).then(function(res){
            if (res.success && res.data) {
                alert(res.data.message + '\n\n现在可以重新运行杠杆链了。');
            } else {
                alert('重置失败: ' + (res.data?.message || '未知错误'));
            }
        }).catch(function(err){
            alert('重置请求失败: ' + String(err.message || err));
        });
    }

    // v20.4-fix6: 演化成功后自动推荐杠杆并勾选
    function autoRecommendLevers(problem, domain, approach) {
        post('linked3_cos_recommend_levers', {
            problem: problem,
            approach: approach,
            domain: domain
        }).then(function(res){
            if (!res.success || !res.data || !res.data.recommended) return;
            // 先取消所有勾选
            document.querySelectorAll('.cos-lever-checkbox').forEach(function(cb){ cb.checked = false; });
            // 勾选推荐的杠杆
            res.data.recommended.forEach(function(l){
                var cb = document.querySelector('.cos-lever-checkbox[value="' + l.id + '"]');
                if (cb) cb.checked = true;
            });
            // 显示推荐提示
            var chainSection = document.getElementById('cos-lever-chain');
            if (chainSection) {
                var tip = document.createElement('div');
                tip.style.cssText = 'width:100%;margin-top:8px;padding:8px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:11px;color:#1e40af;';
                tip.innerHTML = '✨ 已根据你的问题自适配推荐 ' + res.data.recommended.length + ' 个杠杆 (已勾选)。可直接点击"运行杠杆链"。';
                // 移除旧提示
                var oldTip = chainSection.querySelector('.cos-recommend-tip');
                if (oldTip) oldTip.remove();
                tip.className = 'cos-recommend-tip';
                chainSection.appendChild(tip);
            }
        });
    }

    function renderEvolveResult(data) {
        var html = '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px;">';
        html += '<div style="font-size: 14px; font-weight: 600; color: #166534; margin-bottom: 12px;">✅ 演化完成 — ' + (data.final_status || 'unknown') + '</div>';
        if (data.final_mvp) {
            html += '<div style="background: #fff; padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #10b981;">';
            html += '<div style="font-size: 13px; font-weight: 600; color: #1f2937;">🏆 MVP: ' + escapeHtml(data.final_mvp.id || '') + ' (适应度 ' + (data.final_mvp.fitness || 0) + ')</div>';
            // v20.4: 显示真实方案内容
            if (data.final_mvp.approach) {
                html += '<div style="font-size: 12px; color: #374151; margin-top: 8px; line-height: 1.6; white-space: pre-wrap;">' + escapeHtml(data.final_mvp.approach) + '</div>';
            }
            // v20.4: 显示执行步骤
            if (data.final_mvp.steps) {
                html += '<div style="font-size: 11px; color: #6b7280; margin-top: 8px; padding: 6px; background: #f9fafb; border-radius: 4px;"><strong>执行步骤:</strong><br>' + escapeHtml(data.final_mvp.steps) + '</div>';
            }
            // v20.4: 显示评分明细
            if (data.final_mvp.score) {
                var s = data.final_mvp.score;
                html += '<div style="font-size: 10px; color: #9ca3af; margin-top: 6px;">评分: 风险=' + (s.risk||0) + ' · 可行=' + (s.feasibility||0) + ' · 新颖=' + (s.novelty||0) + '</div>';
            }
            html += '</div>';
        }
        if (data.generations && data.generations.length) {
            html += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">三代演化详情:</div>';
            data.generations.forEach(function(g){
                var color = g.status === 'pass' ? '#10b981' : '#ef4444';
                html += '<div style="display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">';
                html += '<span style="background: ' + (g.generation === 'G1' ? '#3b82f6' : (g.generation === 'G2' ? '#8b5cf6' : '#ec4899')) + '; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">' + g.generation + '</span>';
                html += '<span style="color: ' + color + '; font-size: 14px;">' + (g.status === 'pass' ? '✓' : '✗') + '</span>';
                html += '<span style="font-size: 12px; color: #4b5563; flex: 1;">' + escapeHtml(g.message || '') + '</span>';
                html += '</div>';
            });
        }
        html += '<div style="margin-top: 10px; padding: 8px; background: #fef3c7; border-radius: 6px; font-size: 12px; color: #92400e;">';
        html += '💡 <strong>下一步:</strong> 滚动到下方"Skill 库", 找到刚结晶的 Skill, 点击"🚀 应用"按钮生成 system_prompt';
        html += '</div>';
        html += '</div>';
        resultDiv.innerHTML = html;
    }

    // ── STEP 3+4: Skill 应用与删除 ──
    document.addEventListener('click', function(e){
        if (e.target.classList.contains('cos-apply-skill-btn')) {
            var name = e.target.getAttribute('data-name');
            applySkill(name);
        }
        if (e.target.classList.contains('cos-delete-skill-btn')) {
            var name = e.target.getAttribute('data-name');
            if (confirm('确认删除 Skill: ' + name + '?')) {
                deleteSkill(name);
            }
        }
    });

    function applySkill(name) {
        var resultEl = document.getElementById('cos-skill-applied-result');
        resultEl.style.display = 'block';
        resultEl.innerHTML = '<div style="padding: 12px; text-align: center; color: #6b7280; font-size: 13px;">生成 system_prompt 中...</div>';
        post('linked3_cos_apply_skill', {name: name, task_type: 'xhs_generate'}).then(function(res){
            if (res.success && res.data) {
                var d = res.data;
                var html = '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px;">';
                html += '<div style="font-size: 13px; font-weight: 600; color: #166534; margin-bottom: 8px;">✅ Skill 已应用 — ' + escapeHtml(d.message) + '</div>';
                html += '<div style="font-size: 11px; color: #6b7280; margin-bottom: 8px;">适应度: ' + d.fitness + ' · 使用次数: ' + d.usage_count + ' · 固化规则: ' + (d.rules_count || 0) + ' 条</div>';
                // v20.4: 显示方案预览
                if (d.approach_preview) {
                    html += '<div style="font-size: 11px; color: #374151; margin-bottom: 8px; padding: 8px; background: #fff; border-radius: 6px; border-left: 3px solid #10b981;"><strong>方案预览:</strong> ' + escapeHtml(d.approach_preview) + (d.approach_preview.length >= 200 ? '...' : '') + '</div>';
                }
                html += '<div style="font-size: 11px; color: #374151; margin-bottom: 6px; font-weight: 600;">📋 生成的 system_prompt (可复制到生成器):</div>';
                html += '<textarea style="width: 100%; height: 160px; font-family: monospace; font-size: 11px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical;" readonly onclick="this.select()">' + escapeHtml(d.system_prompt) + '</textarea>';
                html += '<div style="font-size: 11px; color: #6b7280; margin-top: 6px;">💡 复制上方文本, 粘贴到小红书/SEO/长文/视频生成器的 system_prompt 字段即可使用</div>';
                html += '</div>';
                resultEl.innerHTML = html;
                refreshDashboard();
            } else {
                resultEl.innerHTML = '<div style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 13px;">❌ ' + (res.data?.message || '应用失败') + '</div>';
            }
        });
    }

    function deleteSkill(name) {
        post('linked3_cos_delete_skill', {name: name}).then(function(res){
            if (res.success) {
                refreshDashboard();
            } else {
                alert('删除失败: ' + (res.data?.message || '未知错误'));
            }
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── STEP 5: 杠杆链 (v20.4-fix10: 分块串行调用, 每个杠杆一个 AJAX 请求) ──
    // 旧实现 (fix9) 在单个 PHP 请求里串行跑 6 个 AI 调用 (最长 360s),
    // 超过 web server / PHP-FPM 超时 (通常 60-120s) → 连接被掐断 → "TypeError: Failed to fetch"。
    // fix10: 改为前端逐个调用 ajax_run_lever, 每个请求 ≤60s, 并实时渲染进度。
    var chainBtn = document.getElementById('cos-run-chain-btn');
    var chainResult = document.getElementById('cos-chain-result');
    // v20.4-fix12: 绑定永久"重置熔断器"按钮
    var resetCircuitPermBtn = document.getElementById('cos-reset-circuit-perm-btn');
    if (resetCircuitPermBtn) resetCircuitPermBtn.addEventListener('click', resetCircuit);

    // v20.4-fix25: 场景选择器绑定
    var scenePresets = {
        'auto': null, // 自动适配, 调用后端
        'ecommerce': ['meta_essence', 'meta_creativity', 'meta_strategy', 'meta_evaluation', 'content_engine', 'risk_defense'],
        'content': ['meta_creativity', 'meta_metaphor', 'content_engine', 'meta_communication', 'meta_critique', 'meta_evaluation'],
        'tech': ['meta_essence', 'meta_system', 'meta_logic', 'meta_stress_test', 'cognitive_audit', 'meta_evaluation'],
        'strategy': ['meta_strategy', 'meta_reverse', 'meta_system', 'deep_strategy', 'risk_defense', 'meta_execution'],
        'audit': ['meta_essence', 'meta_critique', 'meta_evaluation', 'socratic_review', 'cognitive_audit', 'meta_execution'],
        'innovation': ['meta_creativity', 'meta_crossover', 'meta_metaphor', 'cross_innovation', 'meta_reverse', 'meta_folding'],
        'risk': ['meta_stress_test', 'meta_causal', 'meta_game', 'meta_ethics', 'risk_defense', 'meta_self_calibration'],
    };

    document.querySelectorAll('.cos-scene-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var scene = this.getAttribute('data-scene');
            // 更新按钮样式
            document.querySelectorAll('.cos-scene-btn').forEach(function(b){
                b.style.background = '#f3f4f6';
                b.style.color = '#374151';
                b.style.border = '1px solid #d1d5db';
            });
            this.style.background = '#2563eb';
            this.style.color = '#fff';
            this.style.border = 'none';

            if (scene === 'auto') {
                // 自动适配: 调用后端推荐
                var problem = document.querySelector('textarea[name="cos_problem"], #cos-problem-input, #cos_problem')?.value || '';
                var domain = document.querySelector('select[name="cos_domain"], #cos-domain-select')?.value || '';
                post('linked3_cos_recommend_levers', {problem: problem, approach: '', domain: domain}).then(function(res){
                    if (res.success && res.data && res.data.recommended) {
                        // 先取消所有勾选
                        document.querySelectorAll('.cos-lever-checkbox').forEach(function(cb){ cb.checked = false; });
                        // 勾选推荐的杠杆
                        res.data.recommended.forEach(function(r){
                            var cb = document.querySelector('.cos-lever-checkbox[value="' + r.id + '"]');
                            if (cb) cb.checked = true;
                        });
                    }
                }).catch(function(){});
            } else {
                // 手动场景: 使用预设
                var preset = scenePresets[scene] || [];
                // 先取消所有勾选
                document.querySelectorAll('.cos-lever-checkbox').forEach(function(cb){ cb.checked = false; });
                // 勾选预设的杠杆
                preset.forEach(function(lid){
                    var cb = document.querySelector('.cos-lever-checkbox[value="' + lid + '"]');
                    if (cb) cb.checked = true;
                });
            }
        });
    });

    chainBtn.addEventListener('click', function(){
        var levers = [];
        document.querySelectorAll('.cos-lever-checkbox:checked').forEach(function(cb){ levers.push(cb.value); });
        if (levers.length === 0) { alert('请至少选择一个杠杆'); return; }

        // v20.4: 收集审查上下文 (问题 + 最近 MVP 方案)
        var problem = document.querySelector('textarea[name="cos_problem"], #cos-problem-input, #cos_problem')?.value || '';
        var approach = '';
        var steps = '';
        var skillName = '';

        // 尝试从最近应用的 Skill 结果中获取 approach
        var appliedTextarea = document.querySelector('#cos-skill-applied-result textarea');
        if (appliedTextarea) {
            var promptText = appliedTextarea.value;
            var m = promptText.match(/## 最优方案 \(MVP\)\n([\s\S]*?)(\n\n## |\n\n## )/);
            if (m) approach = m[1].trim();
            var m2 = promptText.match(/## 执行步骤\n([\s\S]*?)(\n\n## )/);
            if (m2) steps = m2[1].trim();
        }

        // 尝试从 Skill 表格中获取最近一个 Skill 的 name
        var firstSkillBtn = document.querySelector('.cos-apply-skill-btn[data-name]');
        if (firstSkillBtn) {
            skillName = firstSkillBtn.getAttribute('data-name');
        }

        if (!problem && !approach && !skillName) {
            alert('请先启动演化生成方案, 或在问题描述中输入内容, 再运行杠杆链');
            return;
        }

        chainBtn.disabled = true;
        chainBtn.textContent = '运行中...';
        chainResult.style.display = 'block';

        // v20.4-fix10: 分块串行 — 每个杠杆一个 AJAX 请求, 累积 analysis 传给下一个
        var chainResults = [];
        var accumulated = '';
        var leverLabels = {}; // 缓存杠杆中文名, 用于进度显示

        function renderChainProgress(currentIdx, total, currentLabel) {
            var html = '<div style="padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">';
            html += '<div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 8px;">🔗 杠杆链运行中 (' + (currentIdx + 1) + '/' + total + ')</div>';
            html += '<div style="height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-bottom: 10px;">';
            html += '<div style="height: 100%; width: ' + ((currentIdx / total) * 100) + '%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.3s;"></div>';
            html += '</div>';
            html += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">当前: ' + escapeHtml(currentLabel) + ' (调用 AI 审查中, 约 5-15 秒)...</div>';
            // 已完成的杠杆
            chainResults.forEach(function(r) {
                var ok = r.status === 'success';
                var aiOk = r.ai_status === 'success';
                html += '<div style="padding: 6px 0; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 8px;">';
                html += '<span style="color: ' + (ok ? '#10b981' : '#ef4444') + '; font-size: 13px;">' + (ok ? '✓' : '✗') + '</span>';
                html += '<span style="font-size: 11px; font-weight: 600; color: #1f2937; min-width: 120px;">' + escapeHtml(r.lever_name || r.lever) + '</span>';
                html += '<span style="font-size: 10px; color: ' + (aiOk ? '#10b981' : '#f59e0b') + '; background: ' + (aiOk ? '#ecfdf5' : '#fffbeb') + '; padding: 2px 6px; border-radius: 4px;">' + (aiOk ? 'AI 已调用' : '降级模式') + '</span>';
                html += '</div>';
            });
            html += '</div>';
            chainResult.innerHTML = html;
        }

        function runOneLever(idx, retryCount) {
            if (idx >= levers.length) {
                // 全部完成, 渲染最终结果
                var finalData = assembleChainResult(chainResults, accumulated, problem, approach, steps);
                renderChainResult(finalData);
                chainBtn.disabled = false;
                chainBtn.textContent = '▶ 运行杠杆链';
                return;
            }

            var leverId = levers[idx];
            var label = leverLabels[leverId] || leverId;
            renderChainProgress(idx, levers.length, label);

            var postData = {
                lever_id: leverId,
                problem: problem,
                approach: approach,
                steps: steps,
                accumulated_analysis: accumulated,
            };
            if (skillName) postData.skill_name = skillName;

            post('linked3_cos_run_lever', postData).then(function(res){
                if (res.success && res.data) {
                    var r = res.data;
                    // 缓存杠杆中文名
                    if (r.lever_name) leverLabels[leverId] = r.lever_name;
                    chainResults.push(r);
                    // 累积 analysis 传给下一个杠杆 (链式增强)
                    if (r.status === 'success' && r.accumulated_analysis) {
                        accumulated = r.accumulated_analysis;
                    } else if (r.analysis) {
                        accumulated += '\n\n--- ' + (r.lever_name || leverId) + ' ---\n' + r.analysis;
                    }
                } else {
                    // v20.4-fix25: 自动重试3次 (retryCount=0→1→2→3), 每次间隔递增
                    if (retryCount < 3) {
                        var delay = (retryCount + 1) * 2000; // 2s, 4s, 6s
                        setTimeout(function(){ runOneLever(idx, retryCount + 1); }, delay);
                        return;
                    }
                    // 重试3次仍失败, 记录错误继续下一个
                    chainResults.push({
                        lever: leverId,
                        lever_name: label,
                        status: 'error',
                        ai_status: 'error: ' + (res.data?.message || 'AJAX 失败'),
                        analysis: '杠杆调用失败 (重试3次后): ' + escapeHtml(res.data?.message || '未知错误'),
                    });
                }
                // v20.4-fix25: 杠杆间延迟 2.5 秒, 避免连续请求触发熔断器
                setTimeout(function(){ runOneLever(idx + 1, 0); }, 2500);
            }).catch(function(err){
                // v20.4-fix25: 网络错误也自动重试3次
                if (retryCount < 3) {
                    var delay = (retryCount + 1) * 2000;
                    setTimeout(function(){ runOneLever(idx, retryCount + 1); }, delay);
                    return;
                }
                // 重试3次仍失败, 不中断整条链
                chainResults.push({
                    lever: leverId,
                    lever_name: label,
                    status: 'error',
                    ai_status: 'error: network',
                    analysis: '网络错误 (重试3次后): ' + escapeHtml(String(err.message || err)),
                });
                setTimeout(function(){ runOneLever(idx + 1, 0); }, 2500);
            });
        }

        // v20.4-fix10: 前端组装最终增强 prompt (与后端 chain_levers 逻辑一致)
        // v20.4-fix13: 清理累积分析中的乱码, 确保最终 prompt 干净可读
        function assembleChainResult(results, acc, prob, appr, stp) {
            var enhanced = '你是一个经过认知操作系统 (COS) 三代演化 + 杠杆链深度审查的专家。\n\n';
            enhanced += '<rules>\n';
            enhanced += '输出≤3×原始 | 装饰≤20% | 核心目标不偏离 | 杠杆使命不可违\n';
            enhanced += '公理刚性：需求必由[信息熵减]+[系统降维]推导 | 证伪至死：风险>8或可行<4直接抹杀\n';
            enhanced += '纳什均衡：信息密度与系统降维的平衡点 | 用户目的性优先于技术优雅\n';
            enhanced += '落地性：每条建议必须含具体操作步骤或工具示例, 禁止抽象方向\n';
            enhanced += '差异化：各杠杆审查结论已去重, 请综合而非重复\n';
            enhanced += '</rules>\n\n';
            enhanced += '## 原始问题\n' + prob + '\n\n';
            enhanced += '## 最优方案 (MVP)\n' + appr + '\n\n';
            if (stp) {
                enhanced += '## 执行步骤\n' + stp + '\n\n';
            }
            enhanced += '## 杠杆链审查结论 (经 ' + results.length + ' 个元认知杠杆深度审查)\n';
            enhanced += '以下是各杠杆对方案的审查分析, 请在执行时严格遵守其中的修正建议:\n\n';
            enhanced += cleanAiOutput(acc);
            enhanced += '\n\n## 工作要求\n';
            enhanced += '<answer_operator>\n';
            enhanced += 'Analyze(综合审查) → Synthesize(纳什均衡) → Recommend(可落地步骤) → Verify(用户价值) → Execute\n';
            enhanced += '</answer_operator>\n';
            enhanced += '1. 基于上述方案和审查结论完成用户的内容生成任务\n';
            enhanced += '2. 优先采纳杠杆链审查中指出的修正方向\n';
            enhanced += '3. 规避审查中识别的盲区和风险\n';
            enhanced += '4. 始终以用户目的为锚点, 输出必须可落地执行\n';
            enhanced += '5. 在信息密度与系统降维之间找到纳什均衡点\n';
            return {
                results: results,
                final_enhanced_prompt: enhanced,
                accumulated_analysis: acc,
            };
        }

        // 启动第一个杠杆
        runOneLever(0, 0);
    });

    // v20.4-fix13: 清理 AI 输出 — 去掉 JSON 代码块、多余空格、重复字符
    function cleanAiOutput(text) {
        if (!text) return '';
        var cleaned = String(text);
        // 去掉 ```json ... ``` 和 ``` ... ``` 代码块标记
        cleaned = cleaned.replace(/```json\s*/gi, '').replace(/```\s*/g, '');
        // 去掉行首尾的 { } [ ] (JSON 残留)
        cleaned = cleaned.replace(/^[\s{}[\]]+/, '').replace(/[\s{}[\]]+$/, '');
        // 去掉连续 3 个以上的空格 (乱码特征)
        cleaned = cleaned.replace(/ {3,}/g, ' ');
        // 去掉连续 3 个以上的换行
        cleaned = cleaned.replace(/\n{3,}/g, '\n\n');
        // 去掉连续 5 个以上的相同字符 (重复乱码, 如 """"")
        cleaned = cleaned.replace(/(.)\1{4,}/g, '$1$1');
        // 去掉行首的引号残留
        cleaned = cleaned.replace(/^\s*["""]+/gm, '');
        // trim
        cleaned = cleaned.trim();
        return cleaned;
    }

    function renderChainResult(data) {
        var results = data.results || [];
        var html = '<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px;">';
        html += '<div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;">🔗 杠杆链结果 (' + results.length + ' 个杠杆 · 链式增强)</div>';
        html += '<div style="font-size: 11px; color: #6b7280; margin-bottom: 10px;">每个杠杆真实调用 AI 审查方案, 前一杠杆的输出作为后一杠杆的输入, 形成认知增强链。</div>';
        results.forEach(function(r, idx){
            var ok = r.status === 'success';
            var aiOk = r.ai_status === 'success';
            html += '<div style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">';
            html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">';
            html += '<span style="color: ' + (ok ? '#10b981' : '#ef4444') + '; font-size: 14px;">' + (ok ? '✓' : '✗') + '</span>';
            html += '<span style="font-size: 12px; font-weight: 600; color: #1f2937; min-width: 140px;">' + escapeHtml(r.lever_name || r.lever) + '</span>';
            html += '<span style="font-size: 10px; color: ' + (aiOk ? '#10b981' : '#f59e0b') + '; background: ' + (aiOk ? '#ecfdf5' : '#fffbeb') + '; padding: 2px 6px; border-radius: 4px;">' + (aiOk ? 'AI 已调用' : '降级模式') + '</span>';
            html += '</div>';
            if (r.analysis) {
                // v20.4-fix13: 清理 AI 输出后再显示
                var cleanAnalysis = cleanAiOutput(r.analysis);
                html += '<div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; font-size: 12px; color: #374151; max-height: 250px; overflow-y: auto; white-space: pre-wrap; line-height: 1.6;">' + escapeHtml(cleanAnalysis) + '</div>';
            }
            html += '</div>';
        });
        // 汇总: 最终增强后的 system_prompt
        if (data.final_enhanced_prompt) {
            // v20.4-fix13: 清理最终 prompt 中的乱码
            var cleanPrompt = cleanAiOutput(data.final_enhanced_prompt);
            // v20.4-fix24: 生成唯一ID用于复制功能
            var promptId = 'cos-final-prompt-' + Date.now();
            html += '<div style="margin-top: 10px; padding: 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px;">';
            html += '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">';
            html += '<div style="font-size: 12px; font-weight: 600; color: #1e40af;">💎 杠杆链增强后的最终 system_prompt (可复制)</div>';
            html += '<button type="button" onclick="var t=document.getElementById(\'' + promptId + '\');t.select();document.execCommand(\'copy\');this.textContent=\'✓ 已复制\';setTimeout(function(){this.textContent=\'📋 一键复制\'}.bind(this),2000);" style="background: #2563eb; color: #fff; border: none; padding: 4px 12px; border-radius: 4px; font-size: 11px; cursor: pointer; white-space: nowrap;">📋 一键复制</button>';
            html += '</div>';
            html += '<textarea id="' + promptId + '" style="width: 100%; height: 150px; font-family: monospace; font-size: 11px; padding: 8px; border: 1px solid #93c5fd; border-radius: 6px; resize: vertical; line-height: 1.5;" readonly onclick="this.select()">' + escapeHtml(cleanPrompt) + '</textarea>';
            html += '</div>';
            html += '</div>';
        }
        html += '</div>';
        chainResult.innerHTML = html;
    }

    // ── 刷新仪表盘 ──
    function refreshDashboard() {
        post('linked3_cos_dashboard', {}).then(function(res){
            if (!res.success || !res.data) return;
            var d = res.data;
            // v20.4-fix3: dashboard AJAX 返回 {overview, top_skills, recent_evolutions}
            // 统计数据嵌套在 overview 里, 不是顶层
            var ov = d.overview || d;
            var skillEl = document.getElementById('cos-stat-skills');
            var evoEl   = document.getElementById('cos-stat-evolutions');
            var rateEl  = document.getElementById('cos-stat-success-rate');
            if (skillEl) skillEl.textContent = ov.skill_count || 0;
            if (evoEl) evoEl.textContent = ov.evolution_count || 0;
            if (rateEl) rateEl.textContent = Math.round((ov.evolution_success_rate || 0) * 100) + '%';
            var byGen = ov.by_generation || {G1: 0, G2: 0, G3: 0};
            var g1El = document.getElementById('cos-gen-g1-count');
            var g2El = document.getElementById('cos-gen-g2-count');
            var g3El = document.getElementById('cos-gen-g3-count');
            if (g1El) g1El.textContent = byGen.G1 || 0;
            if (g2El) g2El.textContent = byGen.G2 || 0;
            if (g3El) g3El.textContent = byGen.G3 || 0;
        });
        post('linked3_cos_skills', {}).then(function(res){
            if (!res.success || !res.data) return;
            renderSkillsList(res.data.skills || [], res.data.stats || {});
        });
        post('linked3_cos_archive', {n: 10}).then(function(res){
            if (!res.success || !res.data) return;
            renderArchiveList(res.data.recent || []);
        });
    }

    function renderSkillsList(skills, stats) {
        var container = document.getElementById('cos-skills-list');
        if (!container) return;
        var keys = Object.keys(skills);
        if (keys.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 32px; color: #9ca3af; font-size: 13px;"><div style="font-size: 32px; margin-bottom: 8px; opacity: 0.4;">💎</div>暂无 Skill — 在上方"演化控制台"启动一次演化即可结晶</div>';
            return;
        }
        var html = '<div style="font-size: 11px; color: #6b7280; margin-bottom: 8px;">平均适应度: ' + (stats.avg_fitness || 0).toFixed(1) + ' · 共 ' + keys.length + ' 个 Skill</div>';
        html += '<table style="width: 100%; font-size: 12px; border-collapse: collapse;">';
        // v20.4-fix3: 添加方案预览列, 与 PHP 渲染的表头一致
        html += '<thead><tr style="background: #f9fafb;"><th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">Skill</th><th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">适应度</th><th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">使用</th><th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">领域</th><th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">问题</th><th style="text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb;">方案预览</th><th style="padding: 8px; border-bottom: 2px solid #e5e7eb; text-align: center;">操作</th></tr></thead><tbody>';
        keys.forEach(function(name){
            var s = skills[name];
            // v20.4-fix3: 方案预览
            var approachPreview = (s.mvp_approach || '').substring(0, 40);
            if (!approachPreview) approachPreview = '(空)';
            html += '<tr><td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-weight: 600; font-family: monospace; font-size: 11px;">' + escapeHtml(name) + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center;"><span style="background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-weight: 600;">' + (s.fitness || 0).toFixed(1) + '</span></td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; color: #6b7280;">' + (s.usage_count || 0) + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; font-size: 11px; color: #6b7280;">' + escapeHtml(s.domain || '-') + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; color: #6b7280; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + escapeHtml(s.problem || '') + '">' + escapeHtml((s.problem || '').substring(0, 30)) + '</td>';
            // v20.4-fix3: 方案预览列
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; color: #374151; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + escapeHtml(s.mvp_approach || '') + '">' + escapeHtml(approachPreview) + '</td>';
            html += '<td style="padding: 8px; border-bottom: 1px solid #f3f4f6; text-align: center; white-space: nowrap;"><button class="cos-apply-skill-btn" data-name="' + escapeHtml(name) + '" style="background: #10b981; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; margin-right: 4px;">🚀 应用</button><button class="cos-delete-skill-btn" data-name="' + escapeHtml(name) + '" style="background: #ef4444; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">🗑 删除</button></td></tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function renderArchiveList(recent) {
        var container = document.getElementById('cos-archive-list');
        if (!container) return;
        var arr = Object.keys(recent).map(function(k){ return recent[k]; });
        if (arr.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 24px; color: #9ca3af; font-size: 13px;"><div style="font-size: 28px; margin-bottom: 8px; opacity: 0.4;">📚</div>暂无演化记录 — 启动一次演化即可生成归档</div>';
            return;
        }
        var html = '<div style="font-size: 11px; color: #6b7280; margin-bottom: 8px;">最近 ' + arr.length + ' 条记录</div>';
        arr.forEach(function(snap){
            var genColor = snap.generation === 'G1' ? '#3b82f6' : (snap.generation === 'G2' ? '#8b5cf6' : '#ec4899');
            html += '<div style="padding: 10px; border-bottom: 1px solid #f3f4f6;">';
            html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">';
            html += '<span style="background: ' + genColor + '; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">' + snap.generation + '</span>';
            html += '<span style="font-size: 12px; color: #6b7280; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + (snap.problem || '').substring(0, 50) + '</span>';
            html += '<span style="font-size: 10px; color: #9ca3af;">' + (snap.saved_at || '') + '</span>';
            html += '</div>';
            html += '<div style="font-size: 11px; color: #9ca3af; padding-left: 32px;">方案 ' + (snap.variants_count || 0) + ' · 存活 ' + (snap.survivors_count || 0) + ' · 绞杀 ' + (snap.killed_count || 0);
            if (snap.mvp) { html += ' · MVP: ' + (snap.mvp.id || '') + ' (适应度 ' + (snap.mvp.fitness || 0) + ')'; }
            html += '</div></div>';
        });
        container.innerHTML = html;
    }
})();
