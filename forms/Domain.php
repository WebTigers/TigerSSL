<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Form_Domain — add a domain to manage (the issue form).
 *
 * `domain` is the primary CN; `sans` is an optional comma/space list of extra SAN domains. Extends
 * Tiger_Form (array-config elements, ViewHelper-only decorators, CSRF baked in).
 */
class Tigerssl_Form_Domain extends Tiger_Form
{
    protected function elements(): array
    {
        return [
            ['text', 'domain', [
                'required'   => true,
                'filters'    => ['StringTrim', 'StringToLower'],
                'validators' => [
                    ['Hostname', false, [Zend_Validate_Hostname::ALLOW_DNS]],
                ],
                'attribs'    => ['class' => 'form-control', 'placeholder' => $this->_t('tigerssl.form.domain.placeholder')],
            ]],
            ['textarea', 'sans', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => ['class' => 'form-control', 'rows' => 2, 'placeholder' => $this->_t('tigerssl.form.sans.placeholder')],
            ]],
        ];
    }
}
