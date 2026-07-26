/**
 * linked3-seo-settings.js
 * Extracted from: admin/views/seo/settings.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-seo-settings.js
 * Localized via wp_localize_script('linked3-seo-settings', 'linked3_seo_settings', {...})
 *   Keys: nonce_enhance, ajax_url
 */

(function(){
    var nonce_enhance = window.linked3_seo_settings && window.linked3_seo_settings.nonce_enhance || '';
    var ajax_url = window.linked3_seo_settings && window.linked3_seo_settings.ajax_url || '';


    document.getElementById('linked3-save-seo-enhance').addEventListener('click', function(){
        var btn = this;
        var s = document.getElementById('linked3-seo-enhance-status');
        btn.disabled = true;
        s.textContent = '保存中...';
        s.style.color = '#666';
        var fd = new FormData();
        fd.append('action', 'linked3_save_seo_enhance');
        fd.append('nonce', linked3_seo_settings.nonce_enhance);
        fd.append('interlink_enabled', document.getElementById('se_interlink_enabled').checked ? 1 : 0);
        fd.append('interlink_strategy', document.getElementById('se_interlink_strategy').value);
        fd.append('interlink_max_per_post', document.getElementById('se_interlink_max').value);
        fd.append('schema_article', document.getElementById('se_schema_article').checked ? 1 : 0);
        fd.append('schema_faq', document.getElementById('se_schema_faq').checked ? 1 : 0);
        fd.append('schema_howto', document.getElementById('se_schema_howto').checked ? 1 : 0);
        fd.append('schema_product', document.getElementById('se_schema_product').checked ? 1 : 0);
        fd.append('external_link_nofollow', document.getElementById('se_ext_nofollow').checked ? 1 : 0);
        fd.append('external_link_target_blank', document.getElementById('se_ext_target').checked ? 1 : 0);
        fd.append('external_link_whitelist', document.getElementById('se_ext_whitelist').value);
        fetch(linked3_seo_settings.ajax_url, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                btn.disabled = false;
                s.textContent = res.success ? '✓ ' + (res.data.message || '已保存') : '✗ ' + (res.data.message || '保存失败');
                s.style.color = res.success ? '#080' : '#800';
                setTimeout(function(){ s.textContent = ''; }, 3000);
            })
            .catch(function(e){
                btn.disabled = false;
                s.textContent = '✗ 网络错误: ' + e.message;
                s.style.color = '#800';
            });
    });
    
})();
