<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Model_Certificate — the issued-certificate store (`tigerssl_certificate`).
 *
 * One row per managed domain: what was issued, when it expires, where the installer wrote it, and its
 * renewal state. Extends Tiger_Model_Table for the UUID mint, actor/timestamp stamps, and soft-delete;
 * $_primary is REQUIRED (else the UUID mint targets the wrong column and the insert throws).
 *
 * Secrets do NOT live here: the ACME account key + issued private keys are files on disk (0600) referred
 * to by *_path columns — never a plaintext key column (FEATURES §8, §10).
 *
 * @api
 */
class Tigerssl_Model_Certificate extends Tiger_Model_Table
{
    protected $_name    = 'tigerssl_certificate';
    protected $_primary = 'certificate_id';

    /** Lifecycle states for the `status` column. */
    const STATUS_PENDING  = 'pending';   // an order is in flight
    const STATUS_ACTIVE   = 'active';    // issued + installed, healthy
    const STATUS_EXPIRING = 'expiring';  // inside the renewal window
    const STATUS_ERROR    = 'error';     // last issue/renew failed (cert may still be serving)
    const STATUS_DISABLED = 'disabled';  // operator turned it off

    /**
     * Find a managed certificate by its primary domain (excludes soft-deleted).
     *
     * @param  string $domain the primary domain / CN
     * @return array|null      the row, or null if none
     */
    public function findByDomain(string $domain): ?array
    {
        $select = $this->activeSelect()->where('domain = ?', $domain)->limit(1);
        $row    = $this->fetchRow($select);
        return $row ? $row->toArray() : null;
    }

    /**
     * Certificates due for renewal: auto-renew on, not disabled, expiring within $withinDays.
     *
     * @param  int   $withinDays renew when this many days (or fewer) remain
     * @return array             rows due for renewal
     */
    public function findDueForRenewal(int $withinDays): array
    {
        $cutoff = date('Y-m-d H:i:s', time() + ($withinDays * 86400));
        $select = $this->activeSelect()
            ->where('auto_renew = ?', 1)
            ->where('status != ?', self::STATUS_DISABLED)
            ->where('expires_at IS NOT NULL')
            ->where('expires_at <= ?', $cutoff)
            ->order('expires_at ASC');
        return $this->fetchAll($select)->toArray();
    }
}
