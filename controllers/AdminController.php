<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_AdminController — the admin surface (certificates + settings), in the PUMA admin shell.
 *
 * Thin by the ADMIN.md rule: it reads + renders; every mutation is an /api call
 * (Tigerssl_Service_Certificate / Tigerssl_Service_Settings). ACL-gated to admin+ (configs/acl.ini).
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

    /** Settings: CA environment, contact, key type, renewal window, installer strategy. */
    public function settingsAction()
    {
        $this->view->title = 'SSL Settings — Tiger Admin';
        $form = new Tigerssl_Form_Settings();
        $form->populate($this->_currentConfig());
        $this->view->form = $form;
    }

    /** Read the live tiger.tigerssl.* config, flattened to the settings form field names. */
    protected function _currentConfig(): array
    {
        $t = [];
        if (Zend_Registry::isRegistered('Zend_Config')) {
            $node = Zend_Registry::get('Zend_Config')->get('tiger')?->get('tigerssl');
            if ($node) { $t = $node->toArray(); }
        }
        return [
            'ca_environment'    => $t['ca']['environment']      ?? 'staging',
            'ca_contact_email'  => $t['ca']['contact_email']    ?? '',
            'key_type'          => $t['key']['type']            ?? 'ec',
            'renew_before_days' => $t['renew']['before_days']   ?? '30',
            'renew_auto'        => (int) ($t['renew']['auto']   ?? 1),
            'install_strategy'  => $t['install']['strategy']    ?? 'write-only',
            'install_path'      => $t['install']['path']        ?? '/etc/tiger/ssl',
            'reload_command'    => $t['install']['reload_command'] ?? '',
        ];
    }
}
