<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_AdminController — the admin surface (certificates + settings), in the PUMA admin shell.
 *
 * Thin by the ADMIN.md rule: it reads + renders; every mutation is an /api call
 * (Tigerssl_Service_Certificate / Tigerssl_Service_Settings). ACL-gated to admin+ (configs/acl.ini).
 * The certificates grid is fetched over /api, scoped to the caller's org (TIGER-511).
 */
class Tigerssl_AdminController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();
    }

    /** Certificates: a DataTables grid + the add-domain form + the host-status banner. */
    public function indexAction()
    {
        $this->view->title         = 'SSL — Tiger Admin';
        $this->view->useDataTables = true;
        $this->view->form          = new Tigerssl_Form_Domain();
        $this->view->status        = (new Tigerssl_Service_Certificate())->_statusForView();
    }

    /**
     * Settings: the org's own choices (auto-renew) for everyone; the install-level settings (CA, key type,
     * renewal window, installer) only for a member of the install's own org — the service enforces the
     * same split.
     */
    public function settingsAction()
    {
        $this->view->title = 'SSL Settings — Tiger Admin';
        $install = Tiger_Model_Config::currentOrgId() === Tigerssl_Model_HostOwner::installOrgId();
        $form = new Tigerssl_Form_Settings();
        $form->populate($this->_currentConfig());
        $this->view->form         = $form;
        $this->view->installScope = $install;
    }

    /**
     * The settings as the screen shows them: org keys at the caller's org (the tier the save writes),
     * install keys at the install-wide tier (never an org row).
     */
    protected function _currentConfig(): array
    {
        $g = fn(string $k, string $d) => (string) Tiger_Model_Config::orgValue('', 'tiger.tigerssl.' . $k, $d);
        return [
            'ca_environment'    => $g('ca.environment', 'staging'),
            'ca_contact_email'  => $g('ca.contact_email', ''),
            'key_type'          => $g('key.type', 'ec'),
            'renew_before_days' => $g('renew.before_days', '30'),
            'renew_auto'        => (int) Tiger_Model_Config::currentValue('tiger.tigerssl.renew.auto', '1'),
            'install_strategy'  => $g('install.strategy', 'write-only'),
            'install_path'      => $g('install.path', '/etc/tiger/ssl'),
            'reload_command'    => $g('install.reload_command', ''),
        ];
    }
}
