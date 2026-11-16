<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Backfill for per-org, multi-domain certificates (TIGER-511). DATA migration (a PHP callable — see
 * Tiger_Db_Migrator). One-way: `down` is a no-op.
 *
 *  1. Legacy rows were written with `org_id = ''` (the service never stamped one). They are assigned to
 *     the INSTALL org (Tiger_Model_Org::installOrgId — `tiger.site.org_id`, else the founding org), which
 *     is who issued them on a single-site install. A pre-install install with no org at all leaves them.
 *  2. Each live certificate gets its covered names written to tigerssl_certificate_name — the primary
 *     `domain` (is_primary=1) plus every name in the legacy `sans` JSON — so an existing single-domain
 *     certificate becomes a SAN list of one and keeps renewing. A certificate that already has names is
 *     skipped (idempotent). A name another certificate already holds stays with the first (the
 *     active-only unique would refuse the duplicate; it is skipped, not fatal).
 *
 * The `sans` column stays (no longer read from 1.1.0 on, still written alongside) so a rollback to 1.0.x
 * keeps working.
 */
return [
    'up' => [
        function ($db) {
            $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
            $site = $cfg && $cfg->get('tiger') && $cfg->get('tiger')->get('site') ? (string) $cfg->get('tiger')->get('site')->get('org_id') : '';
            $org  = class_exists('Tiger_Model_Org') ? (string) Tiger_Model_Org::installOrgId($site) : '';
            if ($org !== '') {
                $db->update('tigerssl_certificate', ['org_id' => $org], "org_id = ''");
            }

            $certs = $db->fetchAll(
                $db->select()->from('tigerssl_certificate', ['certificate_id', 'org_id', 'domain', 'sans'])->where('deleted = 0')
            );
            $now = gmdate('Y-m-d H:i:s');
            foreach ($certs as $c) {
                $has = (int) $db->fetchOne(
                    $db->select()->from('tigerssl_certificate_name', [new Zend_Db_Expr('COUNT(*)')])
                        ->where('certificate_id = ?', $c['certificate_id'])
                );
                if ($has > 0) { continue; }
                $sans  = $c['sans'] ? (json_decode((string) $c['sans'], true) ?: []) : [];
                $names = array_values(array_unique(array_filter(array_map(
                    fn($n) => strtolower(trim((string) $n)),
                    array_merge([(string) $c['domain']], is_array($sans) ? $sans : [])
                ))));
                foreach ($names as $i => $name) {
                    try {
                        $db->insert('tigerssl_certificate_name', [
                            'certificate_name_id' => Tiger_Uuid::v4(),
                            'certificate_id'      => $c['certificate_id'],
                            'org_id'              => (string) $c['org_id'],
                            'name'                => $name,
                            'is_primary'          => $i === 0 ? 1 : 0,
                            'created_at'          => $now,
                            'updated_at'          => $now,
                        ]);
                    } catch (Throwable $e) {
                        // already on another live certificate — the first holder keeps it
                    }
                }
            }
        },
    ],
    'down' => [],
];
