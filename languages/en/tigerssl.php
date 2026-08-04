<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSSL — English strings. Semantic, owner-prefixed keys (tigerssl.*). Response messages translate
 * automatically; form placeholders use $this->_t() (Tiger_Form). Views use plain strings (no _t helper).
 */
return [
    // form placeholders / labels
    'tigerssl.form.domain.placeholder' => 'shop.example.com',
    'tigerssl.form.sans.placeholder'   => 'www.example.com, blog.example.com',

    // certificate service messages
    'tigerssl.cert.issued'         => 'Certificate issued.',
    'tigerssl.cert.renewed'        => 'Certificate renewed.',
    'tigerssl.cert.removed'        => 'Certificate removed from management.',
    'tigerssl.cert.renew_due_done' => 'Renewal run complete.',

    // settings
    'tigerssl.settings.saved' => 'SSL settings saved.',

    // errors
    'tigerssl.error.host_managed'  => 'This host manages SSL for you (e.g. cPanel AutoSSL) — TigerSSL is standing down.',
    'tigerssl.error.not_found'     => 'No managed certificate found for that domain.',
    'tigerssl.error.issue_failed'  => 'Certificate issuance failed. Check the domain points at this server and try staging first.',
    'tigerssl.error.renew_failed'  => 'Certificate renewal failed. The existing certificate keeps serving until its expiry.',
];
