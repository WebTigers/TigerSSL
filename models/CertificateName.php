<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Model_CertificateName — the hostnames a certificate covers (`tigerssl_certificate_name`).
 *
 * A certificate is multi-domain (SAN): one row per covered name, the primary CN flagged `is_primary`. A
 * single-domain certificate is a list of one. `org_id` is copied from the parent certificate. A live name
 * is on one live certificate install-wide (the active-only unique `name_active`).
 *
 * @api
 */
class Tigerssl_Model_CertificateName extends Tiger_Model_Table
{
    protected $_name    = 'tigerssl_certificate_name';
    protected $_primary = 'certificate_name_id';

    /**
     * The names a certificate covers, primary first (excludes soft-deleted).
     *
     * @param  string $certificateId the parent certificate
     * @return array<int,string>     the hostnames
     */
    public function namesFor(string $certificateId): array
    {
        $rows = $this->fetchAll(
            $this->activeSelect()
                ->where('certificate_id = ?', $certificateId)
                ->order(['is_primary DESC', 'name ASC'])
        );
        $out = [];
        foreach ($rows as $r) { $out[] = (string) $r->name; }
        return $out;
    }

    /**
     * The live certificate id holding a name, or null.
     *
     * @param  string $name the hostname
     * @return string|null  the certificate_id, or null when no live certificate covers it
     */
    public function certificateFor(string $name): ?string
    {
        $row = $this->fetchRow($this->activeSelect()->where('name = ?', $name)->limit(1));
        return $row ? (string) $row->certificate_id : null;
    }

    /**
     * Set a certificate's covered names to exactly `$names` (primary = the first): keep the ones already
     * there, soft-delete the ones dropped, insert the new ones. Runs inside the caller's transaction.
     *
     * @param  string             $certificateId the parent certificate
     * @param  string             $orgId         the certificate's org (stamped on each name)
     * @param  array<int,string>  $names         the full name list, primary first
     * @return void
     */
    public function replaceNames(string $certificateId, string $orgId, array $names): void
    {
        $existing = [];
        foreach ($this->fetchAll($this->activeSelect()->where('certificate_id = ?', $certificateId)) as $r) {
            $existing[(string) $r->name] = (string) $r->certificate_name_id;
        }
        $primary = $names[0] ?? '';
        foreach ($existing as $name => $id) {
            if (!in_array($name, $names, true)) {
                $this->softDelete($this->getAdapter()->quoteInto('certificate_name_id = ?', $id));
            } else {
                $this->update(['is_primary' => $name === $primary ? 1 : 0], $this->getAdapter()->quoteInto('certificate_name_id = ?', $id));
            }
        }
        foreach ($names as $name) {
            if (isset($existing[$name])) { continue; }
            $this->insert([
                'certificate_id' => $certificateId,
                'org_id'         => $orgId,
                'name'           => $name,
                'is_primary'     => $name === $primary ? 1 : 0,
            ]);
        }
    }

    /**
     * Soft-delete every name of a certificate (it was removed from management).
     *
     * @param  string $certificateId the parent certificate
     * @return void
     */
    public function removeFor(string $certificateId): void
    {
        $this->softDelete($this->getAdapter()->quoteInto('certificate_id = ?', $certificateId));
    }
}
