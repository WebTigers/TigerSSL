<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Service_Settings — save TigerSSL settings over /api (validate → config tier).
 *
 * Two kinds of setting, decided per key (TIGER-511):
 *
 *   ORG-level — a choice each org makes about ITS certificates, written at the caller's org
 *     (Tiger_Model_Config::settingScope) and read per certificate with an install-wide fallback:
 *       renew_auto        → tiger.tigerssl.renew.auto  (the renewal job skips an org that turned it off)
 *
 *   INSTALL-level — properties of the server and its single ACME account, written GLOBAL, and only by a
 *     member of the install's own org (the org that owns the box; a tenant org never configures it):
 *       ca_environment    the CA (staging/production) — one ACME directory per install
 *       ca_contact_email  the ACME ACCOUNT contact — there is one account per install (account.key), and
 *                         ACME re-registers it with this address on every order, so a per-org value would
 *                         overwrite the shared account's contact with whichever org issued last
 *       key_type          the key algorithm for new certificates
 *       renew_before_days the renewal window the daily job scans with
 *       install_strategy / install_path / reload_command — how certificates reach the web server; the
 *                         reload command runs under sudo, so it is never tenant-writable
 *
 * The ACME account key is a SECRET and is NEVER written here — it lives on disk 0600 (FEATURES §10).
 */
class Tigerssl_Service_Settings extends Tiger_Service_Service
{
    /** Form field → config key, written at the caller's org. */
    const ORG_KEYS = [
        'renew_auto' => 'tiger.tigerssl.renew.auto',
    ];

    /** Form field → config key, written install-wide (global) by the install org only. */
    const INSTALL_KEYS = [
        'ca_environment'    => 'tiger.tigerssl.ca.environment',
        'ca_contact_email'  => 'tiger.tigerssl.ca.contact_email',
        'key_type'          => 'tiger.tigerssl.key.type',
        'renew_before_days' => 'tiger.tigerssl.renew.before_days',
        'install_strategy'  => 'tiger.tigerssl.install.strategy',
        'install_path'      => 'tiger.tigerssl.install.path',
        'reload_command'    => 'tiger.tigerssl.install.reload_command',
    ];

    /**
     * Persist the settings form: org-level keys at the caller's org; install-level keys globally, and
     * only when the caller belongs to the install org (a tenant sending one is refused).
     *
     * @param  array $params the /api message
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }
        $org = $this->_orgId();
        if ($org === '') { $this->_error('tigerssl.error.no_org'); return; }

        $install = ($org === Tigerssl_Model_HostOwner::installOrgId());
        $form    = new Tigerssl_Form_Settings();

        if ($install) {
            if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        } else {
            foreach (array_keys(self::INSTALL_KEYS) as $field) {
                if (array_key_exists($field, $params)) { $this->_error('tigerssl.error.install_settings'); return; }
            }
            // A tenant sends only its own keys — validate what it sent, and always the CSRF token.
            $params['_csrf'] = !empty($params['_csrf']) ? $params['_csrf'] : '';
            if (!$form->isValidPartial($params)) { $this->_formErrors($form); return; }
        }
        $values = $form->getValues();

        try {
            $config = new Tiger_Model_Config();
            [$scope, $scopeId] = Tiger_Model_Config::settingScope($org);
            $this->_transaction(function () use ($config, $values, $params, $install, $scope, $scopeId) {
                foreach (self::ORG_KEYS as $field => $key) {
                    if (array_key_exists($field, $params)) {
                        $config->set($scope, $scopeId, $key, (string) $values[$field]);
                    }
                }
                if ($install) {
                    foreach (self::INSTALL_KEYS as $field => $key) {
                        if (array_key_exists($field, $values)) {
                            $config->set(Tiger_Model_Config::SCOPE_GLOBAL, '', $key, (string) $values[$field]);
                        }
                    }
                }
            });
            $this->_success([], 'tigerssl.settings.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
