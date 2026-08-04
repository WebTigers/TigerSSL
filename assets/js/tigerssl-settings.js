/* SPDX-License-Identifier: BSD-3-Clause
 * TigerSSL settings save — over /api, via the house primitives (TigerButton, TigerDOM). No inline script.
 */
(function () {
    'use strict';
    var btn = document.getElementById('tigerssl-settings-save');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        var form = document.getElementById('tigerssl-settings-form');
        var fb   = document.getElementById('tigerssl-settings-feedback');
        var fd   = new URLSearchParams(new FormData(form));
        fd.set('module', 'tigerssl'); fd.set('service', 'settings'); fd.set('method', 'save');
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) { TigerDOM.notify(fb, 'Settings saved.', { type: 'success' }); return; }
            if (res && res.form) {
                Object.keys(res.form).forEach(function (f) {
                    var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); }
                });
            }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () { TigerDOM.notify(fb, 'Network error — please try again.', { type: 'error' }); });
    });
})();
