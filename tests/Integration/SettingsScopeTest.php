<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Integration;

use Tiger_Model_Config;
use TigerSSL\Tests\Support\IntegrationTestCase;

/**
 * TIGER-511 — settings split per key: an org's own choice (auto-renew) is written at that org with an
 * install-wide fallback; the server's settings (CA, key type, installer, the sudo reload command) are
 * install-wide and only the install org writes them. A tenant can never set the root-run reload command.
 */
final class SettingsScopeTest extends IntegrationTestCase
{
    private function install(): array
    {
        return [
            'ca_environment' => 'production', 'ca_contact_email' => 'ops@example.com', 'key_type' => 'ec',
            'renew_before_days' => '20', 'renew_auto' => '1', 'install_strategy' => 'sudo-hook',
            'install_path' => '/etc/tiger/ssl', 'reload_command' => 'systemctl reload httpd',
        ];
    }

    private function value(string $scope, string $scopeId, string $key): ?string
    {
        return (new Tiger_Model_Config())->get($scope, $scopeId, $key);
    }

    public function testATenantCannotWriteInstallSettings(): void
    {
        $this->login(self::ORG_A);
        $res = $this->call(\Tigerssl_Service_Settings::class, 'save', ['reload_command' => 'rm -rf /', 'renew_auto' => '0']);

        $this->assertSame('tigerssl.error.install_settings', $this->firstMessage($res));
        $this->assertNull($this->value('global', '', 'tiger.tigerssl.install.reload_command'));
        $this->assertNull($this->value('org', self::ORG_A, 'tiger.tigerssl.install.reload_command'));
        $this->assertNull($this->value('org', self::ORG_A, 'tiger.tigerssl.renew.auto'), 'a refused save writes nothing');
    }

    public function testATenantSavesItsOwnAutoRenewAtItsOrg(): void
    {
        $this->login(self::ORG_A);
        $res = $this->call(\Tigerssl_Service_Settings::class, 'save', ['renew_auto' => '0']);

        $this->assertSame(1, $res->result, $this->firstMessage($res));
        $this->assertSame('0', $this->value('org', self::ORG_A, 'tiger.tigerssl.renew.auto'));
        $this->assertNull($this->value('global', '', 'tiger.tigerssl.renew.auto'));
        $this->assertNull($this->value('org', self::ORG_B, 'tiger.tigerssl.renew.auto'));
    }

    public function testTheInstallOrgWritesServerSettingsGlobally(): void
    {
        $this->login(self::ORG_INSTALL);
        $res = $this->call(\Tigerssl_Service_Settings::class, 'save', $this->install());

        $this->assertSame(1, $res->result, $this->firstMessage($res));
        $this->assertSame('systemctl reload httpd', $this->value('global', '', 'tiger.tigerssl.install.reload_command'));
        $this->assertSame('production', $this->value('global', '', 'tiger.tigerssl.ca.environment'));
        $this->assertSame('1', $this->value('org', self::ORG_INSTALL, 'tiger.tigerssl.renew.auto'));
        $this->assertNull($this->value('global', '', 'tiger.tigerssl.renew.auto'), 'auto-renew stays an org choice');
    }

    public function testInstallSettingsAreReadInstallWideNeverFromAnOrgRow(): void
    {
        // An org row for an install key (however it got there) never reaches the service's reads.
        (new Tiger_Model_Config())->set('org', self::ORG_A, 'tiger.tigerssl.install.strategy', 'sudo-hook');
        (new Tiger_Model_Config())->set('global', '', 'tiger.tigerssl.install.strategy', 'write-only');
        $this->login(self::ORG_A);

        $status = (new \Tigerssl_Service_Certificate())->_statusForView();
        $this->assertSame('write-only', $status['strategy']);
    }
}
