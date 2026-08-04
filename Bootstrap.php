<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSSL module bootstrap.
 *
 * Contributes the SSL screen to the admin Settings tree and, when the TigerSchedule capability is
 * present, registers a daily renewal job. It registers NO front-controller plugin and touches NO
 * web-server config — the ACME HTTP-01 challenge is a normal route (configs/routes.ini) to a normal
 * controller, so activation is purely additive (ARCHITECTURE §6).
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Tigerssl_Service_* (services/), Tigerssl_Model_* (models/), and Tigerssl_Form_* (forms/) load by
 * convention; configs/{acl,routes,module}.ini + languages/ are picked up by the core globs.
 */
class Tigerssl_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Contribute the SSL page to the admin Settings tree (ACL-gated in the menu). */
    protected function _initAdminSettings()
    {
        if (!class_exists('Tiger_Admin_Settings')) { return; }
        Tiger_Admin_Settings::register([
            'key'      => 'tigerssl',
            'label'    => 'SSL',
            'icon'     => 'fa-lock',
            'href'     => '/tigerssl/admin/index',
            'resource' => 'Tigerssl_AdminController',
            'order'    => 82,
        ]);
    }

    /**
     * Register the daily auto-renewal job with TigerSchedule when that capability exists (WP-pseudo-cron
     * style — no system crontab needed). A no-op when TigerSchedule isn't installed; the operator can
     * instead add `bin/tigerssl.php renew-due` to a real cron. Guarded so a missing capability, a
     * disabled module, or a boot-time hiccup can never break the app boot.
     */
    protected function _initRenewalSchedule()
    {
        if (!class_exists('Tiger_Schedule')) { return; }   // TigerSchedule capability absent → no-op
        try {
            $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
            $ssl = $cfg ? $cfg->get('tiger')?->get('tigerssl') : null;
            if ($ssl && (string) $ssl->get('enabled') === '1'
                && (string) $ssl->get('renew')?->get('schedule_enabled') === '1') {
                Tiger_Schedule::register([
                    'key'      => 'tigerssl.renew',
                    'label'    => 'TigerSSL — renew due certificates',
                    'interval' => 'daily',
                    'handler'  => ['Tigerssl_Service_Certificate', 'renewDue'],
                ]);
            }
        } catch (Throwable $e) {
            // Renewal registration is best-effort; never fatal the boot over it.
        }
    }
}
