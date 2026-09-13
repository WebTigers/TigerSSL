# Changelog

All notable changes to TigerSSL are recorded here. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/); versioning is SemVer with a `-beta` stability suffix.

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
