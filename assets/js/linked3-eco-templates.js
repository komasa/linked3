/**
 * linked3-eco-templates.js
 * Extracted from: admin/views/dashboard/partials/eco-templates.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-templates.js
 * Localized via wp_localize_script('linked3-eco-templates', 'linked3_eco_templates', {...})
 *   Keys: ajax_url, nonce_tpl
 */

(function(){
    var ajax_url = window.linked3_eco_templates && window.linked3_eco_templates.ajax_url || '';
    var nonce_tpl = window.linked3_eco_templates && window.linked3_eco_templates.nonce_tpl || '';
    var local_templates = window.linked3_eco_templates && window.linked3_eco_templates.local_templates || [];
    var local_template_ids = window.linked3_eco_templates && window.linked3_eco_templates.local_template_ids || [];


(function(){
    var ajaxUrl = 'linked3_eco_templates.ajax_url';
    var nonce = 'linked3_eco_templates.nonce_tpl';
    var localTemplates = window.linked3_eco_templates && window.linked3_eco_templates.local_templates || [];
    var localTemplateIds = window.linked3_eco_templates && window.linked3_eco_templates.local_template_ids || [];

    function escHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function fillForm(tpl) {
        var cfg = tpl.config || {};
        document.getElementById('tpl-profile').value = cfg.profile || '';
        document.getElementById('tpl-role').value = cfg.role || '';
        document.getElementById('tpl-scene').value = cfg.scene || '';
        document.getElementById('tpl-background').value = cfg.background || '';
        document.getElementById('tpl-goals').value = (cfg.goals || []).join(',');
        document.getElementById('tpl-skills').value = (cfg.skills || []).join(',');
        document.getElementById('tpl-style').value = cfg.style || '';
        document.getElementById('tpl-limit').value = (cfg.limit || []).join(',');
        document.getElementById('tpl-step').value = (cfg.step || []).join(',');
        document.getElementById('tpl-output').value = cfg.output || '';
    }

    document.addEventListener('DOMContentLoaded', function(){
        // Fork母版到本地
        var forkBtn = document.getElementById('tpl-fork-btn');
        if (forkBtn) {
            forkBtn.addEventListener('click', function(){
                var sourceVal = document.getElementById('tpl-fork-source').value;
                if (!sourceVal) { alert('请选择云模版母版'); return; }

                // 解析 source: "builtin:category" 或 "custom:master_id"
                var parts = sourceVal.split(':');
                var sourceType = parts[0]; // builtin | custom
                var refId = parts[1] || ''; // category 或 master_id

                forkBtn.disabled = true;
                forkBtn.textContent = 'Fork中...';

                var fd = new FormData();
                fd.append('action', 'linked3_cloud_fork');
                fd.append('nonce', nonce);
                fd.append('source', sourceType);
                fd.append('category', refId);
                fd.append('master_id', refId);
                fd.append('fork_name', '本地_' + refId + '_' + Date.now());

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        forkBtn.disabled = false;
                        forkBtn.textContent = '📥 Fork到本地';
                        if (data.success) {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : 'Fork失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        forkBtn.disabled = false;
                        forkBtn.textContent = '📥 Fork到本地';
                        document.getElementById('tpl-status').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
            });
        }

        // 加载本地模版
        var loadBtn = document.getElementById('tpl-load');
        if (loadBtn) {
            loadBtn.addEventListener('click', function(){
                var idx = document.getElementById('tpl-list').value;
                if (idx === '') { alert('请选择本地模版'); return; }
                var tpl = localTemplates[localTemplateIds.indexOf(idx)] || {};
                fillForm(tpl);
                // v11.0.3 #3: 加载后显示完整提示词内容 (不只是模版名)
                var cfg = tpl.config || tpl;
                var promptPreview = '<div class="notice notice-info inline"><p>已加载本地模版: ' + escHtml(tpl.name || '未命名') + (tpl.type ? ' (' + escHtml(tpl.type) + ')' : '') + (tpl.forked_from ? ' [Fork自: ' + escHtml(tpl.forked_from) + ']' : '') + '</p></div>';
                promptPreview += '<div style="margin-top:12px;padding:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">';
                promptPreview += '<h4 style="margin:0 0 8px 0;font-size:13px;color:#3F3F46;">📋 完整提示词内容 (可直接复制使用)</h4>';
                promptPreview += '<pre style="white-space:pre-wrap;font-size:12px;line-height:1.7;color:#1f2937;background:#fff;padding:10px;border-radius:4px;border:1px solid #e5e7eb;max-height:400px;overflow:auto;">';
                var promptText = '';
                promptText += '【Profile】' + (cfg.profile || '-') + '\n';
                promptText += '【Role】' + (cfg.role || '-') + '\n';
                promptText += '【Scene】' + (cfg.scene || '-') + '\n';
                promptText += '【Background】' + (cfg.background || '-') + '\n';
                promptText += '【Goals】' + (Array.isArray(cfg.goals) ? cfg.goals.join('、') : (cfg.goals || '-')) + '\n';
                promptText += '【Skills】' + (Array.isArray(cfg.skills) ? cfg.skills.join('、') : (cfg.skills || '-')) + '\n';
                promptText += '【Style】' + (cfg.style || '-') + '\n';
                promptText += '【Limit】' + (Array.isArray(cfg.limit) ? cfg.limit.join('、') : (cfg.limit || '-')) + '\n';
                promptText += '【Step】' + (Array.isArray(cfg.step) ? cfg.step.join(' → ') : (cfg.step || '-')) + '\n';
                promptText += '【Output】' + (cfg.output || '-');
                promptPreview += escHtml(promptText);
                promptPreview += '</pre>';
                promptPreview += '<button class="button button-small" onclick="var t=this.previousElementSibling;var r=document.createRange();r.selectNode(t);window.getSelection().removeAllRanges();window.getSelection().addRange(r);document.execCommand(\'copy\');this.textContent=\'✅ 已复制\';setTimeout(function(){},2000);" style="margin-top:6px;">📋 复制提示词</button>';
                promptPreview += '</div>';
                document.getElementById('tpl-status').innerHTML = promptPreview;
            });
        }

        // 保存本地模版
        var saveBtn = document.getElementById('tpl-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function(){
                var tplName = prompt('请输入模版名称:', '本地模版_' + new Date().getTime());
                if (!tplName) return;

                var tplData = {
                    name: tplName,
                    type: 'content',
                    config: {
                        profile: document.getElementById('tpl-profile').value,
                        role: document.getElementById('tpl-role').value,
                        scene: document.getElementById('tpl-scene').value,
                        background: document.getElementById('tpl-background').value,
                        goals: document.getElementById('tpl-goals').value.split(',').filter(function(s){return s.trim();}),
                        skills: document.getElementById('tpl-skills').value.split(',').filter(function(s){return s.trim();}),
                        style: document.getElementById('tpl-style').value,
                        limit: document.getElementById('tpl-limit').value.split(',').filter(function(s){return s.trim();}),
                        step: document.getElementById('tpl-step').value.split(',').filter(function(s){return s.trim();}),
                        output: document.getElementById('tpl-output').value
                    }
                };

                saveBtn.disabled = true;
                saveBtn.textContent = '保存中...';

                var fd = new FormData();
                fd.append('action', 'linked3_eco_template_save');
                fd.append('nonce', nonce);
                fd.append('template', JSON.stringify(tplData));

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data){
                        saveBtn.disabled = false;
                        saveBtn.textContent = '保存';
                        if (data.success) {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-success inline"><p>✅ 本地模版已保存: ' + escHtml(tplName) + '</p></div>';
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '保存失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        saveBtn.disabled = false;
                        saveBtn.textContent = '保存';
                        document.getElementById('tpl-status').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
            });
        }

        // 删除本地模版
        var delBtn = document.getElementById('tpl-delete');
        if (delBtn) {
            delBtn.addEventListener('click', function(){
                var idx = document.getElementById('tpl-list').value;
                if (idx === '') { alert('请选择要删除的本地模版'); return; }
                if (!confirm('确认删除此本地模版? (不影响云模版母版)')) return;

                delBtn.disabled = true;
                delBtn.textContent = '删除中...';

                var fd = new FormData();
                fd.append('action', 'linked3_cloud_fork_delete');
                fd.append('nonce', nonce);
                fd.append('fork_id', idx);

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        delBtn.disabled = false;
                        delBtn.textContent = '删除';
                        if (data.success) {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '删除失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        delBtn.disabled = false;
                        delBtn.textContent = '删除';
                        document.getElementById('tpl-status').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
            });
        }
    });
})();

})();
