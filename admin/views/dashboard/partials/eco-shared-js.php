<?php
/**
 * 生态共享JS工具库 v1.0 — 收敛 eco-content/eco-synergy/eco-images 的重复AJAX逻辑
 *
 * ============================================================================
 * v16.1.0 全插件举一反三审计修复:
 *
 * [冲突] 同源AJAX多入口 — linked3_eco_generate_images / linked3_eco_save_draft
 *   在 eco-content.php 和 eco-synergy.php 各自独立实现 fetch+渲染, 代码重复约120行
 *   v1.0: 抽取为 Linked3EcoShared 命名空间, 两处共用
 *
 * [冲突] innerHTML='' 清空模式分散
 *   v1.x: 9处文件各自 innerHTML='', 部分有选项丢失风险
 *   v1.0: 提供 safeClear() 统一封装, 重建下拉时保留指定首选项
 *
 * [冲突] HTML转义函数重复
 *   v1.x: escHtml/escapeHtml 在 eco-content/eco-synergy/eco-images 各定义一遍
 *   v1.0: 统一为 Linked3EcoShared.escapeHtml()
 *
 * 用法 (在需要JS的partial末尾引入一次):
 *   <?php include __DIR__ . '/eco-shared-js.php'; ?>
 * ============================================================================
 */
if (!defined('ABSPATH')) exit;
// 本文件只输出 <script>, 无 PHP 逻辑
?>
<script>
if (!window.Linked3EcoShared) {
window.Linked3EcoShared = (function(){
    var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
    var nonce = '<?php echo esc_js(wp_create_nonce("linked3_content_writer")); ?>';

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
</script>
