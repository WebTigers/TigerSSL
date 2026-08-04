<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Service_Settings — save TigerSSL settings over /api (validate → config tier).
 *
 * Writes tiger.tigerssl.* to the config table (live-override, per-org capable, no deploy), exactly like
 * every other Tiger settings screen. The ACME account key is a SECRET and is NEVER written here — it
 * lives on disk 0600 / the secrets tier (FEATURES §10).
 */
class Tigerssl_Service_Settings extends Tiger_Service_Service
{
    /** Persist the settings form. */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Tigerssl_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $values = $form->getValues();

        // Map form fields → config keys. Only known keys are written.
        $map = [
            'ca_environment'   => 'tiger.tigerssl.ca.environment',
            'ca_contact_email' => 'tiger.tigerssl.ca.contact_email',
            'key_type'         => 'tiger.tigerssl.key.type',
            'renew_before_days'=> 'tiger.tigerssl.renew.before_days',
            'renew_auto'       => 'tiger.tigerssl.renew.auto',
            'install_strategy' => 'tiger.tigerssl.install.strategy',
            'install_path'     => 'tiger.tigerssl.install.path',
            'reload_command'   => 'tiger.tigerssl.install.reload_command',
        ];

        try {
            $config = new Tiger_Model_Config();
            $this->_transaction(function () use ($config, $map, $values) {
                foreach ($map as $field => $key) {
                    if (array_key_exists($field, $values)) {
                        $config->set('global', '', $key, (string) $values[$field]);
                    }
                }
            });
            $this->_success([], 'tigerssl.settings.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
