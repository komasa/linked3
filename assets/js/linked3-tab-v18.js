/**
 * Linked3 V18 Tab JS
 * Extracted from: admin/views/dashboard/partials/tab-v18.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-v18.js
 * Localized via wp_localize_script('linked3-tab-v18', 'linked3_v18', {...})
 *   Keys: ajax_url, nonce
 */

jQuery(document).ready(function($) {
    var ajaxUrl = 'window.linked3_v18.ajax_url';
    var nonce = 'window.linked3_v18.nonce';

    // v18复审 I1/S2: 统一错误处理
    function handleAjaxError($target, resp, textStatus, errorThrown) {
        var msg = '请求失败';
        if (resp && resp.data && resp.data.message) {
            msg = resp.data.message;
        } else if (errorThrown) {
            msg = String(errorThrown);
        } else if (textStatus) {
            msg = textStatus;
        }
        $target.html('<div class="notice notice-error inline"><p>❌ ' + escapeHtml(msg) + '</p></div>');
    }

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    // v18复审 I1/S2: 统一结果渲染器 — JSON→卡片化(保留JSON切换)
    function renderV18Result($target, data, viewType, customRenderer) {
        if (viewType === true || viewType === 'json') {
            // 原始JSON视图
            $target.html('<pre style="background:#f5f5f5;padding:15px;border-radius:4px;overflow-x:auto;font-size:12px;">' +
                escapeHtml(JSON.stringify(data, null, 2)) + '</pre>');
            return;
        }
        // 卡片化视图
        if (typeof customRenderer === 'function') {
            $target.html(customRenderer(data));
        } else {
            // 默认卡片: 遍历第一层键值
            var html = '<div style="background:#fff;border:1px solid #E4E4E7;border-radius:8px;padding:16px;">';
            html += renderObjectAsCard(data, 0);
            html += '</div>';
            $target.html(html);
        }
    }

    function renderObjectAsCard(obj, depth) {
        if (depth > 3) return '<span style="color:#A1A1AA;font-size:11px;">[深层对象已折叠]</span>';
        if (obj == null) return '<span style="color:#A1A1AA;">null</span>';
        if (typeof obj !== 'object') return escapeHtml(String(obj));
        if (Array.isArray(obj)) {
            if (obj.length === 0) return '<span style="color:#A1A1AA;">[]</span>';
            var html = '<ul style="margin:4px 0;padding-left:20px;list-style:disc;">';
            obj.slice(0, 20).forEach(function(item) {
                html += '<li style="font-size:13px;margin:2px 0;">' + renderObjectAsCard(item, depth + 1) + '</li>';
            });
            if (obj.length > 20) html += '<li style="color:#A1A1AA;font-size:11px;">... 共' + obj.length + '项</li>';
            html += '</ul>';
            return html;
        }
        var html = '<dl style="margin:4px 0;">';
        var count = 0;
        for (var k in obj) {
            if (!obj.hasOwnProperty(k) || count >= 30) continue;
            var v = obj[k];
            var labelStyle = 'font-weight:600;color:#0f172a;font-size:13px;';
            if (typeof v === 'object' && v !== null) {
                html += '<dt style="' + labelStyle + 'margin-top:8px;">' + escapeHtml(k) + ':</dt>';
                html += '<dd style="margin:2px 0 2px 16px;">' + renderObjectAsCard(v, depth + 1) + '</dd>';
            } else {
                html += '<dt style="' + labelStyle + 'margin-top:4px;display:inline;">' + escapeHtml(k) + ':</dt> ';
                html += '<span style="font-size:13px;color:#334155;">' + escapeHtml(String(v)) + '</span><br>';
            }
            count++;
        }
        html += '</dl>';
        return html;
    }

    // v18复审 E1: 100天计划定制渲染器
    function renderRuliuPlan(data) {
        if (!data || !data.phases) return '<p style="color:#DC2626;">计划数据为空</p>';
        var html = '';
        // 用户输入回显
        if (data.user_input) {
            var ui = data.user_input;
            html += '<div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:6px;padding:12px;margin-bottom:16px;">';
            html += '<h4 style="margin:0 0 8px;color:#065f46;">✅ 已为你生成定制计划</h4>';
            html += '<div style="font-size:13px;color:#064e3b;line-height:1.8;">';
            html += '👤 职业: <strong>' + escapeHtml(ui.profession || '未填写') + '</strong> &nbsp;|&nbsp; ';
            html += '🎯 赛道: <strong>' + escapeHtml(ui.track || '未填写') + '</strong> &nbsp;|&nbsp; ';
            html += '🏆 目标: <strong>' + escapeHtml(ui.goal || '未填写') + '</strong> &nbsp;|&nbsp; ';
            html += '📱 平台: <strong>' + escapeHtml(ui.platform || '公众号') + '</strong> &nbsp;|&nbsp; ';
            html += '📅 当前: <strong>第' + (ui.current_day || 1) + '天</strong>';
            html += '</div></div>';
        }
        // 当前进度
        if (data.current_state) {
            var cs = data.current_state;
            html += '<div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:6px;padding:12px;margin-bottom:16px;">';
            html += '<div style="font-size:14px;color:#92400E;"><strong>📍 当前状态: ' + escapeHtml(cs.state_label || '') + '</strong></div>';
            html += '<div style="font-size:12px;color:#78350f;margin-top:4px;">本阶段第' + (cs.day_in_state || 1) + '天/' + (cs.state_total_days || 1) + '天 (' + (cs.state_progress_pct || 0) + '%) | 总进度: 第' + (cs.overall_day || 1) + '天 (' + (cs.overall_progress_pct || 0) + '%)</div>';
            html += '<div style="background:#e5e7eb;border-radius:4px;height:8px;margin-top:6px;overflow:hidden;"><div style="background:#F59E0B;height:100%;width:' + (cs.overall_progress_pct || 0) + '%;"></div></div>';
            html += '</div>';
        }
        // 四阶段卡片
        html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;">';
        (data.phases || []).forEach(function(phase) {
            var borderColor = phase.is_current ? '#0F172A' : '#E4E4E7';
            var bgColor = phase.is_current ? '#FAFAFA' : '#fff';
            html += '<div style="border:2px solid ' + borderColor + ';border-radius:8px;padding:14px;background:' + bgColor + ';">';
            html += '<h4 style="margin:0 0 6px;color:#18181B;">' + escapeHtml(phase.label) + ' <span style="font-size:11px;color:#71717A;font-weight:normal;">(第' + phase.day_range[0] + '-' + phase.day_range[1] + '天)</span>';
            if (phase.is_current) html += ' <span style="background:#0F172A;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;">当前</span>';
            html += '</h4>';
            html += '<p style="font-size:12px;color:#71717A;margin:4px 0;">' + escapeHtml(phase.desc || '') + '</p>';
            html += '<div style="font-size:12px;color:#52525B;margin-top:8px;"><strong>核心动作:</strong> ' + escapeHtml(phase.core_action || '') + '</div>';
            html += '<div style="font-size:12px;margin-top:8px;"><strong style="color:#52525B;">具体行动:</strong>';
            html += '<ul style="margin:4px 0;padding-left:18px;font-size:12px;color:#334155;">';
            (phase.actions || []).forEach(function(a) { html += '<li>' + escapeHtml(a) + '</li>'; });
            html += '</ul></div>';
            // 周排期
            if (phase.weeks && phase.weeks.length > 0) {
                html += '<details style="margin-top:8px;"><summary style="font-size:11px;color:#0F172A;cursor:pointer;">📅 每周排期(' + phase.weeks.length + '周)</summary>';
                html += '<table style="width:100%;font-size:11px;margin-top:6px;border-collapse:collapse;">';
                html += '<tr style="background:#F4F4F5;"><th style="padding:3px;border:1px solid #E4E4E7;">周</th><th style="padding:3px;border:1px solid #E4E4E7;">天数</th><th style="padding:3px;border:1px solid #E4E4E7;">重点</th></tr>';
                phase.weeks.forEach(function(w) {
                    html += '<tr><td style="padding:3px;border:1px solid #E4E4E7;text-align:center;">W' + w.week + '</td>';
                    html += '<td style="padding:3px;border:1px solid #E4E4E7;text-align:center;">' + w.day_range[0] + '-' + w.day_range[1] + '</td>';
                    html += '<td style="padding:3px;border:1px solid #E4E4E7;">' + escapeHtml(w.focus) + '</td></tr>';
                });
                html += '</table></details>';
            }
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // 逆向拆解
    $('#v18-reverse-btn').on('click', function() {
        var $result = $('#v18-reverse-result');
        var input = $('#v18-reverse-input').val();
        var type = $('#v18-reverse-type').val();
        var viewJson = $('#v18-reverse-view-json').is(':checked');
        if (!input.trim()) { $result.html('<div class="notice notice-warning inline"><p>请输入要逆向拆解的内容</p></div>'); return; }
        $result.html('<p style="color:#71717A;">⏳ 逆向拆解中（8维度DNA提取）...</p>');
        // v18复审修复: action名+参数名对齐后端 (reverse_execute→reverse_parse, input→json_raw, type→engineer_type)
        $.post(ajaxUrl, {
            action: 'linked3_reverse_parse',
            nonce: nonce,
            json_raw: input,
            engineer_type: type
        }, function(resp) {
            if (resp && resp.success) {
                renderV18Result($result, resp.data, viewJson, function(data) {
                    // 逆向拆解定制渲染: 8维度DNA卡片
                    var html = '<div style="background:#fff;border:1px solid #E4E4E7;border-radius:8px;padding:16px;">';
                    html += '<h4 style="margin:0 0 12px;color:#18181B;">🔍 逆向拆解结果 — 8维度DNA</h4>';
                    var result = data.result || data;
                    if (data.error) {
                        html += '<div class="notice notice-error inline"><p>' + escapeHtml(data.error) + '</p></div>';
                    } else if (result && typeof result === 'object') {
                        html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">';
                        var dims = ['D1_整体风格','D2_角色DNA','D3_色彩系统','D4_构图镜头','D5_文字元素','D6_场景背景','D7_文化符号','D8_META标签'];
                        dims.forEach(function(dim) {
                            if (result[dim] || (result.dimensions && result.dimensions[dim])) {
                                var val = result[dim] || result.dimensions[dim];
                                html += '<div style="background:#FAFAFA;border-left:3px solid #6366f1;padding:10px;border-radius:4px;">';
                                html += '<div style="font-size:12px;font-weight:600;color:#4f46e5;margin-bottom:4px;">' + escapeHtml(dim) + '</div>';
                                html += '<div style="font-size:13px;color:#18181B;line-height:1.5;">' + escapeHtml(typeof val === 'object' ? JSON.stringify(val) : String(val)) + '</div>';
                                html += '</div>';
                            }
                        });
                        html += '</div>';
                        // 可复用Prompt
                        if (result.reusable_prompt || result.meta_prompt) {
                            html += '<div style="margin-top:14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:6px;padding:12px;">';
                            html += '<div style="font-size:12px;font-weight:600;color:#065f46;margin-bottom:6px;">✨ 可复用Prompt</div>';
                            html += '<pre style="background:#fff;padding:10px;border-radius:4px;font-size:12px;white-space:pre-wrap;max-height:200px;overflow-y:auto;">' + escapeHtml(result.reusable_prompt || result.meta_prompt) + '</pre>';
                            html += '</div>';
                        }
                    } else {
                        html += renderObjectAsCard(data, 0);
                    }
                    html += '</div>';
                    return html;
                });
            } else {
                handleAjaxError($result, resp, 'error', 'Request failed');
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            handleAjaxError($result, null, textStatus, errorThrown);
        });
    });

    // SVG统计
    $('#v18-svg-stats-btn').on('click', function() {
        var $result = $('#v18-svg-stats-result');
        var viewJson = $('#v18-svg-view-json').is(':checked');
        $result.html('<p style="color:#71717A;">⏳ 获取统计基线中...</p>');
        $.post(ajaxUrl, {
            action: 'linked3_svg_stats',
            nonce: nonce
        }, function(resp) {
            if (resp && resp.success) {
                renderV18Result($result, resp.data, viewJson, function(data) {
                    // SVG统计定制渲染
                    var html = '<div style="background:#fff;border:1px solid #E4E4E7;border-radius:8px;padding:16px;">';
                    html += '<h4 style="margin:0 0 12px;color:#18181B;">📊 SVG原子级meta统计基线</h4>';
                    if (data.summary) {
                        html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:14px;">';
                        for (var k in data.summary) {
                            html += '<div style="background:#FAFAFA;border-radius:6px;padding:10px;text-align:center;">';
                            html += '<div style="font-size:11px;color:#71717A;">' + escapeHtml(k) + '</div>';
                            html += '<div style="font-size:18px;font-weight:700;color:#18181B;margin-top:4px;">' + escapeHtml(String(data.summary[k])) + '</div>';
                            html += '</div>';
                        }
                        html += '</div>';
                    }
                    html += renderObjectAsCard(data, 0);
                    html += '</div>';
                    return html;
                });
            } else {
                handleAjaxError($result, resp, 'error', 'Request failed');
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            handleAjaxError($result, null, textStatus, errorThrown);
        });
    });

    // v18复审 E1/S1: 入流追踪(带用户输入)
    $('#v18-ruliu-btn').on('click', function() {
        var $result = $('#v18-ruliu-result');
        var viewJson = $('#v18-ruliu-view-json').is(':checked');
        var profession = $('#v18-ruliu-profession').val();
        var track = $('#v18-ruliu-track').val();
        var goal = $('#v18-ruliu-goal').val();
        var platform = $('#v18-ruliu-platform').val();
        var currentDay = $('#v18-ruliu-day').val();

        if (!profession.trim() && !track.trim() && !goal.trim()) {
            $result.html('<div class="notice notice-warning inline"><p>💡 请至少填写"职业"或"赛道"，以便生成个性化计划。</p></div>');
            return;
        }

        $result.html('<p style="color:#71717A;">⏳ 正在为你生成定制100天计划...</p>');
        $.post(ajaxUrl, {
            action: 'linked3_ruliu_plan',
            nonce: nonce,
            profession: profession,
            track: track,
            goal: goal,
            platform: platform,
            current_day: currentDay
        }, function(resp) {
            if (resp && resp.success) {
                renderV18Result($result, resp.data, viewJson, renderRuliuPlan);
            } else {
                handleAjaxError($result, resp, 'error', 'Request failed');
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            handleAjaxError($result, null, textStatus, errorThrown);
        });
    });

    // v18复审: 入流进度更新
    $('#v18-ruliu-update-btn').on('click', function() {
        var $result = $('#v18-ruliu-status-result');
        var day = $('#v18-ruliu-update-day').val();
        if (!day || day < 1 || day > 100) { $result.html('<div class="notice notice-warning inline"><p>请输入1-100之间的天数</p></div>'); return; }
        $result.html('<p style="color:#71717A;">⏳ 更新进度...</p>');
        $.post(ajaxUrl, { action: 'linked3_ruliu_update', nonce: nonce, day: day }, function(resp) {
            if (resp && resp.success) {
                // 更新后立即获取状态
                $.post(ajaxUrl, { action: 'linked3_ruliu_status', nonce: nonce, day: day }, function(resp2) {
                    if (resp2 && resp2.success) {
                        renderV18Result($result, resp2.data, false, function(data) {
                            var s = data.status || data;
                            var html = '<div style="background:#fff;border:1px solid #a7f3d0;border-radius:8px;padding:14px;">';
                            html += '<div style="font-size:14px;font-weight:600;color:#065f46;margin-bottom:8px;">✅ 进度已更新 — 第' + escapeHtml(String(day)) + '天</div>';
                            if (s.current_state || s.state) {
                                html += '<div style="font-size:13px;margin:4px 0;">📍 当前阶段: <strong>' + escapeHtml(s.state_label || s.current_state || s.state || '') + '</strong></div>';
                            }
                            if (s.overall_progress_pct !== undefined) {
                                html += '<div style="margin:8px 0;"><div style="background:#E4E4E7;border-radius:4px;height:20px;overflow:hidden;"><div style="background:linear-gradient(90deg,#6366f1,#06b6d4);height:100%;width:' + s.overall_progress_pct + '%;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:600;">' + s.overall_progress_pct + '%</div></div></div>';
                            }
                            if (s.state_progress_pct !== undefined) {
                                html += '<div style="font-size:12px;color:#71717A;">阶段内进度: ' + s.state_progress_pct + '% (' + (s.day_in_state||0) + '/' + (s.state_total_days||0) + '天)</div>';
                            }
                            html += '</div>';
                            return html;
                        });
                    } else {
                        $result.html('<div class="notice notice-success inline"><p>✅ 第' + day + '天进度已记录</p></div>');
                    }
                }).fail(function(){ $result.html('<div class="notice notice-success inline"><p>✅ 第' + day + '天进度已记录</p></div>'); });
            } else {
                handleAjaxError($result, resp, 'error', 'Request failed');
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            handleAjaxError($result, null, textStatus, errorThrown);
        });
    });

    // v18复审: 查看当前状态
    $('#v18-ruliu-status-btn').on('click', function() {
        var $result = $('#v18-ruliu-status-result');
        var day = $('#v18-ruliu-update-day').val();
        $result.html('<p style="color:#71717A;">⏳ 查询状态...</p>');
        $.post(ajaxUrl, { action: 'linked3_ruliu_status', nonce: nonce, day: day }, function(resp) {
            if (resp && resp.success) {
                renderV18Result($result, resp.data, false, function(data) {
                    var s = data.status || data;
                    var html = '<div style="background:#fff;border:1px solid #E4E4E7;border-radius:8px;padding:14px;">';
                    html += '<div style="font-size:14px;font-weight:600;color:#18181B;margin-bottom:8px;">📍 第' + escapeHtml(String(day)) + '天状态</div>';
                    if (s.state_label || s.current_state) {
                        html += '<div style="font-size:13px;margin:4px 0;">当前阶段: <strong style="color:#6366f1;">' + escapeHtml(s.state_label || s.current_state || '') + '</strong></div>';
                    }
                    if (s.overall_progress_pct !== undefined) {
                        html += '<div style="margin:8px 0;"><div style="background:#E4E4E7;border-radius:4px;height:20px;overflow:hidden;"><div style="background:linear-gradient(90deg,#6366f1,#06b6d4);height:100%;width:' + s.overall_progress_pct + '%;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:600;">' + s.overall_progress_pct + '%</div></div></div>';
                    }
                    html += '</div>';
                    return html;
                });
            } else {
                handleAjaxError($result, resp, 'error', 'Request failed');
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            handleAjaxError($result, null, textStatus, errorThrown);
        });
    });

    // v18复审: 三层能观 — 分配频率
    $('#v18-consciousness-assign-btn').on('click', function() {
        var $result = $('#v18-consciousness-result');
        var moduleType = $('#v18-consciousness-module-type').val();
        var content = $('#v18-consciousness-content').val();
        var viewJson = $('#v18-consciousness-view-json').is(':checked');
        $result.html('<p style="color:#71717A;">⏳ 分配视觉频率...</p>');
        $.post(ajaxUrl, { action: 'linked3_frequency_assign', nonce: nonce, module_type: moduleType, content: content }, function(resp) {
            if (resp && resp.success) {
                renderV18Result($result, resp.data, viewJson, function(data) {
                    var freq = data.frequency || '';
                    var freqColors = { 'HF': '#F59E0B', 'MF': '#0F172A', 'LF': '#71717A' };
                    var freqDesc = { 'HF': '高频·洞察/灵感 (亮色, 画面顶部)', 'MF': '中频·逻辑/方法 (中性色, 画面中部)', 'LF': '低频·信息/背景 (冷暗色, 画面底部)' };
                    var color = freqColors[freq] || '#808080';
                    var html = '<div style="background:#fff;border:1px solid #E4E4E7;border-radius:8px;padding:16px;">';
                    html += '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">';
                    html += '<div style="background:' + color + ';color:#fff;padding:6px 16px;border-radius:20px;font-size:16px;font-weight:700;">[' + escapeHtml(freq) + ']</div>';
                    html += '<div><div style="font-size:14px;font-weight:600;color:#18181B;">' + escapeHtml(data.badge_label || '') + '</div>';
                    html += '<div style="font-size:12px;color:#71717A;">' + escapeHtml(freqDesc[freq] || '') + '</div></div>';
                    html += '</div>';
                    html += '<div style="background:#FAFAFA;padding:10px;border-radius:6px;font-size:12px;color:#52525B;">';
                    html += '<strong>使用建议:</strong> 将此模块放在画面' + (freq==='HF'?'顶部1/3':freq==='MF'?'中部1/3':'底部1/3') + '区域，使用' + (freq==='HF'?'暖亮':'MF'===freq?'中性':'冷暗') + '色调，确保全图从HF→MF→LF递进分布。';
                    html += '</div>';
                    html += '</div>';
                    return html;
                });
            } else {
                handleAjaxError($result, resp, 'error', 'Request failed');
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            handleAjaxError($result, null, textStatus, errorThrown);
        });
    });

    // v18复审: 能知三阶 — 检测认知层级
    $('#v18-nengzhi-detect-btn').on('click', function() {
        var $result = $('#v18-nengzhi-result');
        var content = $('#v18-nengzhi-content').val();
        var viewJson = $('#v18-nengzhi-view-json').is(':checked');
        if (!content.trim()) { $result.html('<div class="notice notice-warning inline"><p>请输入要检测的内容文本</p></div>'); return; }
        $result.html('<p style="color:#71717A;">⏳ 检测认知层级...</p>');
        $.post(ajaxUrl, { action: 'linked3_nengzhi_detect', nonce: nonce, content: content }, function(resp) {
            if (resp && resp.success) {
                renderV18Result($result, resp.data, viewJson, function(data) {
                    var stage = data.detected_stage || '';
                    var level = data.cognitive_level || '';
                    var stageColors = { 'stage_1': '#10B981', 'stage_2': '#0F172A', 'stage_3': '#7C3AED' };
                    var stageInfo = {
                        'stage_1': { label: '一阶·时空意识', reader: '入门读者(R)', adapt: '大白话+图示为主, 术语<10%' },
                        'stage_2': { label: '二阶·逻辑意识', reader: '进阶读者(A)', adapt: '法条+判例为主, 术语可占40%' },
                        'stage_3': { label: '三阶·纯粹意识', reader: '专家读者(E)', adapt: '法理+哲学, 术语可占60%' }
                    };
                    var info = stageInfo[stage] || {};
                    var color = stageColors[stage] || '#808080';
                    var html = '<div style="background:#fff;border:1px solid #E4E4E7;border-radius:8px;padding:16px;">';
                    html += '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">';
                    html += '<div style="background:' + color + ';color:#fff;padding:6px 16px;border-radius:20px;font-size:14px;font-weight:700;">' + escapeHtml(level) + '</div>';
                    html += '<div><div style="font-size:14px;font-weight:600;color:#18181B;">' + escapeHtml(info.label || data.stage_label || '') + '</div>';
                    html += '<div style="font-size:12px;color:#71717A;">目标读者: ' + escapeHtml(info.reader || '') + '</div></div>';
                    html += '</div>';
                    html += '<div style="background:#FAFAFA;padding:10px;border-radius:6px;font-size:12px;color:#52525B;margin-bottom:8px;">';
                    html += '<strong>内容适配建议:</strong> ' + escapeHtml(info.adapt || '');
                    html += '</div>';
                    if (data.scores) {
                        html += '<div style="font-size:11px;color:#A1A1AA;">关键词匹配得分: 一阶=' + (data.scores.stage_1||0) + ' 二阶=' + (data.scores.stage_2||0) + ' 三阶=' + (data.scores.stage_3||0) + ' (置信度:' + escapeHtml(data.confidence||'') + ')</div>';
                    }
                    html += '</div>';
                    return html;
                });
            } else {
                handleAjaxError($result, resp, 'error', 'Request failed');
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            handleAjaxError($result, null, textStatus, errorThrown);
        });
    });

    // v18复审: 能知三阶 — 查看三阶说明
    $('#v18-nengzhi-stages-btn').on('click', function() {
        var $result = $('#v18-nengzhi-result');
        var viewJson = $('#v18-nengzhi-view-json').is(':checked');
        $result.html('<p style="color:#71717A;">⏳ 加载三阶说明...</p>');
        $.post(ajaxUrl, { action: 'linked3_nengzhi_stages', nonce: nonce }, function(resp) {
            if (resp && resp.success) {
                renderV18Result($result, resp.data, viewJson, function(data) {
                    var stages = data.stages || data.three_stages || data;
                    var html = '<div style="background:#fff;border:1px solid #E4E4E7;border-radius:8px;padding:16px;">';
                    html += '<h4 style="margin:0 0 12px;color:#18181B;">能知三阶说明</h4>';
                    if (Array.isArray(stages)) {
                        html += '<div style="display:grid;gap:10px;">';
                        stages.forEach(function(s, i) {
                            var colors = ['#10B981','#0F172A','#7C3AED'];
                            html += '<div style="border-left:4px solid ' + colors[i] + ';padding:10px;background:#FAFAFA;border-radius:4px;">';
                            html += '<div style="font-weight:600;color:#18181B;font-size:13px;">' + escapeHtml(s.label || s.key || ('阶段'+(i+1))) + '</div>';
                            html += '<div style="font-size:12px;color:#71717A;margin-top:4px;">' + escapeHtml(s.desc || s.description || '') + '</div>';
                            html += '</div>';
                        });
                        html += '</div>';
                    } else {
                        html += renderObjectAsCard(data, 0);
                    }
                    html += '</div>';
                    return html;
                });
            } else {
                handleAjaxError($result, resp, 'error', 'Request failed');
            }
        }).fail(function(xhr, textStatus, errorThrown) {
            handleAjaxError($result, null, textStatus, errorThrown);
        });
    });
});
