<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Form_Settings — the TigerSSL settings form.
 *
 * CA environment (staging|production), contact email, key type, renewal window + toggle, and the
 * installer strategy (+ path + reload command for the sudo-hook). Extends Tiger_Form.
 */
class Tigerssl_Form_Settings extends Tiger_Form
{
    protected function elements(): array
    {
        return [
            ['select', 'ca_environment', [
                'required'      => true,
                'multiOptions'  => ['staging' => 'Let\'s Encrypt — Staging (test)', 'production' => 'Let\'s Encrypt — Production'],
                'attribs'       => ['class' => 'form-select'],
            ]],
            ['text', 'ca_contact_email', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['EmailAddress']],
                'attribs'    => ['class' => 'form-control', 'placeholder' => 'you@example.com'],
            ]],
            ['select', 'key_type', [
                'required'     => true,
                'multiOptions' => ['ec' => 'ECDSA (P-256) — recommended', 'rsa' => 'RSA 2048'],
                'attribs'      => ['class' => 'form-select'],
            ]],
            ['text', 'renew_before_days', [
                'required'   => true,
                'filters'    => ['StringTrim', 'Digits'],
                'validators' => [['Between', false, ['min' => 1, 'max' => 89]]],
                'attribs'    => ['class' => 'form-control'],
            ]],
            ['checkbox', 'renew_auto', [
                'required' => false,
                'attribs'  => ['class' => 'form-check-input'],
            ]],
            ['select', 'install_strategy', [
                'required'     => true,
                'multiOptions' => [
                    'write-only' => 'Write-only (I point the web server at the files)',
                    'sudo-hook'  => 'Sudo-hook (issue + reload — I own root)',
                    'stand-down' => 'Stand down (my host manages SSL)',
                ],
                'attribs'      => ['class' => 'form-select'],
            ]],
            ['text', 'install_path', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'attribs'    => ['class' => 'form-control', 'placeholder' => '/etc/tiger/ssl'],
            ]],
            ['text', 'reload_command', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'attribs'    => ['class' => 'form-control', 'placeholder' => 'systemctl reload httpd'],
            ]],
        ];
    }
}
