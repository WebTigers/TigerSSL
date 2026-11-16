<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Support;

use RuntimeException;
use Tigerssl_Service_Certificate;

/**
 * The certificate service with its two outside-world seams faked: the ACME order (_issueViaAcme — no
 * network, no CA) and the installer (_install — no files written). Every order is RECORDED (org + names),
 * so a test can assert exactly which names went to the CA, and as which org. Everything else — the ACL
 * gate, the forms, the org lane, ownership, the store — is the real code.
 */
class FakeCertificateService extends Tigerssl_Service_Certificate
{
    /** @var array<int,array{org:string,names:array}> every ACME order placed, in order */
    public static array $orders = [];

    /** @var array<int,string> a primary name whose order should fail (the CA refused it) */
    public static array $failFor = [];

    public static function reset(): void
    {
        self::$orders  = [];
        self::$failFor = [];
    }

    /** The ACL resource is the real service, not this subclass. */
    protected function _isAllowed($privilege, $resource = null)
    {
        return parent::_isAllowed($privilege, $resource ?? Tigerssl_Service_Certificate::class);
    }

    protected function _issueViaAcme(string $orgId, array $names): array
    {
        self::$orders[] = ['org' => $orgId, 'names' => array_values($names)];
        if (in_array($names[0] ?? '', self::$failFor, true)) {
            throw new RuntimeException('CA refused ' . $names[0]);
        }
        return [
            'cert' => 'CERT', 'key' => 'KEY', 'chain' => 'CHAIN',
            'expires_at' => date('Y-m-d H:i:s', time() + 90 * 86400),
            'serial' => 'abc', 'fingerprint' => 'ff',
        ];
    }

    protected function _install(string $domain, array $result): array
    {
        return ['cert' => "/fake/$domain/cert.pem", 'key' => "/fake/$domain/privkey.pem", 'chain' => "/fake/$domain/chain.pem"];
    }
}
