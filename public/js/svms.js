// SVMS: tiny, dependency-free helpers.
(function () {
    'use strict';

    // Any form with data-confirm="message" asks before submitting.
    document.addEventListener('submit', function (event) {
        var message = event.target && event.target.getAttribute && event.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Prevent double submits (double approvals, double uploads).
    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) { return; }
        var buttons = event.target.querySelectorAll('button[type="submit"]:not([data-keep-enabled])');
        buttons.forEach(function (button) {
            window.setTimeout(function () { button.disabled = true; }, 0);
        });
    });
})();
