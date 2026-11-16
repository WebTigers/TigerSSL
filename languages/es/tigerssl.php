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
    'tigerssl.error.no_org'           => 'Tu cuenta no pertenece a ninguna organización, así que no tiene certificados que administrar.',
    'tigerssl.error.name_not_owned'   => 'Ese dominio no pertenece a tu organización. Agrégalo primero a tus sitios.',
    'tigerssl.error.name_taken'       => 'Ese dominio ya está en otro certificado administrado.',
    'tigerssl.error.san_invalid'      => 'Cada dominio adicional debe ser un nombre de host válido.',
    'tigerssl.error.too_many_names'   => 'Un certificado puede cubrir como máximo 100 dominios.',
    'tigerssl.error.install_settings' => 'Solo la organización propietaria del sitio puede cambiar la configuración SSL del servidor.',

    // admin JS (delivered via the view's i18n carrier)
    'tigerssl.js.remove_title'   => 'Quitar certificado',
    'tigerssl.js.remove_body'    => '¿Quitar este certificado de la administración? Los archivos en disco se conservan.',
    'tigerssl.js.remove_confirm' => 'Quitar',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'tigerssl.listing.description'           => 'HTTPS gratuito y automático para tu servidor Tiger: emisión y renovación de certificados Let\'s Encrypt / ACME desde el panel. PHP puro (sin certbot ni Python), HTTP-01 servido de forma nativa, renovación automática. Para servidores propios; se desactiva cuando el alojamiento gestiona el SSL (cPanel AutoSSL).',
];
