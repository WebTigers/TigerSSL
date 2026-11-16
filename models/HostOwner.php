<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Model_HostOwner — which org owns a hostname, for certificate issuance (TIGER-511).
 *
 * NOTE: like Tigerssl_Model_Challenge this is not a table — it answers one question over core's
 * `site_domain` (host → org) map, through its model finders. An org may only put a name on a certificate
 * if it OWNS that name:
 *
 *   - a host mapped in `site_domain` belongs to its org (any status — a suspended tenant's host is still
 *     that tenant's, never up for grabs);
 *   - `www.<host>` with no row of its own belongs to whoever owns `<host>` (core maps apex + www as two
 *     rows, but a site that only mapped its apex still owns its www);
 *   - an UNMAPPED host belongs to the INSTALL org (`Tiger_Model_Org::installOrgId`) — exactly how core
 *     routes it: a request for a host no tenant claims is served as the install's own site. That is what
 *     keeps a single-tenant install (no `site_domain` rows at all) issuing for its own host unchanged,
 *     while a tenant org can never certify a host it has not been mapped to.
 */
class Tigerssl_Model_HostOwner
{
    /**
     * The install's own org — `tiger.site.org_id`, else the founding org ('' before any org exists).
     *
     * @return string
     */
    public static function installOrgId(): string
    {
        $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $t    = $cfg ? $cfg->get('tiger') : null;
        $site = $t ? $t->get('site') : null;
        $conf = ($site && $site->get('org_id')) ? (string) $site->get('org_id') : '';
        return (string) Tiger_Model_Org::installOrgId($conf);
    }

    /**
     * The org that owns a hostname (see the class docblock for the rule).
     *
     * @param  string $host the hostname
     * @return string       the owning org id ('' only when no org exists at all)
     */
    public static function ownerOf(string $host): string
    {
        $host = Tiger_Model_SiteDomain::normalizeHost($host);
        $map  = new Tiger_Model_SiteDomain();
        $row  = $map->findByHost($host);
        if (!$row && strncmp($host, 'www.', 4) === 0) {
            $row = $map->findByHost(substr($host, 4));
        }
        return $row ? (string) $row->org_id : self::installOrgId();
    }

    /**
     * The first name in `$names` that `$orgId` does NOT own, or null when it owns them all.
     *
     * @param  string            $orgId the org asking
     * @param  array<int,string> $names the hostnames
     * @return string|null              the first foreign name, or null
     */
    public static function firstForeign(string $orgId, array $names): ?string
    {
        foreach ($names as $name) {
            if ($orgId === '' || self::ownerOf((string) $name) !== $orgId) { return (string) $name; }
        }
        return null;
    }
}
