/**
 * linked3-cw-editor.js
 * Extracted from: admin/views/content-writer/editor.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-cw-editor.js
 * Localized via wp_localize_script('linked3-cw-editor', 'linked3_cw_editor', {...})
 *   Keys: nonce, ajax_url, admin_url
 */

(function(){
    var nonce = window.linked3_cw_editor && window.linked3_cw_editor.nonce || '';
    var ajax_url = window.linked3_cw_editor && window.linked3_cw_editor.ajax_url || '';
    var admin_url = window.linked3_cw_editor && window.linked3_cw_editor.admin_url || '';


    (function () {
        var nonce = linked3_cw_editor.nonce;
        var ajaxUrl = linked3_cw_editor.ajax_url;
        function post(action, data, cb) {
            var body = new FormData();
            body.append('action', action);
            body.append('nonce', nonce);
            Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
            fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(cb)
                .catch(function (e) { console.error(e); });
        }
        document.getElementById('linked3-cw-generate').addEventListener('click', function () {
            var data = {
                keyword: document.getElementById('linked3-cw-keyword').value,
                title: document.getElementById('linked3-cw-title').value,
                template_id: document.getElementById('linked3-cw-template').value,
                inject_images: document.getElementById('linked3-cw-images').checked ? 1 : 0
            };
            document.getElementById('linked3-cw-spinner').classList.add('is-active');
            post('linked3_generate_content', data, function (res) {
                document.getElementById('linked3-cw-spinner').classList.remove('is-active');
                if (res.success) {
                    // v0.4.0: response is { articles: [...], count, total_tokens }.
                    // Render the first article; if multiple, show count.
                    var arts = res.data.articles || [];
                    var first = arts.length ? arts[0] : null;
                    var body = first ? (first.content || '') : '';
                    // Escape via textContent to neutralise any XSS payload the
                    // model might have emitted (defence-in-depth; <pre> already
                    // neutralises tags via .replace below, but textContent is
                    // the canonical WP-safe pattern).
                    var pre = document.createElement('pre');
                    pre.textContent = body;
                    var out = document.getElementById('linked3-cw-result');
                    out.innerHTML = '';
                    out.appendChild(pre);
                    if (arts.length > 1) {
                        var note = document.createElement('p');
                        note.className = 'linked3-cw-batch-note';
                        note.textContent = arts.length + ' articles generated (' + (res.data.total_tokens || 0) + ' tokens).';
                        out.appendChild(note);
                    }
                    window.__linked3_last_content = body;
                } else {
                    alert((res.data && res.data.message) || 'Error');
                }
            });
        });
        ['title','meta','tags','excerpt'].forEach(function (kind) {
            document.getElementById('linked3-cw-gen-' + kind).addEventListener('click', function () {
                post('linked3_generate_' + kind, {
                    keyword: document.getElementById('linked3-cw-keyword').value,
                    title: document.getElementById('linked3-cw-title').value
                }, function (res) {
                    if (res.success) {
                        var out = res.data[kind === 'meta' ? 'meta_description' : (kind + 's')];
                        if (Array.isArray(out)) out = out.join('\n');
                        alert(out || '(empty)');
                    } else {
                        alert((res.data && res.data.message) || 'Error');
                    }
                });
            });
        });
        document.getElementById('linked3-cw-copy').addEventListener('click', function () {
            if (window.__linked3_last_content) {
                navigator.clipboard.writeText(window.__linked3_last_content);
            }
        });
        document.getElementById('linked3-cw-new-post').addEventListener('click', function () {
            if (!window.__linked3_last_content) return;
            // v0.4.0: removed the bogus `keyword: '__save_post__'` AJAX call
            // that wasted tokens by triggering a real generation just to
            // "save" the post. Instead, open post-new.php with a fragment
            // the editor can pick up; a future REST endpoint will write the
            // draft properly (v0.5.x).
            window.open('linked3_cw_editor.admin_url#linked3-prefill=' + encodeURIComponent(window.__linked3_last_content).slice(0, 2000));
        });
    })();
    
})();
