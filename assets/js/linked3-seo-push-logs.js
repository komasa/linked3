/**
 * linked3-seo-push-logs.js
 * Extracted from: admin/views/seo/push-logs.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-seo-push-logs.js
 * Localized via wp_localize_script('linked3-seo-push-logs', 'linked3_seo_push_logs', {...})
 *   Keys: nonce, ajax_url, data
 */

(function(){
    var nonce = window.linked3_seo_push_logs && window.linked3_seo_push_logs.nonce || '';
    var ajax_url = window.linked3_seo_push_logs && window.linked3_seo_push_logs.ajax_url || '';
    var data = window.linked3_seo_push_logs && window.linked3_seo_push_logs.data || '';


    (function () {
        var nonce = linked3_seo_push_logs.nonce;
        var ajaxUrl = linked3_seo_push_logs.ajax_url;
        document.getElementById('linked3-select-all').addEventListener('change', function (e) {
            document.querySelectorAll('.linked3-log-id').forEach(function (cb) { cb.checked = e.target.checked; });
        });
        document.getElementById('linked3-retry-selected').addEventListener('click', function () {
            var ids = [];
            document.querySelectorAll('.linked3-log-id:checked').forEach(function (cb) { ids.push(cb.value); });
            if (ids.length === 0) { alert(linked3_seo_push_logs.data); return; }
            var body = new FormData();
            body.append('action', 'linked3_push_retry');
            body.append('nonce', nonce);
            ids.forEach(function (id) { body.append('log_ids[]', id); });
            fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        alert((res.data.results ? Object.keys(res.data.results).length : 0) + ' engines re-pushed.');
                        window.location.reload();
                    } else {
                        alert((res.data && res.data.message) || 'Error');
                    }
                })
                .catch(function (e) { alert(String(e)); });
        });
    })();
    
})();
