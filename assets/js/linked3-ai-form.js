/**
 * Linked3 AI Form — Frontend JS
 * Extracted from: src/Classes/AIForms/AiFormManager.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-ai-form.js
 *
 * Auto-initializes all .linked3-ai-form elements on the page.
 * Data attributes: data-id, data-nonce, data-ajax
 * Localized via wp_localize_script('linked3-ai-form', 'linked3_form_i18n', {...})
 */
(function() {
    'use strict';

    function initForm(f) {
        if (!f || f.dataset.init) return;
        f.dataset.init = '1';

        f.addEventListener('submit', function(e) {
            e.preventDefault();
            var fd = new FormData(f);
            fd.append('action', 'linked3_form_submit');
            fd.append('form_id', f.dataset.id);
            fd.append('nonce', f.dataset.nonce);
            fetch(f.dataset.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    var el = f.querySelector('.linked3-ai-form-result');
                    el.style.display = 'block';
                    if (res.success) {
                        var fallback = (window.linked3_form_i18n && window.linked3_form_i18n.thank_you) || 'Thank you!';
                        el.innerHTML = '<div class="linked3-form-ok">' + (res.data.analysis || fallback) + '</div>';
                    } else {
                        el.innerHTML = '<div class="linked3-form-err">' + (res.data.message || 'Error') + '</div>';
                    }
                });
        });
    }

    function initAll() {
        document.querySelectorAll('.linked3-ai-form').forEach(initForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
