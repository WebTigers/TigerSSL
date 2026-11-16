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
    'tigerssl.error.no_org'           => 'Votre compte n\'appartient à aucune organisation et n\'a donc aucun certificat à gérer.',
    'tigerssl.error.name_not_owned'   => 'Ce domaine n\'appartient pas à votre organisation. Ajoutez-le d\'abord à vos sites.',
    'tigerssl.error.name_taken'       => 'Ce domaine figure déjà sur un autre certificat géré.',
    'tigerssl.error.san_invalid'      => 'Chaque domaine supplémentaire doit être un nom d\'hôte valide.',
    'tigerssl.error.too_many_names'   => 'Un certificat peut couvrir au maximum 100 domaines.',
    'tigerssl.error.install_settings' => 'Seule l\'organisation propriétaire du site peut modifier les paramètres SSL du serveur.',

    // admin JS (delivered via the view's i18n carrier)
    'tigerssl.js.remove_title'   => 'Retirer le certificat',
    'tigerssl.js.remove_body'    => 'Retirer ce certificat de la gestion ? Les fichiers sur le disque sont conservés.',
    'tigerssl.js.remove_confirm' => 'Retirer',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'tigerssl.listing.description'           => 'HTTPS gratuit et automatique pour votre serveur Tiger — émission et renouvellement de certificats Let\'s Encrypt / ACME depuis l\'administration. PHP pur (sans certbot ni Python), HTTP-01 servi nativement, renouvellement automatique. Pour vos propres serveurs ; se retire lorsque l\'hébergeur gère le SSL (cPanel AutoSSL).',
];
