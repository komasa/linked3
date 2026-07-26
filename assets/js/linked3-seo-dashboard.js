/**
 * linked3-seo-dashboard.js
 * Extracted from: admin/views/seo/dashboard.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-seo-dashboard.js
 * Localized via wp_localize_script('linked3-seo-dashboard', 'linked3_seo_dashboard', {...})
 *   Keys: nonce, ajax_url, data
 */

(function(){
    var nonce = window.linked3_seo_dashboard && window.linked3_seo_dashboard.nonce || '';
    var ajax_url = window.linked3_seo_dashboard && window.linked3_seo_dashboard.ajax_url || '';
    var data = window.linked3_seo_dashboard && window.linked3_seo_dashboard.data || '';


    (function () {
        var nonce = linked3_seo_dashboard.nonce;
        var ajaxUrl = linked3_seo_dashboard.ajax_url;
        document.querySelectorAll('.linked3-push-now').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.disabled = true;
                var body = new FormData();
                body.append('action', 'linked3_push_now');
                body.append('nonce', nonce);
                body.append('engine', btn.dataset.engine);
                body.append('url', linked3_seo_dashboard.data);
                fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        btn.disabled = false;
                        if (res.success) {
                            var r = res.data.results[btn.dataset.engine] || { ok: false };
                            alert(r.message || (r.ok ? 'OK' : 'Failed'));
                        } else {
                            alert((res.data && res.data.message) || 'Error');
                        }
                    })
                    .catch(function (e) { btn.disabled = false; alert(String(e)); });
            });
        });
    })();
    
})();
