/**
 * linked3-eco-config-bridge.js
 * Extracted from: admin/views/dashboard/partials/eco-config-bridge.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-config-bridge.js
 */


(function(){
    var srcSel = document.getElementById('bridge-tpl-source');
    var localWrap = document.getElementById('bridge-local-tpl-wrap');
    var cloudWrap = document.getElementById('bridge-cloud-tpl-wrap');
    if (!srcSel) return;
    srcSel.addEventListener('change', function(){
        localWrap.style.display = (this.value === 'local') ? '' : 'none';
        cloudWrap.style.display = (this.value === 'cloud') ? '' : 'none';
    });

    // 暴露桥接器取值函数, 供 eco-content.php 写作提交时读取
    window.linked3_bridge_get_config = function(){
        var cfg = { tpl_source: '', tpl_id: '', img_style: 'auto', output_format: 'markdown' };
        var s = document.getElementById('bridge-tpl-source');
        if (s) cfg.tpl_source = s.value;
        if (cfg.tpl_source === 'local') {
            var lt = document.getElementById('bridge-local-tpl');
            cfg.tpl_id = lt ? lt.value : '';
        } else if (cfg.tpl_source === 'cloud') {
            var ct = document.getElementById('bridge-cloud-tpl');
            cfg.tpl_id = ct ? ct.value : '';
        }
        var is = document.getElementById('bridge-img-style');
        if (is) cfg.img_style = is.value;
        var of = document.getElementById('bridge-output-format');
        if (of) cfg.output_format = of.value;
        return cfg;
    };
})();
