/**
 * Linked3 Cloud Tab JS
 * Extracted from: admin/views/dashboard/partials/tab-cloud.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-cloud.js
 * Localized via wp_localize_script('linked3-tab-cloud', 'linked3_cloud', {...})
 *   Keys: ajax_url, nonce, templates_url, industry_variants
 */

(function(){
    // v11.6.1: A/B变体数据 (PHP注入)
    var abData = window.linked3_cloud.industry_variants;
    var sel = document.getElementById('lk3-ab-category');
    var result = document.getElementById('lk3-ab-result');
    if (!sel || !result) return;

    function renderAB(cat) {
        var variants = abData[cat] || [];
        if (!variants.length) { result.innerHTML = '<p style="color:#9ca3af;">无数据</p>'; return; }
        result.innerHTML = variants.map(function(v){
            var cfg = (v.template && v.template.config) || {};
            return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:10px;">'
                + '<div style="font-size:14px;margin-bottom:6px;">' + v.industry_icon + ' <strong>' + v.industry_label + '</strong></div>'
                + '<div style="font-size:10px;color:#71717A;margin-bottom:4px;"><strong>角色:</strong> ' + (cfg.role || '—').substring(0, 40) + '</div>'
                + '<div style="font-size:10px;color:#71717A;margin-bottom:4px;"><strong>风格:</strong> ' + (cfg.style || '—') + '</div>'
                + '<div style="font-size:10px;color:#71717A;"><strong>目标:</strong> ' + ((cfg.goals || []).slice(0,2).join(', ') || '—') + '</div>'
                + '</div>';
        }).join('');
    }
    sel.addEventListener('change', function(){ renderAB(this.value); });
    renderAB(sel.value);
})();

(function(){
    var ajaxUrl = 'window.linked3_cloud.ajax_url';
    var nonce = 'window.linked3_cloud.nonce';
    function escHtml(s){s=String(s==null?'':s);return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

    // Fork母版到写作生态本地
    document.querySelectorAll('.cloud-fork-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var cat = btn.getAttribute('data-cat');
            var source = btn.getAttribute('data-source');
            var masterId = btn.getAttribute('data-master-id');
            if (!confirm('确认Fork此母版到写作生态本地? (本地副本可修改, 不影响母版)')) return;

            var fd = new FormData();
            fd.append('action', 'linked3_cloud_fork');
            fd.append('nonce', nonce);
            fd.append('category', cat);
            fd.append('source', source);
            fd.append('master_id', masterId);

            btn.disabled = true;
            btn.textContent = 'Forking...';

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                .then(function(data){
                    btn.disabled = false;
                    btn.textContent = '📥 Fork到写作生态';
                    var status = document.getElementById('cloud-status');
                    if (data.success) {
                        var ecoUrl = 'window.linked3_cloud.templates_url';
                        status.innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + ' <a href="' + ecoUrl + '">→ 去编辑本地模版</a></p></div>';
                        setTimeout(function(){ location.reload(); }, 2000);
                    } else {
                        status.innerHTML = '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : 'Fork失败') + '</p></div>';
                    }
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '📥 Fork到写作生态';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    });

    // v10.8.0: 解锁内置母版 (= Fork可编辑副本, 母版保持锁定)
    document.querySelectorAll('.cloud-unlock-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var cat = btn.getAttribute('data-cat');
            if (!confirm('解锁内置母版 [' + cat + ']?\n\n解锁 = 创建一个可编辑的本地副本 (Fork),\n内置母版本身保持锁定不变。\n\n修改请编辑本地副本。')) return;

            var fd = new FormData();
            fd.append('action', 'linked3_cloud_fork');
            fd.append('nonce', nonce);
            fd.append('category', cat);
            fd.append('source', 'builtin');

            btn.disabled = true;
            btn.textContent = '解锁中...';

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                .then(function(data){
                    btn.disabled = false;
                    btn.textContent = '🔓 解锁编辑';
                    var status = document.getElementById('cloud-status');
                    if (data.success) {
                        status.innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p><p>母版已解锁, 页面刷新后可编辑本地副本。</p></div>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        status.innerHTML = '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '解锁失败') + '</p></div>';
                    }
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '🔓 解锁编辑';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    });

    // 预览母版
    document.querySelectorAll('.cloud-preview-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var cat = btn.getAttribute('data-cat');
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_preview');
            fd.append('nonce', nonce);
            fd.append('category', cat);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        var tpl = data.data.template;
                        document.getElementById('cloud-preview-title').textContent = '母版预览: ' + escHtml(tpl.name || cat);
                        var html = '<table class="widefat"><tbody>';
                        Object.keys(tpl).forEach(function(k){
                            var v = tpl[k];
                            if (typeof v === 'object') v = JSON.stringify(v, null, 2);
                            html += '<tr><th style="width:100px;">' + escHtml(String(k)) + '</th><td><pre style="white-space:pre-wrap;margin:0;font-size:12px;">' + escHtml(String(v)) + '</pre></td></tr>';
                        });
                        html += '</tbody></table>';
                        document.getElementById('cloud-preview-body').innerHTML = html;
                        document.getElementById('cloud-preview-dialog').style.display = 'block';
                    }
                });
        });
    });

    // 添加母版对话框
    document.getElementById('cloud-add-master').addEventListener('click', function(){
        document.getElementById('cloud-master-title').textContent = '添加自定义母版';
        document.getElementById('cloud-master-edit-id').value = '';
        ['name','profile','role','scene','style','goals','output'].forEach(function(f){
            var el = document.getElementById('cloud-master-' + f);
            if (el) el.value = '';
        });
        document.getElementById('cloud-master-dialog').style.display = 'block';
    });

    // 编辑母版
    document.querySelectorAll('.cloud-edit-master-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var mid = btn.getAttribute('data-master-id');
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_preview');
            fd.append('nonce', nonce);
            fd.append('master_id', mid);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        var tpl = data.data.template;
                        document.getElementById('cloud-master-title').textContent = '编辑自定义母版';
                        document.getElementById('cloud-master-edit-id').value = mid;
                        document.getElementById('cloud-master-name').value = tpl.name || '';
                        document.getElementById('cloud-master-type').value = tpl.type || 'content';
                        var c = tpl.config || {};
                        document.getElementById('cloud-master-profile').value = c.profile || '';
                        document.getElementById('cloud-master-role').value = c.role || '';
                        document.getElementById('cloud-master-scene').value = c.scene || '';
                        document.getElementById('cloud-master-style').value = c.style || '';
                        document.getElementById('cloud-master-goals').value = Array.isArray(c.goals) ? c.goals.join(', ') : '';
                        document.getElementById('cloud-master-output').value = c.output || '';
                        document.getElementById('cloud-master-dialog').style.display = 'block';
                    }
                });
        });
    });

    // 保存母版
    document.getElementById('cloud-master-save').addEventListener('click', function(){
        var editId = document.getElementById('cloud-master-edit-id').value;
        var tplData = {
            name: document.getElementById('cloud-master-name').value,
            type: document.getElementById('cloud-master-type').value,
            config: {
                profile: document.getElementById('cloud-master-profile').value,
                role: document.getElementById('cloud-master-role').value,
                scene: document.getElementById('cloud-master-scene').value,
                style: document.getElementById('cloud-master-style').value,
                goals: document.getElementById('cloud-master-goals').value,
                output: document.getElementById('cloud-master-output').value,
            }
        };
        if (!tplData.name) { alert('请输入母版名称'); return; }

        var fd = new FormData();
        fd.append('action', 'linked3_cloud_master_save');
        fd.append('nonce', nonce);
        fd.append('master_id', editId);
        fd.append('template', JSON.stringify(tplData));

        var saveBtn = document.getElementById('cloud-master-save');
        saveBtn.disabled = true;
        saveBtn.textContent = '保存中...';

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(function(data){
                saveBtn.disabled = false;
                saveBtn.textContent = '保存母版';
                if (data.success) {
                    document.getElementById('cloud-master-dialog').style.display = 'none';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '保存失败') + '</p></div>';
                }
            })
            .catch(function(e){
                saveBtn.disabled = false;
                saveBtn.textContent = '保存母版';
                document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
            });
    });

    // 删除母版
    document.querySelectorAll('.cloud-del-master-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var mid = btn.getAttribute('data-master-id');
            if (!confirm('确认删除此自定义母版? (内置母版不可删除, 已Fork的本地副本不受影响)')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_master_delete');
            fd.append('nonce', nonce);
            fd.append('master_id', mid);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        document.getElementById('cloud-status').innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    }
                });
        });
    });

    // 删除本地Fork
    document.querySelectorAll('.cloud-del-fork-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var fid = btn.getAttribute('data-fork-id');
            if (!confirm('确认删除此本地实例? (不影响母版)')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_fork_delete');
            fd.append('nonce', nonce);
            fd.append('fork_id', fid);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        document.getElementById('cloud-status').innerHTML = '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    }
                });
        });
    });

    // v10.8.1: 同步 (生产→本地: 拉取母版最新内容覆盖本地Fork)
    document.querySelectorAll('.cloud-sync-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var fid = btn.getAttribute('data-fork-id');
            if (!confirm('确认从源母版同步最新内容?\n\n注意: 本地修改将被覆盖!')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_sync_to_local');
            fd.append('nonce', nonce);
            fd.append('fork_id', fid);

            btn.disabled = true; btn.textContent = '同步中...';
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    btn.disabled = false; btn.textContent = '🔄 同步';
                    var msg = data.success ? '✅ ' + escHtml(data.data.message) : escHtml(data.data && data.data.message ? data.data.message : '同步失败');
                    document.getElementById('cloud-status').innerHTML = '<div class="notice ' + (data.success ? 'notice-success' : 'notice-error') + ' inline"><p>' + msg + '</p></div>';
                    if (data.success) setTimeout(function(){ location.reload(); }, 1500);
                })
                .catch(function(e){
                    btn.disabled = false; btn.textContent = '🔄 同步';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    });

    // v10.8.1: 收录 (本地→生产: 将本地Fork提升为自定义母版)
    document.querySelectorAll('.cloud-promote-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var fid = btn.getAttribute('data-fork-id');
            if (!confirm('确认将此本地副本收录为自定义母版?\n\n收录后, 该模版将出现在母版库, 可被所有生态Fork使用。')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_cloud_promote');
            fd.append('nonce', nonce);
            fd.append('fork_id', fid);

            btn.disabled = true; btn.textContent = '收录中...';
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    btn.disabled = false; btn.textContent = '⬆ 收录';
                    var msg = data.success ? '✅ ' + escHtml(data.data.message) : escHtml(data.data && data.data.message ? data.data.message : '收录失败');
                    document.getElementById('cloud-status').innerHTML = '<div class="notice ' + (data.success ? 'notice-success' : 'notice-error') + ' inline"><p>' + msg + '</p></div>';
                    if (data.success) setTimeout(function(){ location.reload(); }, 1500);
                })
                .catch(function(e){
                    btn.disabled = false; btn.textContent = '⬆ 收录';
                    document.getElementById('cloud-status').innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    });
})();
