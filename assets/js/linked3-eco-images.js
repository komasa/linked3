/**
 * linked3-eco-images.js
 * Extracted from: admin/views/dashboard/partials/eco-images.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-images.js
 * Localized via wp_localize_script('linked3-eco-images', 'linked3_eco_images', {...})
 *   Keys: ajax_url, nonce_img
 */

(function(){
    var ajax_url = window.linked3_eco_images && window.linked3_eco_images.ajax_url || '';
    var nonce_img = window.linked3_eco_images && window.linked3_eco_images.nonce_img || '';


(function(){
    var ajaxUrl = 'linked3_eco_images.ajax_url';
    var nonce = 'linked3_eco_images.nonce_img';

    // v16.1.0: escHtml 优先复用 Linked3EcoShared.escapeHtml (消除三处重复定义)
    var escHtml = (window.Linked3EcoShared && window.Linked3EcoShared.escapeHtml) ? window.Linked3EcoShared.escapeHtml : function(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    };

    document.addEventListener('DOMContentLoaded', function(){
        var saveBtn = document.getElementById('img-save');
        if (!saveBtn) return;

        saveBtn.addEventListener('click', function(){
            var settings = {};

            // AI模式字段
            var provider = document.getElementById('img-provider');
            if (provider) settings.provider = provider.value;
            var resolution = document.getElementById('img-resolution');
            if (resolution) settings.resolution = resolution.value;

            // 图库模式字段
            var stockProvider = document.getElementById('img-stock-provider');
            if (stockProvider) settings.stock_provider = stockProvider.value;
            var stockKey = document.getElementById('img-stock-key');
            if (stockKey) settings.stock_api_key = stockKey.value;

            // 图片站模式字段
            var siteUrl = document.getElementById('img-site-url');
            if (siteUrl) settings.image_site_url = siteUrl.value;
            var siteCount = document.getElementById('img-site-count');
            if (siteCount) settings.image_site_count = parseInt(siteCount.value) || 3;

            // 插入位置字段
            var insertPos = document.getElementById('img-insert-position');
            if (insertPos) settings.insert_position = insertPos.value;

            // 布局checkbox
            var layouts = [];
            document.querySelectorAll('input[name="img-layouts[]"]:checked').forEach(function(cb){
                layouts.push(cb.value);
            });
            settings.layouts = layouts;

            saveBtn.disabled = true;
            saveBtn.textContent = '保存中...';

            var fd = new FormData();
            fd.append('action', 'linked3_eco_image_save');
            fd.append('nonce', nonce);
            fd.append('settings', JSON.stringify(settings));

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(data){
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 保存设置';
                    var status = document.getElementById('img-status');
                    if (data.success) {
                        status.innerHTML = '<span style="color:#10B981;font-size:12px;">✅ 已保存</span>';
                    } else {
                        status.innerHTML = '<span style="color:#EF4444;font-size:12px;">❌ ' + escHtml(data.data && data.data.message ? data.data.message : '保存失败') + '</span>';
                    }
                    setTimeout(function(){ status.innerHTML = ''; }, 4000);
                })
                .catch(function(e){
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 保存设置';
                    document.getElementById('img-status').innerHTML =
                        '<span style="color:#EF4444;font-size:12px;">❌ 错误: ' + escHtml(e.message) + '</span>';
                });
        });
    });
})();

})();
