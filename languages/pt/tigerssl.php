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
    'tigerssl.error.no_org'           => 'Sua conta não pertence a nenhuma organização, portanto não tem certificados para gerenciar.',
    'tigerssl.error.name_not_owned'   => 'Esse domínio não pertence à sua organização. Adicione-o primeiro aos seus sites.',
    'tigerssl.error.name_taken'       => 'Esse domínio já está em outro certificado gerenciado.',
    'tigerssl.error.san_invalid'      => 'Cada domínio adicional deve ser um nome de host válido.',
    'tigerssl.error.too_many_names'   => 'Um certificado pode cobrir no máximo 100 domínios.',
    'tigerssl.error.install_settings' => 'Somente a organização proprietária do site pode alterar as configurações SSL do servidor.',

    // admin JS (delivered via the view's i18n carrier)
    'tigerssl.js.remove_title'   => 'Remover certificado',
    'tigerssl.js.remove_body'    => 'Remover este certificado do gerenciamento? Os arquivos em disco são mantidos.',
    'tigerssl.js.remove_confirm' => 'Remover',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'tigerssl.listing.description'           => 'HTTPS gratuito e automático para o seu servidor Tiger — emissão e renovação de certificados Let\'s Encrypt / ACME pelo admin. PHP puro (sem certbot/Python), HTTP-01 servido nativamente, renovação automática. Para servidores próprios; recua quando a hospedagem gerencia o SSL (cPanel AutoSSL).',
];
