<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Support;

use PHPUnit\Framework\TestCase;
use Tiger_Model_Config;
use Tiger_Model_Org;
use Tiger_Model_Table;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Db;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * Base for TigerSSL integration tests (TIGER-511). The schema is the three core tables the module reads —
 * `org`, `config`, `site_domain` (from the sibling tiger-core's own migrations) — plus every TigerSSL
 * migration, applied once per process (drop-all-then-migrate, filename order; a PHP-callable step is run
 * with the adapter, as Tiger_Db_Migrator does). The services run their own transactions, so each test
 * starts and ends on emptied tables rather than inside a wrapping transaction.
 * Self-skips when TIGER_TEST_DB_NAME is unset, so the unit suite runs anywhere.
 *
 *   TIGER_TEST_DB_NAME (required)  TIGER_TEST_DB_SOCKET (unix socket; overrides host/port)
 *   TIGER_TEST_DB_HOST/_PORT (default 127.0.0.1/3306)  TIGER_TEST_DB_USER/_PASS (default root/'')
 */
abstract class IntegrationTestCase extends TestCase
{
    /** Fixed org ids — the install org (owns the box) and two tenants. */
    const ORG_INSTALL = '00000000-0000-4000-8000-000000000001';
    const ORG_A       = '00000000-0000-4000-8000-0000000000aa';
    const ORG_B       = '00000000-0000-4000-8000-0000000000bb';

    /** The core migrations this module depends on (relative to tiger-core/migrations). */
    const CORE_MIGRATIONS = ['0001_create_org.php', '0009_create_config.php', '0052_create_site_domain.php'];

    private static ?Zend_Db_Adapter_Abstract $sharedDb = null;
    private static bool $schemaReady = false;

    protected Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        parent::setUp();

        $name = getenv('TIGER_TEST_DB_NAME');
        if ($name === false || $name === '') {
            $this->markTestSkipped('Integration DB not configured (set TIGER_TEST_DB_NAME to run).');
        }

        $this->db = self::adapter((string) $name);
        $this->ensureSchema();
        self::ensureAcl();

        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        // A test drives a service IN PROCESS with no session — the shape of a stateless Bearer call, which
        // Tiger_Form exempts from CSRF (WEBSERVICES.md §8).
        Zend_Registry::set('tiger.auth.stateless', true);
        self::useConfig();
        Tiger_Model_Table::setActor(null);
        Tiger_Model_Table::setOrg(null);
        Tiger_Model_Org::setSiteOrgId(null);
        FakeCertificateService::reset();
        $this->truncateAll();
        $this->seedOrgs();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) { $this->truncateAll(); }
        Zend_Auth::getInstance()->clearIdentity();
        Tiger_Model_Table::setActor(null);
        Tiger_Model_Table::setOrg(null);
        Tiger_Model_Config::resetCache();
        parent::tearDown();
    }

    /**
     * The test config: a null log writer, the install org pinned via tiger.site.org_id (unless $siteOrg is
     * '' — then the founding org answers), and any extra tiger.* values.
     */
    protected static function useConfig(array $extra = [], string $siteOrg = self::ORG_INSTALL): void
    {
        $tiger = ['log' => ['writer' => 'null']];
        if ($siteOrg !== '') { $tiger['site'] = ['org_id' => $siteOrg]; }
        Zend_Registry::set('Zend_Config', new \Zend_Config(array_replace_recursive(['tiger' => $tiger], $extra), true));
        Tiger_Model_Config::resetCache();
        if (class_exists('Tiger_Log')) { \Tiger_Log::reset(); }
    }

    /**
     * Empty every table the module touches. The services run their OWN transactions (_transaction), so a
     * test can't wrap itself in one — each test starts and ends on empty tables instead.
     */
    protected function truncateAll(): void
    {
        foreach (['tigerssl_certificate_name', 'tigerssl_certificate', 'site_domain', 'config', 'org'] as $t) {
            $this->db->query("DELETE FROM `$t`");
        }
        Tiger_Model_Config::resetCache();
    }

    /** The three orgs, the install org founded first. */
    private function seedOrgs(): void
    {
        $at = ['2026-01-01 00:00:00', '2026-02-01 00:00:00', '2026-03-01 00:00:00'];
        foreach ([self::ORG_INSTALL => 'Install', self::ORG_A => 'Tenant A', self::ORG_B => 'Tenant B'] as $id => $name) {
            $this->db->insert('org', [
                'org_id' => $id, 'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name)),
                'status' => 'active', 'deleted' => 0, 'created_at' => array_shift($at),
            ]);
        }
    }

    /** Map a host to an org in core's site_domain (host → org). */
    protected function mapHost(string $host, string $orgId, string $status = 'active'): void
    {
        $this->db->insert('site_domain', [
            'site_domain_id' => \Tiger_Uuid::v4(), 'domain' => $host, 'org_id' => $orgId,
            'status' => $status, 'deleted' => 0, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** Sign in (non-persistent storage) as an admin of $orgId. */
    protected function login(string $orgId, string $role = 'admin', string $userId = '00000000-0000-4000-8000-0000000000ff'): void
    {
        Zend_Auth::getInstance()->getStorage()->write((object) [
            'org_id' => $orgId, 'user_id' => $userId, 'role' => $role,
        ]);
    }

    /**
     * A managed certificate straight through the models (bypassing ACME): the row + its names.
     *
     * @return string the certificate id
     */
    protected function makeCert(string $orgId, array $names, ?string $expiresAt = null): string
    {
        $m  = new \Tigerssl_Model_Certificate();
        $id = (string) $m->insert([
            'org_id' => $orgId, 'domain' => $names[0], 'status' => 'active', 'auto_renew' => 1,
            'expires_at' => $expiresAt ?? date('Y-m-d H:i:s', time() + 5 * 86400),
        ]);
        (new \Tigerssl_Model_CertificateName())->replaceNames($id, $orgId, $names);
        return $id;
    }

    /** Run a service method in process, as the signed-in identity. */
    protected function call(string $class, string $method, array $params = []): object
    {
        $svc = new $class(['action' => $method] + $params);
        return $svc->getResponse();
    }

    /** First response message (the translation key a service recorded), or ''. */
    protected function firstMessage(object $response): string
    {
        return isset($response->messages[0]) ? (string) $response->messages[0]->message : '';
    }

    /** A live certificate row by id (any org), or null — for asserting what a refused call left alone. */
    protected function certRow(string $id): ?array
    {
        $row = $this->db->fetchRow($this->db->select()->from('tigerssl_certificate')->where('certificate_id = ?', $id));
        return $row ?: null;
    }

    /** Live names of a certificate, primary first. */
    protected function names(string $certificateId): array
    {
        return (new \Tigerssl_Model_CertificateName())->namesFor($certificateId);
    }

    /** Load the real ACL (core acl.ini + this module's, via a scratch modules dir under APPLICATION_PATH). */
    private static function ensureAcl(): void
    {
        if (Zend_Registry::isRegistered('Zend_Acl')) { return; }
        @mkdir(APPLICATION_PATH . '/modules', 0777, true);
        $link = APPLICATION_PATH . '/modules/tigerssl';
        if (!is_link($link) && !is_dir($link)) { @symlink(dirname(__DIR__, 2), $link); }
        Zend_Registry::set('Zend_Acl', new \Tiger_Acl_Acl());
    }

    private static function adapter(string $name): Zend_Db_Adapter_Abstract
    {
        if (self::$sharedDb === null) {
            $cfg = [
                'dbname'   => $name,
                'username' => getenv('TIGER_TEST_DB_USER') ?: 'root',
                'password' => getenv('TIGER_TEST_DB_PASS') ?: '',
                'charset'  => 'utf8mb4',
            ];
            $socket = getenv('TIGER_TEST_DB_SOCKET');
            if ($socket) {
                $cfg['unix_socket'] = $socket;
            } else {
                $cfg['host'] = getenv('TIGER_TEST_DB_HOST') ?: '127.0.0.1';
                $cfg['port'] = (int) (getenv('TIGER_TEST_DB_PORT') ?: 3306);
            }
            self::$sharedDb = Zend_Db::factory('Pdo_Mysql', $cfg);
            Zend_Db_Table_Abstract::setDefaultAdapter(self::$sharedDb);
            Zend_Registry::set('Zend_Db', self::$sharedDb);
        }
        return self::$sharedDb;
    }

    /** Drop-all-then-migrate: the core tables, then TigerSSL's migrations in filename order. */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) { return; }
        $files = [];
        foreach (self::CORE_MIGRATIONS as $f) { $files[] = TIGER_CORE_PATH . '/migrations/' . $f; }
        $mine = glob(dirname(__DIR__, 2) . '/migrations/*.php') ?: [];
        sort($mine);
        $files = array_merge($files, $mine);

        foreach (['tigerssl_certificate_name', 'tigerssl_certificate', 'site_domain', 'config', 'org'] as $t) {
            $this->db->query("DROP TABLE IF EXISTS `$t`");
        }
        foreach ($files as $file) {
            foreach ((require $file)['up'] as $stmt) {
                if (!is_string($stmt) && is_callable($stmt)) { $stmt($this->db); continue; }
                $this->db->query($stmt);
            }
        }
        self::$schemaReady = true;
    }
}
