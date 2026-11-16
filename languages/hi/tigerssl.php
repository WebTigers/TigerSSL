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
    'tigerssl.error.no_org'           => 'आपका खाता किसी संगठन का सदस्य नहीं है, इसलिए प्रबंधित करने के लिए कोई प्रमाणपत्र नहीं है।',
    'tigerssl.error.name_not_owned'   => 'यह डोमेन आपके संगठन का नहीं है। पहले इसे अपनी साइटों में जोड़ें।',
    'tigerssl.error.name_taken'       => 'यह डोमेन पहले से किसी अन्य प्रबंधित प्रमाणपत्र पर है।',
    'tigerssl.error.san_invalid'      => 'प्रत्येक अतिरिक्त डोमेन एक मान्य होस्टनाम होना चाहिए।',
    'tigerssl.error.too_many_names'   => 'एक प्रमाणपत्र अधिकतम 100 डोमेन कवर कर सकता है।',
    'tigerssl.error.install_settings' => 'केवल साइट के स्वामी का संगठन ही सर्वर की SSL सेटिंग्स बदल सकता है।',

    // admin JS (delivered via the view's i18n carrier)
    'tigerssl.js.remove_title'   => 'प्रमाणपत्र हटाएँ',
    'tigerssl.js.remove_body'    => 'इस प्रमाणपत्र को प्रबंधन से हटाएँ? डिस्क पर फ़ाइलें बनी रहती हैं।',
    'tigerssl.js.remove_confirm' => 'हटाएँ',

    // The marketplace listing blurb. Pulled into the public directory by TigerVendors at the
    // pinned ref, so this file stays the one place this module's copy is translated.
    'tigerssl.listing.description'           => 'आपके Tiger सर्वर के लिए मुफ़्त, स्वचालित HTTPS — एडमिन से Let\'s Encrypt / ACME प्रमाणपत्र जारी करना और नवीनीकरण। शुद्ध PHP (कोई certbot/Python नहीं), HTTP-01 मूल रूप से सर्व किया जाता है, स्वतः नवीनीकरण। अपने सर्वरों के लिए; जहाँ होस्ट SSL प्रबंधित करता है वहाँ निष्क्रिय रहता है (cPanel AutoSSL)।',
];
