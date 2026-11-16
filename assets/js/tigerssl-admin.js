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

    // The screen renders ONE CSRF token (the add-domain form's); renew + remove post it back too — their
    // form shares the salt server-side.
    function csrf() {
        var el = document.querySelector('#tigerssl-add-form [name="_csrf"]');
        return el ? el.value : '';
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    var table = window.tigerDataTable && tigerDataTable('#tigerssl-certs', {
        service: { module: 'tigerssl', service: 'certificate', method: 'datatable' },
        order: [[0, 'asc']],
        columns: [
            {
                data: 'domain', render: function (d, t, row) {
                    var extra = (row.names || []).filter(function (n) { return n !== d; });
                    return esc(d) + (extra.length ? '<div class="small text-body-secondary">' + extra.map(esc).join(', ') + '</div>' : '');
                }
            },
            { data: 'status', render: function (d) { return statusPill(d); } },
            { data: 'expires_at', render: function (d) { return d ? d : '<span class="text-body-secondary">—</span>'; } },
            { data: 'auto_renew', render: function (d) { return d ? '<i class="fa-solid fa-check text-success"></i>' : '<i class="fa-solid fa-minus text-body-secondary"></i>'; } },
            { data: 'installer' },
            {
                data: null, orderable: false, className: 'text-end', render: function (row) {
                    var id = esc(row.certificate_id);
                    return (row.can_edit ? '<button class="btn btn-sm btn-outline-secondary" data-renew="' + id + '"><i class="fa-solid fa-rotate"></i></button> ' : '') +
                           (row.can_delete ? '<button class="btn btn-sm btn-outline-danger" data-remove="' + id + '"><i class="fa-solid fa-trash"></i></button>' : '');
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
            TigerButton.run(renew, function () { return post({ method: 'renew', certificate_id: renew.getAttribute('data-renew'), _csrf: csrf() }); })
                .then(function (res) { if (notify(res, 'Certificate renewed.') && table) { table.ajax.reload(null, false); } });
        } else if (remove) {
            TigerModal.confirm({
                title: Tiger.t('removeTitle'),
                body: Tiger.t('removeBody'),
                confirmLabel: Tiger.t('removeConfirm'),
                variant: 'danger'
            }).then(function (ok) {
                if (!ok) { return; }
                TigerButton.run(remove, function () { return post({ method: 'remove', certificate_id: remove.getAttribute('data-remove'), _csrf: csrf() }); })
                    .then(function (res) { if (notify(res, 'Removed.') && table) { table.ajax.reload(null, false); } });
            });
        }
    });
})();
