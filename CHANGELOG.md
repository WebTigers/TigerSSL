# Changelog

All notable changes to TigerSSL are recorded here. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/); versioning is SemVer with a `-beta` stability suffix.

## [1.0.0] — 2026-08-24

**1.0** — the module line follows Tiger 1.0.

### Changed
- Version is now `1.0.0` (was `0.1.0-beta`).
- Ships the full six-locale UI (en/es/pt/hi/de/fr).

### Status — read before relying on automatic issuance
The ACME client, the certificate store, the admin screen, the HTTP-01 `.well-known` route and the
renewal job are built. **Live end-to-end issuance against Let's Encrypt has not been proven on a real
domain yet**, the privileged install/reload hook is still being wired, and **DNS-01 (and therefore
wildcard certificates) is roadmap**. Point Settings at Let's Encrypt **staging** first — the defaults
do, deliberately, because production has real rate limits.

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
- Issuance is code-complete + verified in isolation; the **live** staging issue on a real domain, the
  privileged install/reload hook, and DNS-01 (wildcards) are the next milestones — see FEATURES §12.
