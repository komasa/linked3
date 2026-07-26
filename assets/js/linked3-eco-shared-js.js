/**
 * linked3-eco-shared-js.js
 * Extracted from: admin/views/dashboard/partials/eco-shared-js.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-shared-js.js
 * Localized via wp_localize_script('linked3-eco-shared-js', 'linked3_eco_shared', {...})
 *   Keys: ajax_url, nonce_content_writer
 */

(function(){
    var ajax_url = window.linked3_eco_shared && window.linked3_eco_shared.ajax_url || '';
    var nonce_content_writer = window.linked3_eco_shared && window.linked3_eco_shared.nonce_content_writer || '';

if (!window.Linked3EcoShared) {
window.Linked3EcoShared = (function(){
    var ajaxUrl = 'linked3_eco_shared.ajax_url';
    var nonce = 'linked3_eco_shared.nonce_content_writer';

    // 统一HTML转义 (消除 eco-content/eco-synergy/eco-images 三处重复定义)
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    // 安全清空下拉并重建 (保留指定首选项, 修复 innerHTML='' 选项丢失)
    // 用法: rebuildSelect(sel, items, {preserveFirst:true, currentValue:sel.value})
    function rebuildSelect(selectEl, items, opts) {
        opts = opts || {};
        var prevVal = opts.currentValue || (selectEl ? selectEl.value : '');
        if (!selectEl) return;
        selectEl.innerHTML = '';
        // 保留首位选项 (如"自动适配")
        if (opts.preserveFirst && opts.firstOption) {
            var fo = document.createElement('option');
            fo.value = opts.firstOption.value || '';
            fo.textContent = opts.firstOption.text || '';
            selectEl.appendChild(fo);
        }
        items.forEach(function(item){
            var o = document.createElement('option');
            o.value = item.value;
            o.textContent = item.text;
            if (item.value === prevVal) o.selected = true;
            selectEl.appendChild(o);
        });
    }

    // 统一AJAX封装 (消除 fetch+credentials+json 解析的重复)
    function ajax(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        for (var k in data) {
            if (data.hasOwnProperty(k)) fd.append(k, data[k]);
        }
        return fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            });
    }

    // 统一生成图片 (eco-content 长文配图 + eco-synergy 配图共用)
    // 用法: Linked3EcoShared.generateImages(images, onProgress).then(...)
    function generateImages(images) {
        return ajax('linked3_eco_generate_images', {
            images: JSON.stringify(images)
        });
    }

    // 统一保存草稿 (eco-content + eco-synergy 组装共用)
    function saveDraft(title, content, images) {
        return ajax('linked3_eco_save_draft', {
            title: title,
            content: content,
            images: JSON.stringify(images || [])
        });
    }

    return {
        escapeHtml: escapeHtml,
        rebuildSelect: rebuildSelect,
        ajax: ajax,
        generateImages: generateImages,
        saveDraft: saveDraft,
        nonce: nonce,
        ajaxUrl: ajaxUrl
    };
})();
}

})();
