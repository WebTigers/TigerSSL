<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Model_Certificate — the issued-certificate store (`tigerssl_certificate`).
 *
 * One row per managed certificate, OWNED BY AN ORG (TIGER-511): what was issued, when it expires, where
 * the installer wrote it, and its renewal state. The hostnames it covers (the primary `domain` plus any
 * SANs) live in tigerssl_certificate_name. Extends Tiger_Model_Table for the UUID mint, actor/timestamp
 * stamps, and soft-delete; $_primary is REQUIRED (else the UUID mint targets the wrong column).
 *
 * Every finder an admin surface uses takes the org — there is no org-less lookup by id or domain. The one
 * install-wide read is findDueForRenewal(…, null), for the system renewal job, which renews each row as
 * its own org.
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
     * One org's certificate by id (excludes soft-deleted), or null — another org's id is "not found".
     *
     * @param  string $orgId         the owning org
     * @param  string $certificateId the certificate id
     * @return array|null            the row, or null
     */
    public function findForOrg(string $orgId, string $certificateId): ?array
    {
        if ($orgId === '' || $certificateId === '') { return null; }
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('certificate_id = ?', $certificateId)
                ->where('org_id = ?', $orgId)
                ->limit(1)
        );
        return $row ? $row->toArray() : null;
    }

    /**
     * One org's certificate by its primary domain (excludes soft-deleted), or null.
     *
     * @param  string $orgId  the owning org
     * @param  string $domain the primary domain / CN
     * @return array|null      the row, or null
     */
    public function findByDomainForOrg(string $orgId, string $domain): ?array
    {
        if ($orgId === '' || $domain === '') { return null; }
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', $orgId)
                ->where('domain = ?', $domain)
                ->limit(1)
        );
        return $row ? $row->toArray() : null;
    }

    /**
     * Every live certificate of one org, newest first.
     *
     * @param  string $orgId the owning org
     * @return array         the rows
     */
    public function forOrg(string $orgId): array
    {
        if ($orgId === '') { return []; }
        return $this->fetchAll(
            $this->activeSelect()->where('org_id = ?', $orgId)->order('created_at DESC')
        )->toArray();
    }

    /**
     * Update one org's certificate. The org is part of the WHERE, so another org's id updates nothing.
     *
     * @param  string $orgId         the owning org
     * @param  string $certificateId the certificate id
     * @param  array  $data          the columns to set
     * @return int                   affected rows (0 = not this org's / not found)
     */
    public function updateForOrg(string $orgId, string $certificateId, array $data): int
    {
        if ($orgId === '' || $certificateId === '') { return 0; }
        return $this->update($data, $this->_orgWhere($orgId, $certificateId));
    }

    /**
     * Soft-delete one org's certificate (another org's id deletes nothing).
     *
     * @param  string $orgId         the owning org
     * @param  string $certificateId the certificate id
     * @return int                   affected rows
     */
    public function softDeleteForOrg(string $orgId, string $certificateId): int
    {
        if ($orgId === '' || $certificateId === '') { return 0; }
        return $this->softDelete($this->_orgWhere($orgId, $certificateId));
    }

    /** The WHERE for "this certificate, in this org". */
    protected function _orgWhere(string $orgId, string $certificateId): array
    {
        $db = $this->getAdapter();
        return [
            $db->quoteInto('certificate_id = ?', $certificateId),
            $db->quoteInto('org_id = ?', $orgId),
        ];
    }

    /**
     * Certificates due for renewal: auto-renew on, not disabled, expiring within $withinDays.
     *
     * @param  int         $withinDays renew when this many days (or fewer) remain
     * @param  string|null $orgId      one org's certificates; null = every org's (the system job only)
     * @return array                   rows due for renewal, soonest first
     */
    public function findDueForRenewal(int $withinDays, ?string $orgId = null): array
    {
        $cutoff = date('Y-m-d H:i:s', time() + ($withinDays * 86400));
        $select = $this->activeSelect()
            ->where('auto_renew = ?', 1)
            ->where('status != ?', self::STATUS_DISABLED)
            ->where('expires_at IS NOT NULL')
            ->where('expires_at <= ?', $cutoff)
            ->order('expires_at ASC');
        if ($orgId !== null) {
            if ($orgId === '') { return []; }
            $select->where('org_id = ?', $orgId);
        }
        return $this->fetchAll($select)->toArray();
    }
}
