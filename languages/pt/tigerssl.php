<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSSL — Português (pt) strings. Semantic, owner-prefixed keys (tigerssl.*). Response messages translate
 * automatically; form placeholders use $this->_t() (Tiger_Form). Views use plain strings (no _t helper).
 */
return [
    // form placeholders / labels
    'tigerssl.form.domain.placeholder' => 'shop.example.com',
    'tigerssl.form.sans.placeholder'   => 'www.example.com, blog.example.com',

    // certificate service messages
    'tigerssl.cert.issued'         => 'Certificado emitido.',
    'tigerssl.cert.renewed'        => 'Certificado renovado.',
    'tigerssl.cert.removed'        => 'Certificado removido do gerenciamento.',
    'tigerssl.cert.renew_due_done' => 'Execução de renovação concluída.',

    // settings
    'tigerssl.settings.saved' => 'Configurações de SSL salvas.',

    // errors
    'tigerssl.error.host_managed'  => 'Este host gerencia o SSL para você (por exemplo, cPanel AutoSSL) — o TigerSSL está se afastando.',
    'tigerssl.error.not_found'     => 'Nenhum certificado gerenciado encontrado para esse domínio.',
    'tigerssl.error.issue_failed'  => 'A emissão do certificado falhou. Verifique se o domínio aponta para este servidor e teste primeiro no staging.',
    'tigerssl.error.renew_failed'  => 'A renovação do certificado falhou. O certificado existente continua funcionando até expirar.',
];
