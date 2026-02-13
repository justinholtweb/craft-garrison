/**
 * Garrison CP JavaScript
 */
(function() {
    'use strict';

    // Confirm before running a scan
    var scanForms = document.querySelectorAll('form[action*="garrison/scanner/run"]');
    scanForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            // Add loading state to button
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.classList.add('loading');
                btn.disabled = true;
                btn.textContent = Craft.t('garrison', 'Scanning...');
            }
        });
    });
})();
