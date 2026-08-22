<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSSL — Español (es) strings. Semantic, owner-prefixed keys (tigerssl.*). Response messages translate
 * automatically; form placeholders use $this->_t() (Tiger_Form). Views use plain strings (no _t helper).
 */
return [
    // form placeholders / labels
    'tigerssl.form.domain.placeholder' => 'shop.example.com',
    'tigerssl.form.sans.placeholder'   => 'www.example.com, blog.example.com',

    // certificate service messages
    'tigerssl.cert.issued'         => 'Certificado emitido.',
    'tigerssl.cert.renewed'        => 'Certificado renovado.',
    'tigerssl.cert.removed'        => 'Certificado retirado de la gestión.',
    'tigerssl.cert.renew_due_done' => 'Ejecución de renovación completada.',

    // settings
    'tigerssl.settings.saved' => 'Configuración de SSL guardada.',

    // errors
    'tigerssl.error.host_managed'  => 'Este host gestiona el SSL por ti (p. ej. cPanel AutoSSL) — TigerSSL se retira.',
    'tigerssl.error.not_found'     => 'No se encontró ningún certificado gestionado para ese dominio.',
    'tigerssl.error.issue_failed'  => 'La emisión del certificado falló. Comprueba que el dominio apunte a este servidor y prueba primero con staging.',
    'tigerssl.error.renew_failed'  => 'La renovación del certificado falló. El certificado existente sigue funcionando hasta su vencimiento.',
];
