/**
 * Linked3 Consent Compliance — Frontend JS
 * Extracted from: src/Classes/Addons/ConsentComplianceAddon.php
 * Hides consent banner if user has already consented.
 */
(function() {
    'use strict';
    if (localStorage.getItem('linked3_consent')) {
        var el = document.getElementById('linked3-consent');
        if (el) el.style.display = 'none';
    }
})();
