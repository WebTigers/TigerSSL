<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Integration;

use Tiger_Model_Config;
use TigerSSL\Tests\Support\FakeCertificateService;
use TigerSSL\Tests\Support\IntegrationTestCase;

/**
 * TIGER-511 — the daily renewal job renews every org's due certificates, each as its OWN org and for
 * exactly the names on it: one order per certificate, never two orgs' names in one order, never a name
 * its org no longer owns. Plus the legacy upgrade: a pre-1.1 row (org_id '', a `sans` JSON column) is
 * stamped to the install org, gets its names, and keeps renewing.
 */
final class RenewalJobTest extends IntegrationTestCase
{
    public function testTheJobRenewsEachCertificateAsItsOwnOrgAndNeverMixesOrgs(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('b.example.com', self::ORG_B);
        $this->mapHost('b2.example.com', self::ORG_B);
        $this->makeCert(self::ORG_A, ['a.example.com', 'www.a.example.com']);
        $this->makeCert(self::ORG_B, ['b.example.com', 'b2.example.com']);
        $this->makeCert(self::ORG_INSTALL, ['site.example.com']);
        $this->makeCert(self::ORG_A, ['later.example.com'], date('Y-m-d H:i:s', time() + 80 * 86400));   // not due

        $out = (new FakeCertificateService())->_renewAllDue();

        $this->assertSame(['renewed' => 3, 'failed' => 0, 'skipped' => 0, 'due' => 3], $out);
        $this->assertCount(3, FakeCertificateService::$orders, 'one order per certificate');
        $byOrg = [];
        foreach (FakeCertificateService::$orders as $order) {
            foreach ($order['names'] as $name) {
                $this->assertSame($order['org'], \Tigerssl_Model_HostOwner::ownerOf($name),
                    "$name went to the CA under an org that does not own it");
            }
            $byOrg[$order['org']] = $order['names'];
        }
        $this->assertSame(['a.example.com', 'www.a.example.com'], $byOrg[self::ORG_A]);
        $this->assertSame(['b.example.com', 'b2.example.com'], $byOrg[self::ORG_B]);
        $this->assertSame(['site.example.com'], $byOrg[self::ORG_INSTALL]);
    }

    public function testANameThatMovedToAnotherOrgFailsTheRenewalInsteadOfBeingCertified(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $id = $this->makeCert(self::ORG_A, ['a.example.com', 'moved.example.com']);
        $this->mapHost('moved.example.com', self::ORG_B);   // the host now belongs to org B

        $out = (new FakeCertificateService())->_renewAllDue();

        $this->assertSame(1, $out['failed']);
        $this->assertSame([], FakeCertificateService::$orders, "org A's order must not carry org B's host");
        $this->assertSame('error', $this->certRow($id)['status']);
    }

    public function testOneOrgsFailureDoesNotStopAnothersRenewal(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('b.example.com', self::ORG_B);
        $a = $this->makeCert(self::ORG_A, ['a.example.com']);
        $b = $this->makeCert(self::ORG_B, ['b.example.com']);
        FakeCertificateService::$failFor = ['a.example.com'];

        $out = (new FakeCertificateService())->_renewAllDue();

        $this->assertSame(1, $out['renewed']);
        $this->assertSame(1, $out['failed']);
        $this->assertSame('error', $this->certRow($a)['status']);
        $this->assertStringContainsString('CA refused', (string) $this->certRow($a)['last_error']);
        $this->assertSame('active', $this->certRow($b)['status']);
    }

    public function testAnOrgThatTurnedAutoRenewOffIsSkipped(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('b.example.com', self::ORG_B);
        $this->makeCert(self::ORG_A, ['a.example.com']);
        $this->makeCert(self::ORG_B, ['b.example.com']);
        (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_ORG, self::ORG_B, 'tiger.tigerssl.renew.auto', '0');

        $out = (new FakeCertificateService())->_renewAllDue();

        $this->assertSame(['renewed' => 1, 'failed' => 0, 'skipped' => 1, 'due' => 2], $out);
        $this->assertSame(self::ORG_A, FakeCertificateService::$orders[0]['org']);
    }

    public function testALegacySingleDomainRowMigratesToTheInstallOrgAndStillRenews(): void
    {
        // The founding org answers when tiger.site.org_id is unset (the common single-site install).
        self::useConfig([], '');
        $id = '0190a000-0000-7000-8000-000000000001';
        $this->db->insert('tigerssl_certificate', [
            'certificate_id' => $id, 'org_id' => '', 'domain' => 'legacy.example.com',
            'sans' => json_encode(['www.legacy.example.com']), 'status' => 'active', 'auto_renew' => 1,
            'expires_at' => date('Y-m-d H:i:s', time() + 3 * 86400), 'created_at' => '2026-08-01 00:00:00',
        ]);

        $backfill = require dirname(__DIR__, 2) . '/migrations/20261116120200_tigerssl_certificate_org_backfill.php';
        foreach ($backfill['up'] as $step) { $step($this->db); }
        foreach ($backfill['up'] as $step) { $step($this->db); }   // idempotent

        $this->assertSame(self::ORG_INSTALL, $this->certRow($id)['org_id']);
        $this->assertSame(['legacy.example.com', 'www.legacy.example.com'], $this->names($id));

        $out = (new FakeCertificateService())->_renewAllDue();
        $this->assertSame(1, $out['renewed']);
        $this->assertSame(
            [['org' => self::ORG_INSTALL, 'names' => ['legacy.example.com', 'www.legacy.example.com']]],
            FakeCertificateService::$orders
        );

        // …and the install org's admin now sees it on their grid.
        $this->login(self::ORG_INSTALL);
        $res = $this->call(FakeCertificateService::class, 'datatable', ['draw' => 1]);
        $this->assertSame($id, $res->data['data'][0]['certificate_id']);
    }
}
