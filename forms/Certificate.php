<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Form_Certificate — act on one managed certificate (renew / remove).
 *
 * Carries the certificate id (and, for the CLI's `renew <domain>`, an optional primary domain) plus the
 * CSRF token the base adds. Shares the Domain form's CSRF salt: the certificates screen renders ONE token
 * (from the add-domain form) and posts it back for issue, renew and remove alike.
 */
class Tigerssl_Form_Certificate extends Tiger_Form
{
    protected function elements(): array
    {
        return [
            ['hidden', 'certificate_id', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '/^[0-9a-fA-F-]{36}$/']]],
            ]],
            ['hidden', 'domain', [
                'required'   => false,
                'filters'    => ['StringTrim', 'StringToLower'],
                'validators' => [['Hostname', false, [Zend_Validate_Hostname::ALLOW_DNS]]],
            ]],
        ];
    }

    /** One CSRF slot for the whole certificates screen (see the class docblock). */
    protected function csrfSalt(): string
    {
        return 'Tigerssl_Form_Domain';
    }
}
