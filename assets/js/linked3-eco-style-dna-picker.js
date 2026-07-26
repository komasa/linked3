/**
 * linked3-eco-style-dna-picker.js
 * Extracted from: admin/views/dashboard/partials/eco-style-dna-picker.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-style-dna-picker.js
 */

(function(){

// v17.2: 全局取值函数 — 所有写作入口共享
window.lk3_get_style_config = function() {
    var cfg = { style_dna: '', tone: 'professional', humanize_modules: [] };
    var sd = document.getElementById('lk3-style-dna');
    if (sd) cfg.style_dna = sd.value;
    var tn = document.getElementById('lk3-tone');
    if (tn) cfg.tone = tn.value;
    document.querySelectorAll('.lk3-humanize-module:checked').forEach(function(cb) {
        cfg.humanize_modules.push(cb.value);
    });
    return cfg;
};

})();
