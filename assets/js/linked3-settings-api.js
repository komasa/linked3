/**
 * linked3-settings-api.js
 * Extracted from: admin/views/settings/api.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-settings-api.js
 * Localized via wp_localize_script('linked3-settings-api', 'linked3_settings_api', {...})
 *   Keys: nonce_settings, ajax_url
 */

(function(){
    var nonce_settings = window.linked3_settings_api && window.linked3_settings_api.nonce_settings || '';
    var ajax_url = window.linked3_settings_api && window.linked3_settings_api.ajax_url || '';
    var img_providers = window.linked3_settings_api && window.linked3_settings_api.img_providers || [];


    // 全局 nonce/ajaxUrl — 供本页所有按钮复用 (v2.6.0 修复作用域问题)
    window.linked3Nonce = linked3_settings_api.nonce_settings;
    window.linked3AjaxUrl = linked3_settings_api.ajax_url;

    // v3.1.0: Provider 配置 AJAX 保存 (不刷新页面)
    (function(){
        var form = document.querySelector('form[action="options.php"]');
        if (!form) return;
        var saveBtn = document.getElementById('linked3-save-provider-config');
        if (!saveBtn) return;

        form.addEventListener('submit', function(e){
            e.preventDefault();
            saveBtn.disabled = true;
            saveBtn.value = '保存中...';
            var status = document.getElementById('linked3-provider-save-status');
            status.textContent = '保存中...';
            status.style.color = '#666';

            var fd = new FormData();
            fd.append('action', 'linked3_save_provider_config');
            fd.append('nonce', window.linked3Nonce);
            fd.append('default_provider', document.getElementById('default_provider').value);
            fd.append('key_rotation', document.getElementById('key_rotation').value);

            // 收集所有 linked3_provider_api_bases[slug] / linked3_provider_models[slug] / linked3_provider_keys[slug]
            var inputs = form.querySelectorAll('input[name], select[name], textarea[name]');
            inputs.forEach(function(inp){
                var name = inp.name;
                if (!name) return;
                // v3.1.1 修复: 匹配带 linked3_ 前缀的字段名
                if (name.indexOf('linked3_provider_api_bases[') === 0 ||
                    name.indexOf('linked3_provider_models[') === 0 ||
                    name.indexOf('linked3_provider_keys[') === 0) {
                    // 去掉 linked3_ 前缀,后端读 provider_keys/provider_models/provider_api_bases
                    var fieldName = name.replace('linked3_', '');
                    fd.append(fieldName, inp.value);
                }
            });

            fetch(window.linked3AjaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){
                    saveBtn.disabled = false;
                    saveBtn.value = '💾 保存默认 AI 服务 + 多 Key 轮询';
                    if (res.success) {
                        status.textContent = '✅ ' + (res.data.message || '已保存');
                        status.style.color = '#080';
                        // v27.8.7: 保存成功后更新 select 的 selected 属性, 防止刷新前回退
                        if (res.data.default_provider) {
                            var dp = document.getElementById('default_provider');
                            if (dp) dp.value = res.data.default_provider;

                            // v27.8.11 (审计Phase1): 保存后自动测试 default provider
                            var testBtn = document.querySelector('.linked3-test-provider[data-provider="' + res.data.default_provider + '"]');
                            if (testBtn) {
                                status.textContent += ' | 正在自动测试 ' + res.data.default_provider + '...';
                                setTimeout(function(){ testBtn.click(); }, 500);
                            }
                        }
                        // 8 秒后清空提示 (给自动测试留时间)
                        setTimeout(function(){ status.textContent = ''; }, 8000);
                    } else {
                        status.textContent = '❌ ' + (res.data && res.data.message ? res.data.message : '保存失败');
                        status.style.color = '#800';
                    }
                })
                .catch(function(e){
                    saveBtn.disabled = false;
                    saveBtn.value = '💾 保存默认 AI 服务 + 多 Key 轮询';
                    status.textContent = '❌ 网络错误: ' + e.message;
                    status.style.color = '#800';
                });
        });

        // v27.8.11 (审计Phase1): 测试连接按钮
        document.querySelectorAll('.linked3-test-provider').forEach(function(btn){
            btn.addEventListener('click', function(){
                var provider = this.getAttribute('data-provider');
                var statusEl = document.querySelector('.linked3-test-status[data-provider="' + provider + '"]');
                var keyEl = document.getElementById('key_' + provider);
                var modelEl = document.getElementById('model_' + provider);

                // 读取表单中的 key (未保存的), 如果为空则用已保存的 (后端处理)
                var apiKey = keyEl ? keyEl.value.trim().split('\n')[0].trim() : '';
                var model = modelEl ? modelEl.value : '';

                btn.disabled = true;
                btn.textContent = '测试中...';
                statusEl.textContent = '⏳ 正在连接 ' + provider + '...';
                statusEl.style.color = '#666';

                var fd = new FormData();
                fd.append('action', 'linked3_test_provider');
                fd.append('nonce', window.linked3Nonce);
                fd.append('provider', provider);
                if (apiKey) fd.append('api_key', apiKey);
                if (model) fd.append('model', model);

                fetch(window.linked3AjaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(res){
                        btn.disabled = false;
                        btn.textContent = '🔌 测试连接';
                        if (res.success) {
                            statusEl.textContent = res.data.message;
                            statusEl.style.color = '#080';
                        } else {
                            statusEl.textContent = (res.data && res.data.message) ? res.data.message : '❌ 连接失败';
                            statusEl.style.color = '#800';
                        }
                        // 8秒后清空状态
                        setTimeout(function(){ statusEl.textContent = ''; }, 8000);
                    })
                    .catch(function(e){
                        btn.disabled = false;
                        btn.textContent = '🔌 测试连接';
                        statusEl.textContent = '❌ 网络错误: ' + e.message;
                        statusEl.style.color = '#800';
                    });
            });
        });
    })();

    (function(){
        var nonce = window.linked3Nonce;
        var ajaxUrl = window.linked3AjaxUrl;

        // 添加自定义 API
        document.getElementById('linked3-add-custom-api').addEventListener('click', function(){
            var id = 'custom_' + Date.now();
            var html = '<div class="custom-api-row" data-id="' + id + '" style="background:#fff;border:1px solid #ddd;padding:12px;margin:8px 0;border-radius:4px;">' +
                '<table class="form-table" style="margin:0;">' +
                '<tr><th style="width:120px;">名称</th><td><input type="text" class="custom-api-name regular-text" value="" placeholder="自定义 API 名称" /></td><td style="width:80px;"><button type="button" class="button button-link-delete custom-api-delete">删除</button></td></tr>' +
                '<tr><th>API 地址</th><td colspan="2"><input type="text" class="custom-api-url large-text" value="" placeholder="https://api.example.com/v1/chat/completions" /></td></tr>' +
                '<tr><th>模型</th><td colspan="2"><input type="text" class="custom-api-model regular-text" value="" placeholder="deepseek-r1" /></td></tr>' +
                '<tr><th>API Key</th><td colspan="2"><textarea class="custom-api-key" rows="2" cols="60" placeholder="多个 Key 用换行分隔"></textarea></td></tr>' +
                '</table></div>';
            document.getElementById('linked3-custom-apis').insertAdjacentHTML('beforeend', html);
        });

        // 删除自定义 API (事件委托)
        document.getElementById('linked3-custom-apis').addEventListener('click', function(e){
            if (e.target.classList.contains('custom-api-delete')) {
                if (confirm('确认删除此自定义 API?')) {
                    e.target.closest('.custom-api-row').remove();
                }
            }
        });

        // 保存自定义 API
        document.getElementById('linked3-save-custom-apis').addEventListener('click', function(){
            var apis = {};
            document.querySelectorAll('.custom-api-row').forEach(function(row){
                var id = row.dataset.id;
                apis[id] = {
                    name: row.querySelector('.custom-api-name').value,
                    url: row.querySelector('.custom-api-url').value,
                    model: row.querySelector('.custom-api-model').value,
                    key: row.querySelector('.custom-api-key').value
                };
            });
            var fd = new FormData();
            fd.append('action', 'linked3_save_custom_apis');
            fd.append('nonce', nonce);
            fd.append('apis', JSON.stringify(apis));
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){
                    var s = document.getElementById('linked3-custom-save-status');
                    s.textContent = res.success ? '已保存' : '保存失败';
                    s.style.color = res.success ? '#080' : '#800';
                    setTimeout(function(){ s.textContent = ''; }, 2000);
                });
        });

        // 同步模型
        document.querySelectorAll('.linked3-sync-models').forEach(function(btn){
            btn.addEventListener('click', function(){
                var provider = btn.dataset.provider;
                var status = document.querySelector('.linked3-sync-status[data-provider="' + provider + '"]');
                status.textContent = '同步中...';
                status.style.color = '#666';
                var fd = new FormData();
                fd.append('action', 'linked3_sync_models');
                fd.append('nonce', nonce);
                fd.append('provider', provider);
                // v27.8.11 (审计Phase1): 从表单读取 key (未保存也能同步)
                var keyEl = document.getElementById('key_' + provider);
                if (keyEl && keyEl.value.trim()) {
                    fd.append('api_key', keyEl.value.trim().split('\n')[0].trim());
                }
                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(res){
                        if (res.success) {
                            var models = res.data.models;
                            var select = document.getElementById('model_' + provider);
                            var current = select.value;
                            select.innerHTML = '';
                            models.forEach(function(m){
                                var opt = document.createElement('option');
                                opt.value = m; opt.textContent = m;
                                if (m === current) opt.selected = true;
                                select.appendChild(opt);
                            });
                            status.textContent = '已同步 ' + res.data.count + ' 个模型';
                            status.style.color = '#080';
                        } else {
                            status.textContent = res.data.message || '失败';
                            status.style.color = '#800';
                        }
                        setTimeout(function(){ status.textContent = ''; }, 3000);
                    });
            });
        });
    })();
    

    document.getElementById('linked3-save-suffix').addEventListener('click', function(){
        var btn = this;
        var s = document.getElementById('linked3-suffix-status');
        btn.disabled = true;
        s.textContent = '保存中...';
        s.style.color = '#666';
        var fd = new FormData();
        fd.append('action', 'linked3_save_ai_suffix');
        fd.append('nonce', window.linked3Nonce);
        fd.append('enabled', document.getElementById('ai_suffix_enabled').checked ? 1 : 0);
        fd.append('suffix', document.getElementById('ai_suffix_text').value);
        fetch(window.linked3AjaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                btn.disabled = false;
                s.textContent = res.success ? '已保存' : ('保存失败: ' + (res.data && res.data.message ? res.data.message : '未知'));
                s.style.color = res.success ? '#080' : '#800';
                setTimeout(function(){ s.textContent = ''; }, 3000);
            })
            .catch(function(e){
                btn.disabled = false;
                s.textContent = '网络错误: ' + e.message;
                s.style.color = '#800';
            });
    });
    

    document.getElementById('linked3-save-advanced').addEventListener('click', function(){
        var btn = this;
        var s = document.getElementById('linked3-advanced-status');
        btn.disabled = true;
        s.textContent = '保存中...';
        s.style.color = '#666';
        var fd = new FormData();
        fd.append('action', 'linked3_save_advanced');
        fd.append('nonce', window.linked3Nonce);
        fd.append('require_html', document.getElementById('adv_require_html').checked ? 1 : 0);
        fd.append('enable_ai_summary', document.getElementById('adv_enable_summary').checked ? 1 : 0);
        fd.append('require_tag', document.getElementById('adv_require_tag').checked ? 1 : 0);
        fd.append('time_window_enabled', document.getElementById('adv_time_window').checked ? 1 : 0);
        fd.append('time_window_start', document.getElementById('adv_time_start').value);
        fd.append('time_window_end', document.getElementById('adv_time_end').value);
        fetch(window.linked3AjaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                btn.disabled = false;
                s.textContent = res.success ? '已保存' : ('保存失败: ' + (res.data && res.data.message ? res.data.message : '未知'));
                s.style.color = res.success ? '#080' : '#800';
                setTimeout(function(){ s.textContent = ''; }, 3000);
            })
            .catch(function(e){
                btn.disabled = false;
                s.textContent = '网络错误: ' + e.message;
                s.style.color = '#800';
            });
    });
    

    document.getElementById('linked3-save-image-api').addEventListener('click', function(){
        var b=this,s=document.getElementById('linked3-image-api-status');b.disabled=true;s.textContent='保存中...';s.style.color='#666';
        var fd=new FormData();fd.append('action','linked3_save_image_api');fd.append('nonce',window.linked3Nonce);
        fd.append('provider',document.getElementById('img_provider').value);
        var cm=document.getElementById('img_model_custom').value.trim();fd.append('model',cm||document.getElementById('img_model').value);
        fd.append('api_base',document.getElementById('img_api_base').value);fd.append('api_key',document.getElementById('img_api_key').value);
        fd.append('width',document.getElementById('img_width').value);fd.append('height',document.getElementById('img_height').value);
        fetch(window.linked3AjaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){b.disabled=false;s.textContent=res.success?'✅ 已保存':('❌ '+(res.data&&res.data.message?res.data.message:'未知'));s.style.color=res.success?'#080':'#800';setTimeout(function(){s.textContent='';},3000);}).catch(function(e){b.disabled=false;s.textContent='❌ '+e.message;s.style.color='#800';});
    });
    // v16.1.0: 图片供应商切换 — 展开压缩代码, 提升可读性; 重建模型列表时保留当前选择
    document.getElementById('img_provider').addEventListener('change', function() {
        var providers = window.linked3_settings_api && window.linked3_settings_api.img_providers || [];
        var sl = this.value;
        var providerCfg = providers[sl] || {};
        var models = providerCfg.models || [];
        var sel = document.getElementById('img_model');
        var prevModel = sel.value; // v16.1.0: 记住当前模型, 重建后尝试恢复
        sel.innerHTML = '';
        models.forEach(function(m) {
            var o = document.createElement('option');
            o.value = m;
            o.textContent = m;
            if (m === prevModel) o.selected = true; // v16.1.0: 恢复之前选择
            sel.appendChild(o);
        });
        if (providerCfg.default_base) {
            document.getElementById('img_api_base').placeholder = providerCfg.default_base;
        }
    });
    
})();
