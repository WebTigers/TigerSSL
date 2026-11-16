<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Integration;

use TigerSSL\Tests\Support\FakeCertificateService;
use TigerSSL\Tests\Support\IntegrationTestCase;

/**
 * TIGER-511 — a certificate belongs to an org, and an org admin sees and manages only its own: the grid,
 * remove, renew and renew-due all stay in the caller's org lane. Each refusal has an own-org positive
 * control beside it, so a guard that refuses everything can't pass.
 */
final class OrgLaneTest extends IntegrationTestCase
{
    public function testDatatableListsOnlyTheCallersOrg(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('b.example.com', self::ORG_B);
        $a = $this->makeCert(self::ORG_A, ['a.example.com', 'www.a.example.com']);
        $this->makeCert(self::ORG_B, ['b.example.com']);

        $this->login(self::ORG_A);
        $res = $this->call(FakeCertificateService::class, 'datatable', ['draw' => 1]);

        $this->assertSame(1, $res->result);
        $rows = $res->data['data'];
        $this->assertCount(1, $rows);
        $this->assertSame($a, $rows[0]['certificate_id']);
        $this->assertSame(['a.example.com', 'www.a.example.com'], $rows[0]['names']);
    }

    public function testRemoveRefusesAnotherOrgsCertificateAndRemovesOwn(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('b.example.com', self::ORG_B);
        $a = $this->makeCert(self::ORG_A, ['a.example.com']);
        $b = $this->makeCert(self::ORG_B, ['b.example.com']);

        $this->login(self::ORG_A);
        $res = $this->call(FakeCertificateService::class, 'remove', ['certificate_id' => $b]);
        $this->assertSame(0, $res->result);
        $this->assertSame('tigerssl.error.not_found', $this->firstMessage($res));
        $this->assertSame(0, (int) $this->certRow($b)['deleted'], "org B's certificate must survive org A's remove");
        $this->assertSame(['b.example.com'], $this->names($b));

        $res = $this->call(FakeCertificateService::class, 'remove', ['certificate_id' => $a]);
        $this->assertSame(1, $res->result);
        $this->assertSame(1, (int) $this->certRow($a)['deleted']);
        $this->assertSame([], $this->names($a), 'a removed certificate frees its names');
    }

    public function testRenewRefusesAnotherOrgsCertificateAndRenewsOwn(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('b.example.com', self::ORG_B);
        $a = $this->makeCert(self::ORG_A, ['a.example.com', 'www.a.example.com']);
        $b = $this->makeCert(self::ORG_B, ['b.example.com']);

        $this->login(self::ORG_A);
        $res = $this->call(FakeCertificateService::class, 'renew', ['certificate_id' => $b]);
        $this->assertSame('tigerssl.error.not_found', $this->firstMessage($res));
        $this->assertSame([], FakeCertificateService::$orders, 'no order is placed for another org');

        $res = $this->call(FakeCertificateService::class, 'renew', ['certificate_id' => $a]);
        $this->assertSame(1, $res->result, $this->firstMessage($res));
        $this->assertSame([['org' => self::ORG_A, 'names' => ['a.example.com', 'www.a.example.com']]], FakeCertificateService::$orders);
        $this->assertSame('/fake/a.example.com/cert.pem', $this->certRow($a)['cert_path']);
    }

    public function testRenewDueOverApiStaysInTheCallersOrg(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('b.example.com', self::ORG_B);
        $this->makeCert(self::ORG_A, ['a.example.com']);
        $this->makeCert(self::ORG_B, ['b.example.com']);

        $this->login(self::ORG_A);
        $res = $this->call(FakeCertificateService::class, 'renewDue');

        $this->assertSame(1, $res->result);
        $this->assertSame(1, $res->data['due']);
        $this->assertSame([['org' => self::ORG_A, 'names' => ['a.example.com']]], FakeCertificateService::$orders);
    }
}
