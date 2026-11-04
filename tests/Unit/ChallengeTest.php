<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tigerssl_Model_Challenge;

/**
 * Tigerssl_Model_Challenge — the ephemeral HTTP-01 challenge store (a short-TTL file cache, NOT a DB
 * table). This is the backing store the native WellKnownController serves to the ACME CA during an
 * issuance, so its contract IS the domain-control endpoint's security contract:
 *
 *   - a pending token resolves to exactly its key authorization (put → get),
 *   - an UNKNOWN token resolves to null (the controller 404s — no enumeration),
 *   - an EXPIRED token resolves to null AND is swept on the next load (a stale token can't be replayed),
 *   - a corrupt cache file fails soft to an empty set (a garbled file never fatals an issuance),
 *   - the empty token is never stored and never resolves.
 */
final class ChallengeTest extends TestCase
{
    /** The deterministic cache path the model derives from dirname(APPLICATION_PATH). */
    private function cacheFile(): string
    {
        return dirname(APPLICATION_PATH) . '/storage/cache/tigerssl/challenges.json';
    }

    private function writeRaw(string $contents): void
    {
        $file = $this->cacheFile();
        if (!is_dir(dirname($file))) { mkdir(dirname($file), 0700, true); }
        file_put_contents($file, $contents);
    }

    protected function setUp(): void
    {
        @unlink($this->cacheFile());
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheFile());
    }

    public function testPutThenGetReturnsTheKeyAuthorization(): void
    {
        Tigerssl_Model_Challenge::put('token-abc', 'token-abc.keyauthz');
        $this->assertSame('token-abc.keyauthz', Tigerssl_Model_Challenge::get('token-abc'));
    }

    public function testUnknownTokenReturnsNull(): void
    {
        Tigerssl_Model_Challenge::put('token-abc', 'token-abc.keyauthz');
        $this->assertNull(
            Tigerssl_Model_Challenge::get('never-issued'),
            'an unknown token must resolve to null so the endpoint can 404 without leaking'
        );
    }

    public function testEmptyTokenIsNeverStoredAndNeverResolves(): void
    {
        Tigerssl_Model_Challenge::put('', 'should-not-store');
        $this->assertNull(Tigerssl_Model_Challenge::get(''), 'the empty token must not resolve');
        $this->assertFalse(is_file($this->cacheFile()), 'put("") must not even create the cache file');
    }

    public function testForgetDropsAToken(): void
    {
        Tigerssl_Model_Challenge::put('t1', 'k1');
        Tigerssl_Model_Challenge::put('t2', 'k2');
        Tigerssl_Model_Challenge::forget('t1');

        $this->assertNull(Tigerssl_Model_Challenge::get('t1'), 'the forgotten token is gone');
        $this->assertSame('k2', Tigerssl_Model_Challenge::get('t2'), 'its sibling is untouched');
    }

    public function testExpiredTokenResolvesToNull(): void
    {
        // Write an entry whose lifetime is already in the past — the model stamps exp = now + TTL on
        // put(), so an expired entry can only be produced by writing the file directly, exactly as a
        // slow issuance leaving a stale token behind would.
        $this->writeRaw(json_encode(['stale' => ['key' => 'stale.keyauthz', 'exp' => time() - 10]]));

        $this->assertNull(
            Tigerssl_Model_Challenge::get('stale'),
            'an expired token must resolve to null — a stale challenge cannot be replayed'
        );
    }

    public function testExpiredEntryIsSweptOnLoad(): void
    {
        // A live entry next to a dead one; a load must drop the dead one from the persisted set.
        $this->writeRaw(json_encode([
            'live' => ['key' => 'live.keyauthz', 'exp' => time() + 300],
            'dead' => ['key' => 'dead.keyauthz', 'exp' => time() - 1],
        ]));

        // A mutation re-persists the swept set: put() loads (dropping the dead entry) then saves,
        // forget() loads then saves — so after this round-trip the dead entry is gone from the file.
        Tigerssl_Model_Challenge::put('tmp', 'tmp.k');
        Tigerssl_Model_Challenge::forget('tmp');

        $persisted = json_decode((string) file_get_contents($this->cacheFile()), true);
        $this->assertArrayHasKey('live', $persisted, 'the live entry survives');
        $this->assertArrayNotHasKey('dead', $persisted, 'the expired entry is swept from the file');
    }

    public function testCorruptCacheFileFailsSoftToEmpty(): void
    {
        $this->writeRaw('}{ this is not json');
        // Must not throw, and must resolve every token to null (the garbled file is treated as empty).
        $this->assertNull(Tigerssl_Model_Challenge::get('anything'));
    }

    public function testNonArrayEntriesAreIgnored(): void
    {
        // A malformed entry (scalar instead of the [key,exp] shape) must not resolve or fatal.
        $this->writeRaw(json_encode(['weird' => 'not-an-array', 'ok' => ['key' => 'k', 'exp' => time() + 60]]));
        $this->assertNull(Tigerssl_Model_Challenge::get('weird'), 'a non-array entry resolves to null');
        $this->assertSame('k', Tigerssl_Model_Challenge::get('ok'), 'a well-formed sibling still resolves');
    }
}
