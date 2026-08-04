<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Model_Challenge — the ephemeral HTTP-01 challenge store.
 *
 * NOTE: despite the "Model" name (that's just how the ZF1 module autoloader maps models/ → this class),
 * this is NOT a database table. Pending ACME challenges live for seconds-to-minutes during a single
 * issuance, so they belong in a short-TTL FILE CACHE, not a domain table (config-discipline: don't put
 * ephemeral state in the DB). Tigerssl_Service_Certificate::put()s a token→keyAuthorization pair right
 * before it tells the CA "ready", and Tigerssl_WellKnownController::challengeAction get()s it when the CA
 * calls back. Expired entries are swept on read.
 *
 * File: storage/cache/tigerssl/challenges.json  (0600, outside the docroot, never committed).
 */
class Tigerssl_Model_Challenge
{
    /** Entries older than this (seconds) are considered dead and swept. Issuance is quick; keep it tight. */
    const TTL = 600;

    /** Record a pending challenge: the CA will fetch /.well-known/acme-challenge/$token → $keyAuth. */
    public static function put(string $token, string $keyAuth): void
    {
        if ($token === '') { return; }
        $all = self::_load();
        $all[$token] = ['key' => $keyAuth, 'exp' => self::_now() + self::TTL];
        self::_save($all);
    }

    /** Return the key authorization for a currently-pending token, or null if unknown/expired. */
    public static function get(string $token): ?string
    {
        if ($token === '') { return null; }
        $all = self::_load();
        $e   = $all[$token] ?? null;
        if (!$e || ($e['exp'] ?? 0) < self::_now()) { return null; }
        return (string) $e['key'];
    }

    /** Drop a challenge once its authorization has been consumed (or on cleanup). */
    public static function forget(string $token): void
    {
        $all = self::_load();
        if (isset($all[$token])) { unset($all[$token]); self::_save($all); }
    }

    /** Load the cache, sweeping expired entries. Any read/parse error yields an empty set (fail-soft). */
    protected static function _load(): array
    {
        $file = self::_file();
        if (!is_file($file)) { return []; }
        try {
            $raw = (string) @file_get_contents($file);
            $all = $raw === '' ? [] : (json_decode($raw, true) ?: []);
        } catch (Throwable $e) {
            return [];
        }
        $now  = self::_now();
        $live = [];
        foreach ($all as $token => $e) {
            if (is_array($e) && ($e['exp'] ?? 0) >= $now) { $live[$token] = $e; }
        }
        return $live;
    }

    /** Persist the cache (0600). Best-effort — a write failure never throws into issuance. */
    protected static function _save(array $all): void
    {
        $file = self::_file();
        $dir  = dirname($file);
        if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
        try {
            @file_put_contents($file, json_encode($all, JSON_UNESCAPED_SLASHES), LOCK_EX);
            @chmod($file, 0600);
        } catch (Throwable $e) {
            // ignore — the WellKnownController will simply 404 the token and the CA retries
        }
    }

    /** Resolve the cache path under the app's writable storage dir. */
    protected static function _file(): string
    {
        $base = defined('APPLICATION_PATH') ? dirname(APPLICATION_PATH) : sys_get_temp_dir();
        return $base . '/storage/cache/tigerssl/challenges.json';
    }

    /** Current unix time (indirected so tests can freeze it if needed). */
    protected static function _now(): int
    {
        return time();
    }
}
