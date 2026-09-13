# AGENTS.md — TigerSSL

> **Step 0 — check core before you build.** Before writing any mechanism, grep
> [`../tiger-core/CAPABILITIES.md`](../tiger-core/CAPABILITIES.md) — a generated, CI-checked index of every `Tiger_*` class and module.
> Core probably already has it. TigerImage once reimplemented `Tiger_View_Helper_I18n` by hand because this
> hop was skipped, and lost a property core had designed in. **Assume a capability exists until you have
> grepped the index and confirmed it doesn't.**

Orientation for AI agents (and humans) working in this repo. Read this first, then
[`FEATURES.md`](FEATURES.md) — the design-of-record. If you change a decision, update FEATURES.md in the
same change; the "why" is the most perishable part. This module follows tiger-core's conventions
(`AGENTS.md` / `ADMIN.md` / `WEBSERVICES.md` in the tiger-core repo) — don't reinvent them.

## What this is

A **Tiger module** that issues + renews Let's Encrypt / ACME TLS certificates. It installs as
`application/modules/tigerssl/` and self-registers via the module scan. Issuance is pure PHP; the one
privileged step (install the cert + reload the web server) is isolated behind a detected, opt-in hook.
See [`README.md`](README.md) for the pitch, [`module.json`](module.json) for the manifest.

## Invariants — do not break these

- **Issuance is unprivileged; installation is privileged. Keep them apart.** Nothing in the *issue* path
  needs root. The *install/reload* path is the only privileged surface and is one **allow-listed**
  command behind an opt-in sudoers hook (FEATURES §5). Never widen it.
- **Never touch web-server config as a side effect of install/activate.** The HTTP-01 challenge is a
  **native controller route** (`configs/routes.ini`), never an `.htaccess`/vhost edit — that's the
  ARCHITECTURE §6 rule and the reason 1-click install works. Don't "simplify" it to a static file.
- **Stand down where the host owns SSL.** On cPanel/Plesk (detected), the module does nothing and says
  so. Never issue where you have no way to install/reload — that's a silent failure.
- **Fail-soft, always.** A failed issue/renew logs + flags the row, and the **existing cert keeps
  serving**. A cert operation must never 500 a request or disable a working cert.
- **Secrets never hit the DB in plaintext.** The ACME account key + issued private keys live on disk
  `0600` outside the docroot (account key may use the secrets tier). No key columns in the DB.
- **Default to Let's Encrypt STAGING.** First issuance runs against staging (LE production has real rate
  limits). Flipping to production is a deliberate Settings choice.
- **No certbot, no Python, no third-party network beyond the ACME CA.** The ACME engine is the vendored
  pure-PHP client in `vendor-lib/` (FEATURES §7). Keep the footprint tiny and pure-PHP.

## Platform conventions (Tiger-native)

- **Migrations use timestamp versions** (`YYYYMMDDHHMMSS_*.php`), never `0001` — the `tiger_migration`
  ledger is one shared bare-version namespace across core + app + all modules.
- **`Tiger_Model_Table` subclasses must declare `protected $_primary = '<pk>'`** or the UUID mint targets
  the wrong column and the insert throws.
- **Services** validate → transaction; settings save over `/api` writing to the `config` tier
  (`$config->set('global', '', $key, $value)` — 4 args). Ephemeral challenge state lives in
  `storage/cache/tigerssl/`, **never** the eager `config` table.
- **Forms** are `Tiger_Form` from array config; **i18n** keys are semantic (`tigerssl.*`). In views there
  is **no `_t()` helper** — pull the translator from the registry (see tiger-core AGENTS.md i18n).
- **ACL:** the admin surface (controller + `/api` services) is admin-gated; the **WellKnown challenge
  controller is guest-allowed** (the ACME CA is anonymous) — the one deliberate public resource.

## The ACME client (vendor-lib/)

The ACME wire protocol is **vendored**, not hand-written (JWS/nonce/order handling is security-sensitive —
FEATURES §7). Vendored: **`skoerfgen/ACMECert` v3.7.2** (MIT, pure PHP), under `vendor-lib/ACMECert/` with
its upstream layout + LICENSE preserved, pinned in `vendor-lib/README.md`. `Tigerssl_Service_Certificate::
_issueViaAcme()` is the only thing that talks to it (via `getCertificateChain()`, with our http-01 handler
publishing tokens through `Tigerssl_Model_Challenge`). Do **not** edit `vendor-lib/` or hand-roll the JOSE
layer; to update, re-fetch the pinned files and bump the version in the vendor README.

## Dev / test loop

Drop it into a Tiger app's `application/modules/tigerssl/`, run `vendor/bin/tiger migrate`, and it
self-registers (the SSL screen appears under admin Settings). The dev/test host is **tiger-dev** (deploy
code there only; operate as `ec2-user`). **Test against Let's Encrypt STAGING** — never burn production
rate limit on a test. Clean up any test `tigerssl_certificate` rows + on-disk keys afterward.

## Layout

```
Bootstrap.php               registers the admin Settings entry + the renewal schedule job
controllers/
  WellKnownController.php    serves /.well-known/acme-challenge/<token> (guest — the ACME HTTP-01 check)
  AdminController.php        the admin surface (certificates list + add + settings)
services/
  Certificate.php            /api: issue | renew | renewDue | remove | datatable | status — drives the ACME client
  Settings.php               /api: save settings to the config tier
models/Certificate.php       the cert store (UUID PK, $_primary, standard columns)
forms/                       Domain (add), Settings
migrations/                  timestamp-versioned schema (tigerssl_certificate)
configs/  acl.ini  routes.ini (the .well-known route)  module.ini (defaults)
views/scripts/admin/         index (certs) + settings screens
languages/en/                semantic tigerssl.* keys
bin/tigerssl.php             module CLI (cron): issue | renew | renew-due | status
vendor-lib/                  the vendored pure-PHP ACME v2 client (fetched at build; pinned)
docs/en/                     admin help (tiger:doc), mirrored to TigerDocs-content
storage/cache/tigerssl/      ephemeral challenge tokens + issuance locks — NOT committed
media/                       store/marketing art (icon, banner, screenshots)
```
