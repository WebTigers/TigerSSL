<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tigerssl_Service_Certificate;
use Zend_Config;
use Zend_Registry;

/**
 * Tigerssl_Service_Certificate's pure helpers — SAN parsing, filesystem-safe naming, the
 * tiger.tigerssl.* config walk, and the derived install-strategy / host-stands-down / status surface.
 * No DB, no network: these are driven on a constructor-less instance via reflection.
 */
final class CertificateHelpersTest extends TestCase
{
    private Tigerssl_Service_Certificate $svc;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        $this->ref = new ReflectionClass(Tigerssl_Service_Certificate::class);
        $this->svc = $this->ref->newInstanceWithoutConstructor();
        $this->clearConfig();
    }

    protected function tearDown(): void
    {
        $this->clearConfig();
    }

    private function clearConfig(): void
    {
        if (Zend_Registry::isRegistered('Zend_Config')) {
            Zend_Registry::getInstance()->offsetUnset('Zend_Config');
        }
    }

    /** Register a Zend_Config holding a tiger.tigerssl.* tree. */
    private function setTigersslConfig(array $tigerssl): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['tigerssl' => $tigerssl]]));
    }

    /** Invoke a protected/private method without setAccessible() (deprecated on 8.5). */
    private function call(string $method, array $args = [])
    {
        return $this->ref->getMethod($method)->invoke($this->svc, ...$args);
    }

    // ── _parseSans ────────────────────────────────────────────────────────────────────────────────

    public function testParseSansSplitsLowercasesTrimsAndDedupes(): void
    {
        $this->assertSame(
            ['a.example.com', 'b.example.com'],
            $this->call('_parseSans', ['A.example.com, B.example.com   a.example.com'])
        );
    }

    public function testParseSansSplitsOnCommasAndWhitespace(): void
    {
        $this->assertSame(
            ['one.test', 'two.test', 'three.test'],
            $this->call('_parseSans', ["one.test,two.test\n three.test"])
        );
    }

    public function testParseSansOfEmptyIsEmptyArray(): void
    {
        $this->assertSame([], $this->call('_parseSans', ['']));
        $this->assertSame([], $this->call('_parseSans', ['   ,  , ']));
    }

    // ── _safeName ─────────────────────────────────────────────────────────────────────────────────

    public static function safeNameProvider(): array
    {
        return [
            'plain domain kept'          => ['example.com', 'example.com'],
            'hyphen + dot kept'          => ['my-site.co.uk', 'my-site.co.uk'],
            'wildcard star neutralised'  => ['*.example.com', '_.example.com'],
            'slash neutralised'          => ['a/b.example.com', 'a_b.example.com'],
            'traversal neutralised'      => ['../../etc', '.._.._etc'],
            'spaces neutralised'         => ['a b.com', 'a_b.com'],
        ];
    }

    #[DataProvider('safeNameProvider')]
    public function testSafeNameNeutralisesPathSignificantCharacters(string $in, string $expected): void
    {
        $this->assertSame($expected, $this->call('_safeName', [$in]));
    }

    // ── _cfg (tiger.tigerssl.* walk + default) ─────────────────────────────────────────────────────

    public function testCfgReadsANestedValue(): void
    {
        $this->setTigersslConfig(['ca' => ['environment' => 'production']]);
        $this->assertSame('production', $this->call('_cfg', ['ca.environment', 'staging']));
    }

    public function testCfgFallsBackToTheDefaultForAMissingKey(): void
    {
        $this->setTigersslConfig(['ca' => ['environment' => 'production']]);
        $this->assertSame('30', $this->call('_cfg', ['renew.before_days', '30']));
    }

    public function testCfgReturnsDefaultWhenNoConfigRegistered(): void
    {
        // registry cleared in setUp
        $this->assertSame('write-only', $this->call('_cfg', ['install.strategy', 'write-only']));
    }

    // ── _installStrategy / _hostStandsDown / _statusForView ─────────────────────────────────────────

    public function testInstallStrategyDefaultsToWriteOnly(): void
    {
        $this->assertSame('write-only', $this->call('_installStrategy'));
    }

    public function testInstallStrategyReadsConfig(): void
    {
        $this->setTigersslConfig(['install' => ['strategy' => 'sudo-hook']]);
        $this->assertSame('sudo-hook', $this->call('_installStrategy'));
    }

    public function testHostStandsDownWhenStrategyIsStandDown(): void
    {
        $this->setTigersslConfig(['install' => ['strategy' => 'stand-down']]);
        $this->assertTrue($this->call('_hostStandsDown'));
    }

    public function testHostDoesNotStandDownByDefault(): void
    {
        $this->assertFalse($this->call('_hostStandsDown'), 'write-only + no probe → we manage SSL');
    }

    public function testHostStandsDownWhenAutosslProbePathExists(): void
    {
        $probe = tempnam(sys_get_temp_dir(), 'autossl-probe-');
        $this->setTigersslConfig(['host' => ['autossl_probe' => $probe]]);
        try {
            $this->assertTrue($this->call('_hostStandsDown'), 'an existing cPanel AutoSSL probe path → stand down');
        } finally {
            @unlink($probe);
        }
    }

    public function testHostDoesNotStandDownWhenProbePathIsAbsent(): void
    {
        $this->setTigersslConfig(['host' => ['autossl_probe' => '/no/such/autossl/marker/' . uniqid('', true)]]);
        $this->assertFalse($this->call('_hostStandsDown'), 'a configured-but-absent probe does not stand us down');
    }

    public function testStatusForViewReportsTheEnvironment(): void
    {
        $this->setTigersslConfig(['ca' => ['environment' => 'production'], 'install' => ['strategy' => 'write-only']]);
        $status = $this->svc->_statusForView();

        $this->assertSame('production', $status['ca']);
        $this->assertSame('write-only', $status['strategy']);
        $this->assertFalse($status['stands_down']);
        $this->assertArrayHasKey('acme_ready', $status);
        $this->assertArrayHasKey('openssl', $status);
    }

    public function testStatusForViewDefaultsToStagingWhenUnset(): void
    {
        $status = $this->svc->_statusForView();
        $this->assertSame('staging', $status['ca'], 'an unconfigured install defaults to the LE staging CA');
    }
}
