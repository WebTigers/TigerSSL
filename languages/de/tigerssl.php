<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSSL — Deutsch (de) strings. Semantic, owner-prefixed keys (tigerssl.*). Response messages translate
 * automatically; form placeholders use $this->_t() (Tiger_Form). Views use plain strings (no _t helper).
 */
return [
    // form placeholders / labels
    'tigerssl.form.domain.placeholder' => 'shop.example.com',
    'tigerssl.form.sans.placeholder'   => 'www.example.com, blog.example.com',

    // certificate service messages
    'tigerssl.cert.issued'         => 'Zertifikat ausgestellt.',
    'tigerssl.cert.renewed'        => 'Zertifikat erneuert.',
    'tigerssl.cert.removed'        => 'Zertifikat aus der Verwaltung entfernt.',
    'tigerssl.cert.renew_due_done' => 'Erneuerungslauf abgeschlossen.',

    // settings
    'tigerssl.settings.saved' => 'SSL-Einstellungen gespeichert.',

    // errors
    'tigerssl.error.host_managed'  => 'Dieser Host verwaltet SSL für Sie (z. B. cPanel AutoSSL) — TigerSSL tritt zurück.',
    'tigerssl.error.not_found'     => 'Für diese Domain wurde kein verwaltetes Zertifikat gefunden.',
    'tigerssl.error.issue_failed'  => 'Die Ausstellung des Zertifikats ist fehlgeschlagen. Prüfen Sie, ob die Domain auf diesen Server zeigt, und testen Sie zuerst mit Staging.',
    'tigerssl.error.renew_failed'  => 'Die Erneuerung des Zertifikats ist fehlgeschlagen. Das bestehende Zertifikat bleibt bis zu seinem Ablauf gültig.',
];
