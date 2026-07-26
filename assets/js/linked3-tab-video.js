/**
 * Linked3 Video Tab JS
 * Extracted from: admin/views/dashboard/partials/tab-video.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-video.js
 * Localized via wp_localize_script('linked3-tab-video', 'linked3_video', {...})
 *   Keys: ajax_url, nonce, publish_url
 */

(function(){
    'use strict';
    var nonce = 'window.linked3_video.nonce';
    var ajaxUrl = 'window.linked3_video.ajax_url';

    // 字数统计
    var scriptEl = document.getElementById('linked3-video-script');
    var statsEl = document.getElementById('lk3-video-script-stats');
    if (scriptEl && statsEl) {
        scriptEl.addEventListener('input', function() {
            statsEl.textContent = scriptEl.value.length + ' 字';
        });
    }

    // Motion手动/自动切换
    var motionAutoSel = document.getElementById('linked3-video-motion-auto');
    var motionManual = document.getElementById('lk3-video-motion-manual');
    if (motionAutoSel && motionManual) {
        motionAutoSel.addEventListener('change', function() {
            motionManual.style.display = this.value === 'no' ? 'block' : 'none';
        });
    }

    // 生成按钮
    var genBtn = document.getElementById('linked3-video-gen');
    var statusEl = document.getElementById('linked3-video-status');
    var resultStage = document.getElementById('lk3-video-result-stage');
    var resultEl = document.getElementById('linked3-video-result');

    if (genBtn) {
        genBtn.addEventListener('click', function() {
            var script = document.getElementById('linked3-video-script').value.trim();
            if (!script || script.length < 20) {
                alert('请输入至少20字的剧本');
                return;
            }

            genBtn.disabled = true;
            genBtn.textContent = '⏳ 生成中...';
            statusEl.textContent = '正在生成视频脚本...';
            resultEl.innerHTML = '<div style="text-align:center;padding:30px;color:#71717A;"><div class="spinner is-active" style="float:none;margin:0 auto 10px;"></div>正在拆解剧本, 生成首尾帧+Motion Prompt...</div>';
            resultStage.style.display = 'block';

            var fd = new FormData();
            fd.append('action', 'linked3_video_generate_v10');
            fd.append('nonce', nonce);
            fd.append('script', script);
            fd.append('style', document.getElementById('linked3-video-style').value);
            fd.append('group_count', document.getElementById('linked3-video-group-count').value);
            fd.append('split_mode', document.getElementById('linked3-video-split-mode').value);
            fd.append('motion_auto', document.getElementById('linked3-video-motion-auto').value);
            fd.append('seed_refs', document.getElementById('linked3-video-seed-refs').value);

            if (document.getElementById('linked3-video-motion-auto').value === 'no') {
                fd.append('camera', document.getElementById('lk3-video-camera').value);
                fd.append('action_type', document.getElementById('lk3-video-action').value);
                fd.append('speed', document.getElementById('lk3-video-speed').value);
                fd.append('atmosphere', document.getElementById('lk3-video-atmosphere').value);
            }

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    genBtn.disabled = false;
                    genBtn.textContent = '🎬 生成视频脚本';
                    if (!res.success) {
                        statusEl.textContent = '✗ 生成失败';
                        statusEl.style.color = '#DC2626';
                        resultEl.innerHTML = '<div style="color:#DC2626;padding:12px;">✗ ' + escapeHtml(res.data.message || '生成失败') + '</div>';
                        return;
                    }
                    statusEl.textContent = '✓ 生成完成, 共 ' + (res.data.total_groups || 0) + ' 组';
                    statusEl.style.color = '#16a34a';
                    renderVideoResult(res.data);
                })
                .catch(function(e) {
                    genBtn.disabled = false;
                    genBtn.textContent = '🎬 生成视频脚本';
                    statusEl.textContent = '✗ 网络错误';
                    statusEl.style.color = '#DC2626';
                    resultEl.innerHTML = '<div style="color:#DC2626;padding:12px;">✗ ' + escapeHtml(e.message) + '</div>';
                });
        });
    }

    function renderVideoResult(data) {
        var groups = data.groups || [];
        var html = '';

        // 概览
        html += '<div style="background:#F4F4F5;border:1px solid #86efac;padding:10px 12px;margin-bottom:12px;border-radius:6px;">';
        html += '<strong style="color:#16a34a;">✓ 视频脚本生成成功</strong> — ' + (data.total_groups || 0) + '组, 每组5-10秒, 总时长约' + ((data.total_groups || 0) * 7) + '秒';
        html += '</div>';

        // 批量操作
        html += '<div style="margin-bottom:12px;">';
        html += '<button class="lk3-video-btn lk3-video-btn-sm" id="lk3-video-copy-all">📋 复制全部</button> ';
        html += '<button class="lk3-video-btn lk3-video-btn-sm" id="lk3-video-download-all">⬇️ 下载全部</button> ';
        // v11.8.0: SOP闭环 — 保存草稿 + 去发布
        html += '<button class="lk3-video-btn lk3-video-btn-sm" id="lk3-video-save-draft">💾 保存为草稿</button> ';
        html += '<a href="window.linked3_video.publish_url" class="lk3-video-btn lk3-video-btn-sm" style="text-decoration:none;display:inline-block;">📤 去发布</a>';
        html += '</div>';

        // 分组卡片
        groups.forEach(function(g, idx) {
            var arcColor = ['#0F172A','#F59E0B','#EF4444','#10B981'][idx % 4];
            html += '<div class="lk3-video-group-card" style="border-left-color:' + arcColor + ';">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
            html += '<div>';
            html += '<span style="background:' + arcColor + ';color:#fff;padding:2px 6px;border-radius:3px;font-weight:bold;font-size:11px;">组' + (idx + 1) + '</span> ';
            html += '<span style="font-size:12px;color:#71717A;">' + escapeHtml(g.arc_position || '') + ' · ' + escapeHtml(g.emotion || '') + '</span>';
            html += '</div>';
            html += '<span style="font-size:10px;color:#A1A1AA;">转场: ' + escapeHtml(g.transition || '') + '</span>';
            html += '</div>';

            if (g.beat_text) {
                html += '<div style="font-size:11px;color:#52525B;margin-bottom:6px;background:#FAFAFA;padding:4px 8px;border-radius:3px;">📝 ' + escapeHtml(g.beat_text) + '</div>';
            }

            // v11.2.0 #2: 动态变化预览 (首尾帧差异对比)
            var firstFrame = g.first_frame || '';
            var lastFrame = g.last_frame || '';
            var motionPrompt = g.motion_prompt || '';
            html += '<div style="margin-bottom:8px;padding:8px;background:#FEF3C7;border:1px solid #F59E0B;border-radius:4px;">';
            html += '<div style="font-size:11px;font-weight:600;color:#92400E;margin-bottom:4px;">🎬 动态变化预览 (首帧→尾帧)</div>';
            html += '<div style="font-size:11px;color:#71717A;line-height:1.6;">';
            html += '<strong>首帧状态:</strong> ' + escapeHtml((firstFrame.match(/about to[^,。]+/) || ['动作起始状态'])[0]) + '<br>';
            html += '<strong>尾帧状态:</strong> ' + escapeHtml((lastFrame.match(/has [^,。]+/) || ['动作完成状态'])[0]) + '<br>';
            html += '<strong>动态过渡:</strong> ' + escapeHtml(motionPrompt.substring(0, 100)) + (motionPrompt.length > 100 ? '...' : '');
            html += '</div></div>';

            // 首帧
            html += '<div style="margin-bottom:6px;"><div style="font-size:11px;font-weight:600;color:#0F172A;">🟦 首帧 Prompt (粘贴到生图工具)</div>';
            html += '<div class="lk3-video-frame-box"><textarea readonly>' + escapeHtml(firstFrame) + '</textarea></div>';
            var firstCaption = '画面说明: ' + (g.beat_text || '本组首帧画面') + '。情绪: ' + (g.emotion || 'neutral') + '。此帧为动作起始状态, 蓄势待发。';
            html += '<div style="font-size:11px;color:#71717A;background:#FAFAFA;padding:4px 8px;border-radius:3px;margin-top:2px;border-left:3px solid #0F172A;">💬 ' + escapeHtml(firstCaption) + '</div>';
            html += '</div>';

            // 尾帧
            html += '<div style="margin-bottom:6px;"><div style="font-size:11px;font-weight:600;color:#10B981;">🟩 尾帧 Prompt (粘贴到生图工具)</div>';
            html += '<div class="lk3-video-frame-box"><textarea readonly>' + escapeHtml(lastFrame) + '</textarea></div>';
            var lastCaption = '画面说明: ' + (g.beat_text || '本组尾帧画面') + '的完成状态。情绪: ' + (g.emotion || 'neutral') + '。此帧为动作结束状态, 与首帧有明显差异。';
            html += '<div style="font-size:11px;color:#71717A;background:#ecfdf5;padding:4px 8px;border-radius:3px;margin-top:2px;border-left:3px solid #10B981;">💬 ' + escapeHtml(lastCaption) + '</div>';
            html += '</div>';

            // Motion Prompt
            html += '<div style="margin-bottom:6px;"><div style="font-size:11px;font-weight:600;color:#7c3aed;">🎬 Motion Prompt (上传智谱清言, 2图间运动)</div>';
            html += '<div class="lk3-video-motion-box"><textarea readonly>' + escapeHtml(g.motion_prompt || '') + '</textarea></div>';
            // v11.0.7 #10: Motion图说
            var motionCaption = '运动说明: 描述首帧→尾帧之间的动态变化。转场: ' + (g.transition || '默认') + '。将首尾帧2张图+此Motion Prompt上传智谱清言, 生成5-10秒视频。';
            html += '<div style="font-size:11px;color:#71717A;background:#FAFAFA;padding:4px 8px;border-radius:3px;margin-top:2px;border-left:3px solid #7c3aed;">💬 ' + escapeHtml(motionCaption) + '</div>';
            html += '</div>';

            html += '</div>';
        });

        resultEl.innerHTML = html;

        // 批量操作绑定
        var copyAll = document.getElementById('lk3-video-copy-all');
        if (copyAll) {
            copyAll.addEventListener('click', function() {
                var parts = groups.map(function(g, i) {
                    return '=== 组' + (i+1) + ' ===\n【首帧】\n' + (g.first_frame||'') + '\n\n【尾帧】\n' + (g.last_frame||'') + '\n\n【Motion】\n' + (g.motion_prompt||'');
                });
                navigator.clipboard.writeText(parts.join('\n\n---\n\n')).then(function() {
                    alert('已复制 ' + groups.length + ' 组视频脚本');
                });
            });
        }
        var dlBtn = document.getElementById('lk3-video-download-all');
        if (dlBtn) {
            dlBtn.addEventListener('click', function() {
                var parts = groups.map(function(g, i) {
                    return '=== 组' + (i+1) + ' ===\n【首帧】\n' + (g.first_frame||'') + '\n\n【尾帧】\n' + (g.last_frame||'') + '\n\n【Motion】\n' + (g.motion_prompt||'');
                });
                var blob = new Blob([parts.join('\n\n---\n\n')], {type:'text/plain'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'video-script-' + Date.now() + '.txt';
                a.click();
                setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
            });
        }

        // v11.8.0: SOP闭环 — 保存为草稿
        var saveDraftBtn = document.getElementById('lk3-video-save-draft');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', function() {
                var parts = groups.map(function(g, i) {
                    return '## 组' + (i+1) + '\n\n### 首帧\n' + (g.first_frame||'') + '\n\n### 尾帧\n' + (g.last_frame||'') + '\n\n### Motion\n' + (g.motion_prompt||'');
                });
                var title = prompt('请输入文章标题:', '视频脚本-' + Date.now());
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

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }
})();
