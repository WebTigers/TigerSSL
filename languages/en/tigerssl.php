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
    'tigerssl.error.no_org'           => 'Your account is not a member of an organization, so it has no certificates to manage.',
    'tigerssl.error.name_not_owned'   => 'That domain does not belong to your organization. Add it to your sites first.',
    'tigerssl.error.name_taken'       => 'That domain is already on another managed certificate.',
    'tigerssl.error.san_invalid'      => 'Every additional domain must be a valid hostname.',
    'tigerssl.error.too_many_names'   => 'A certificate can cover at most 100 domains.',
    'tigerssl.error.install_settings' => 'Only the site owner\'s organization can change the server\'s SSL settings.',

    // admin JS (delivered via the view's i18n carrier)
    'tigerssl.js.remove_title'   => 'Remove certificate',
    'tigerssl.js.remove_body'    => 'Remove this certificate from management? The on-disk files are left in place.',
    'tigerssl.js.remove_confirm' => 'Remove',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'tigerssl.listing.description'           => 'Free, automatic HTTPS for your Tiger server — Let\'s Encrypt / ACME certificate issuance + renewal from the admin. Pure-PHP (no certbot/Python), HTTP-01 served natively, auto-renews. For servers you own; stands down where the host manages SSL (cPanel AutoSSL).',
];
