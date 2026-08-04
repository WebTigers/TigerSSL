/* SPDX-License-Identifier: BSD-3-Clause
 * TigerSSL admin — certificates grid + issue/renew/remove, over /api, using the house primitives
 * (tigerDataTable, TigerButton, TigerDOM). No inline script in the view (Tiger house rule).
 */
(function () {
    'use strict';

    var fb = document.getElementById('tigerssl-feedback');

    function post(extra) {
        var body = new URLSearchParams(Object.assign({ module: 'tigerssl', service: 'certificate' }, extra));
        return fetch('/api', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    function notify(res, okMsg) {
        if (res && res.result === 1) { if (okMsg) { TigerDOM.notify(fb, okMsg, { type: 'success' }); } return true; }
        (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        if (!res || !res.messages) { TigerDOM.notify(fb, 'Something went wrong — please try again.', { type: 'error' }); }
        return false;
    }

    function statusPill(s) {
        var map = { active: 'success', expiring: 'warning', pending: 'secondary', error: 'danger', disabled: 'dark' };
        return '<span class="badge bg-' + (map[s] || 'secondary') + '">' + s + '</span>';
    }

    var table = window.tigerDataTable && tigerDataTable('#tigerssl-certs', {
        service: { module: 'tigerssl', service: 'certificate', method: 'datatable' },
        order: [[0, 'asc']],
        columns: [
            { data: 'domain' },
            { data: 'status', render: function (d) { return statusPill(d); } },
            { data: 'expires_at', render: function (d) { return d ? d : '<span class="text-body-secondary">—</span>'; } },
            { data: 'auto_renew', render: function (d) { return d ? '<i class="fa-solid fa-check text-success"></i>' : '<i class="fa-solid fa-minus text-body-secondary"></i>'; } },
            { data: 'installer' },
            {
                data: null, orderable: false, className: 'text-end', render: function (row) {
                    var id = row.certificate_id;
                    return '<button class="btn btn-sm btn-outline-secondary" data-renew="' + id + '" data-domain="' + row.domain + '"><i class="fa-solid fa-rotate"></i></button> ' +
                           '<button class="btn btn-sm btn-outline-danger" data-remove="' + id + '"><i class="fa-solid fa-trash"></i></button>';
                }
            }
        ]
    });

    // Issue a new certificate.
    var issueBtn = document.getElementById('tigerssl-issue-btn');
    if (issueBtn) {
        issueBtn.addEventListener('click', function () {
            var form = document.getElementById('tigerssl-add-form');
            var extra = Object.assign({ method: 'issue' }, Object.fromEntries(new FormData(form)));
            TigerButton.run(this, function () { return post(extra); })
                .then(function (res) {
                    if (res && res.form) {
                        Object.keys(res.form).forEach(function (f) {
                            var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); }
                        });
                    }
                    if (notify(res, 'Certificate issued.') && table) { table.ajax.reload(null, false); }
                })
                .catch(function () { TigerDOM.notify(fb, 'Network error — please try again.', { type: 'error' }); });
        });
    }

    // Renew / remove (event delegation on the table).
    document.addEventListener('click', function (e) {
        var renew = e.target.closest && e.target.closest('[data-renew]');
        var remove = e.target.closest && e.target.closest('[data-remove]');
        if (renew) {
            TigerButton.run(renew, function () { return post({ method: 'renew', domain: renew.getAttribute('data-domain') }); })
                .then(function (res) { if (notify(res, 'Renewal started.') && table) { table.ajax.reload(null, false); } });
        } else if (remove) {
            if (!window.confirm('Remove this certificate from management? (The on-disk files are left in place.)')) { return; }
            TigerButton.run(remove, function () { return post({ method: 'remove', certificate_id: remove.getAttribute('data-remove') }); })
                .then(function (res) { if (notify(res, 'Removed.') && table) { table.ajax.reload(null, false); } });
        }
    });
})();
