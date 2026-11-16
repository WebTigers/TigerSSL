<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Integration;

use TigerSSL\Tests\Support\FakeCertificateService;
use TigerSSL\Tests\Support\IntegrationTestCase;

/**
 * TIGER-511 — an org may only put names it OWNS on a certificate: its own site_domain hosts and their
 * www; an unmapped host belongs to the install org (how core routes it), so a tenant can't certify one
 * while a single-tenant install keeps issuing for its own host. Multi-name (SAN) certificates for an
 * org's own hosts go out as ONE order.
 */
final class IssueOwnershipTest extends IntegrationTestCase
{
    public function testIssuesOneMultiNameCertificateForTheOrgsOwnHosts(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('blog.a.example.com', self::ORG_A);

        $this->login(self::ORG_A);
        $res = $this->call(FakeCertificateService::class, 'issue', [
            'domain' => 'a.example.com', 'sans' => 'www.a.example.com, blog.a.example.com',
        ]);

        $this->assertSame(1, $res->result, $this->firstMessage($res));
        $id = $res->data['certificate_id'];
        $this->assertSame(self::ORG_A, $this->certRow($id)['org_id']);
        $this->assertSame('active', $this->certRow($id)['status']);
        $this->assertSame(['a.example.com', 'blog.a.example.com', 'www.a.example.com'], $this->names($id));
        $this->assertSame(
            [['org' => self::ORG_A, 'names' => ['a.example.com', 'www.a.example.com', 'blog.a.example.com']]],
            FakeCertificateService::$orders
        );
    }

    public function testRefusesAHostMappedToAnotherOrg(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('b.example.com', self::ORG_B);
        $this->login(self::ORG_A);

        // As the primary name …
        $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'b.example.com']);
        $this->assertSame('tigerssl.error.name_not_owned', $this->firstMessage($res));
        $this->assertSame(['name' => 'b.example.com'], $res->data);

        // … and smuggled in as a SAN beside an owned one (and as another org's www).
        foreach (['b.example.com', 'www.b.example.com'] as $san) {
            $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'a.example.com', 'sans' => $san]);
            $this->assertSame('tigerssl.error.name_not_owned', $this->firstMessage($res), $san);
        }

        $this->assertSame([], FakeCertificateService::$orders, 'no CA order for a refused name');
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tigerssl_certificate'), 'nothing written');
    }

    public function testASuspendedTenantsHostIsStillTheirs(): void
    {
        $this->mapHost('b.example.com', self::ORG_B, 'suspended');
        $this->login(self::ORG_INSTALL);

        $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'b.example.com']);
        $this->assertSame('tigerssl.error.name_not_owned', $this->firstMessage($res));

        // A tenant's www (no row of its own) is the tenant's too — not "unmapped, so the install org's".
        $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'www.b.example.com']);
        $this->assertSame('tigerssl.error.name_not_owned', $this->firstMessage($res));
        $this->assertSame([], FakeCertificateService::$orders);
    }

    public function testATenantCannotCertifyAnUnmappedHost(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->login(self::ORG_A);

        $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'unmapped.example.com']);
        $this->assertSame('tigerssl.error.name_not_owned', $this->firstMessage($res));
        $this->assertSame([], FakeCertificateService::$orders);
    }

    public function testTheInstallOrgCertifiesItsOwnUnmappedHost(): void
    {
        // A single-tenant install: no site_domain rows at all — the install org owns its own host.
        $this->login(self::ORG_INSTALL);

        $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'site.example.com', 'sans' => 'www.site.example.com']);
        $this->assertSame(1, $res->result, $this->firstMessage($res));
        $this->assertSame(
            [['org' => self::ORG_INSTALL, 'names' => ['site.example.com', 'www.site.example.com']]],
            FakeCertificateService::$orders
        );
    }

    public function testANameAlreadyOnAnotherCertificateIsRefused(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->mapHost('shop.a.example.com', self::ORG_A);
        $this->makeCert(self::ORG_A, ['a.example.com', 'www.a.example.com']);
        $this->login(self::ORG_A);

        $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'shop.a.example.com', 'sans' => 'www.a.example.com']);
        $this->assertSame('tigerssl.error.name_taken', $this->firstMessage($res));

        // Re-issuing the SAME certificate (same primary) with its own names is fine.
        $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'a.example.com', 'sans' => 'www.a.example.com']);
        $this->assertSame(1, $res->result, $this->firstMessage($res));
    }

    public function testAnInvalidSanIsAFieldError(): void
    {
        $this->mapHost('a.example.com', self::ORG_A);
        $this->login(self::ORG_A);

        $res = $this->call(FakeCertificateService::class, 'issue', ['domain' => 'a.example.com', 'sans' => 'not_a host!']);
        $this->assertSame(0, $res->result);
        $this->assertArrayHasKey('sans', (array) $res->form);
        $this->assertSame([], FakeCertificateService::$orders);
    }
}
