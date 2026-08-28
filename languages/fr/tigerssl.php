<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSSL — Français (fr) strings. Semantic, owner-prefixed keys (tigerssl.*). Response messages translate
 * automatically; form placeholders use $this->_t() (Tiger_Form). Views use plain strings (no _t helper).
 */
return [
    // form placeholders / labels
    'tigerssl.form.domain.placeholder' => 'shop.example.com',
    'tigerssl.form.sans.placeholder'   => 'www.example.com, blog.example.com',

    // certificate service messages
    'tigerssl.cert.issued'         => 'Certificat émis.',
    'tigerssl.cert.renewed'        => 'Certificat renouvelé.',
    'tigerssl.cert.removed'        => 'Certificat retiré de la gestion.',
    'tigerssl.cert.renew_due_done' => 'Exécution du renouvellement terminée.',

    // settings
    'tigerssl.settings.saved' => 'Paramètres SSL enregistrés.',

    // errors
    'tigerssl.error.host_managed'  => 'Cet hôte gère le SSL pour vous (p. ex. cPanel AutoSSL) — TigerSSL se met en retrait.',
    'tigerssl.error.not_found'     => 'Aucun certificat géré trouvé pour ce domaine.',
    'tigerssl.error.issue_failed'  => 'L\'émission du certificat a échoué. Vérifiez que le domaine pointe vers ce serveur et testez d\'abord en staging.',
    'tigerssl.error.renew_failed'  => 'Le renouvellement du certificat a échoué. Le certificat existant reste actif jusqu\'à son expiration.',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'tigerssl.listing.description'           => 'HTTPS gratuit et automatique pour votre serveur Tiger — émission et renouvellement de certificats Let\'s Encrypt / ACME depuis l\'administration. PHP pur (sans certbot ni Python), HTTP-01 servi nativement, renouvellement automatique. Pour vos propres serveurs ; se retire lorsque l\'hébergeur gère le SSL (cPanel AutoSSL).',
];
