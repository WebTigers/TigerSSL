# Changelog

All notable changes to TigerSSL are recorded here. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/); versioning is SemVer.

## [1.1.0] — 2026-11-16

Certificates are per org and multi-domain (TIGER-511). Requires Tiger >= 1.59.0.

### Changed
- A certificate belongs to an org. The grid, issue, renew, remove and the /api `renewDue` act for the
  caller's org only; another org's certificate is "not found".
- A certificate covers several hostnames (SAN), stored in the new `tigerssl_certificate_name` table. An
  org may only request names it owns: its `site_domain` hosts and their `www.`; the install org also owns
  any host no tenant is mapped to, so a single-site install issues for its own host as before.
- Settings split per key: auto-renew is per org; the CA, ACME account contact, key type, renewal window,
  installer strategy/path and reload command are install-wide and only the install org can change them.
  Those install-level values are read from the install-wide tier only, never an org row.
- Domain keys are kept per org (`storage/tigerssl/keys/<org>/`); an existing key is adopted by the install org.
- Renew and remove are `Tiger_Form`-validated with CSRF; the remove confirmation uses the in-app modal.

### Fixed
- The daily renewal job never registered (it used `handler`/`interval`, which TigerSchedule does not
  read). It now renews every org's due certificates, each as its own org, re-checking that the org still
  owns every name on it, and skips an org that turned auto-renew off.
- Removing a certificate passed its bare id as the SQL WHERE and failed.
- The per-org domain index let only one soft-deleted row per domain exist; it is now active-only.

### Migrations
- `20261116120000` active-only `(org_id, domain)` unique · `20261116120100` creates
  `tigerssl_certificate_name` · `20261116120200` stamps `org_id = ''` rows to the install org and writes
  each live certificate's names from `domain` + `sans`.

## [1.0.6] — 2026-11-13

### Changed
- Requires Tiger >= 1.0.0 (was `>=0.8.0-beta`); dropped the leftover "beta" status wording from FEATURES.md and this changelog's header.

## [1.0.4] — 2026-09-13

### Changed
- AGENTS.md: Step 0 — grep tiger-core/CAPABILITIES.md before building anything; core probably already has it.

## [1.0.3] — 2026-09-13

### Changed
- README, CHANGELOG and release notes describe limitations as capability rather than build progress; the work items are tracked in Jira (TIGER-108/109/110). Two missing changelog entries backfilled.

## [1.0.2] — 2026-09-10

### Fixed
- `module.json` version did not match the release tag, which leaves the update check offering an update
  that can never complete: the update applies, records the manifest's older version, and advertises
  itself again. A CI guard now fails a tag whose manifest disagrees with it.

## [1.0.1] — 2026-08-28

### Changed
- The marketplace listing blurb is translated into the other five locales.

## [1.0.0] — 2026-08-24

**1.0** — the module line follows Tiger 1.0.

### Changed
- Version is now `1.0.0` (was `0.1.0-beta`).
- Ships the full six-locale UI (en/es/pt/hi/de/fr).

### Roadmap
- DNS-01, and with it wildcard certificates.

## [0.1.0-beta] — unreleased

### Added
- Initial module scaffold: manifest, ACL, routes, config defaults, admin shell, i18n.
- **Native HTTP-01 challenge endpoint** — `Tigerssl_WellKnownController` serves
  `/.well-known/acme-challenge/<token>` with zero web-server config (a real route, guest-allowed).
- **Certificate store** — `tigerssl_certificate` table + `Tigerssl_Model_Certificate`.
- **Admin surface** — certificates list (DataTables), add-domain form, settings (CA env, contact, key
  type, renewal window, installer strategy).
- **Certificate service** — `issue | renew | renewDue | remove | datatable | status`, **wired to the
  vendored ACME client** (`_issueViaAcme()` drives `getCertificateChain()`; our http-01 handler publishes
  tokens via `Tigerssl_Model_Challenge`; account + per-domain keys minted once and reused, `0600`).
- **Vendored ACME v2 client** — `skoerfgen/ACMECert` **v3.7.2** (MIT) under `vendor-lib/ACMECert/`,
  upstream layout + LICENSE preserved, pinned in `vendor-lib/README.md`.
- **Renewal** — a TigerSchedule daily job + `bin/tigerssl.php renew-due` for a system cron.
- Design-of-record ([FEATURES.md](FEATURES.md)) + repo orientation ([AGENTS.md](AGENTS.md)).

### Notes
- Ships targeting **Let's Encrypt staging** by default (production rate limits are real).
