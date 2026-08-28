<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSSL — हिन्दी (hi) strings. Semantic, owner-prefixed keys (tigerssl.*). Response messages translate
 * automatically; form placeholders use $this->_t() (Tiger_Form). Views use plain strings (no _t helper).
 */
return [
    // form placeholders / labels
    'tigerssl.form.domain.placeholder' => 'shop.example.com',
    'tigerssl.form.sans.placeholder'   => 'www.example.com, blog.example.com',

    // certificate service messages
    'tigerssl.cert.issued'         => 'प्रमाणपत्र जारी किया गया।',
    'tigerssl.cert.renewed'        => 'प्रमाणपत्र नवीनीकृत किया गया।',
    'tigerssl.cert.removed'        => 'प्रमाणपत्र प्रबंधन से हटाया गया।',
    'tigerssl.cert.renew_due_done' => 'नवीनीकरण प्रक्रिया पूर्ण हुई।',

    // settings
    'tigerssl.settings.saved' => 'SSL सेटिंग्स सहेजी गईं।',

    // errors
    'tigerssl.error.host_managed'  => 'यह होस्ट आपके लिए SSL प्रबंधित करता है (जैसे cPanel AutoSSL) — TigerSSL पीछे हट रहा है।',
    'tigerssl.error.not_found'     => 'उस डोमेन के लिए कोई प्रबंधित प्रमाणपत्र नहीं मिला।',
    'tigerssl.error.issue_failed'  => 'प्रमाणपत्र जारी करना विफल रहा। जांचें कि डोमेन इस सर्वर की ओर इंगित करता है और पहले staging आज़माएँ।',
    'tigerssl.error.renew_failed'  => 'प्रमाणपत्र नवीनीकरण विफल रहा। मौजूदा प्रमाणपत्र अपनी समाप्ति तक कार्य करता रहेगा।',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'tigerssl.listing.description'           => 'आपके Tiger सर्वर के लिए मुफ़्त, स्वचालित HTTPS — एडमिन से Let\'s Encrypt / ACME प्रमाणपत्र जारी करना और नवीनीकरण। शुद्ध PHP (कोई certbot/Python नहीं), HTTP-01 मूल रूप से सर्व किया जाता है, स्वतः नवीनीकरण। अपने सर्वरों के लिए; जहाँ होस्ट SSL प्रबंधित करता है वहाँ निष्क्रिय रहता है (cPanel AutoSSL)।',
];
