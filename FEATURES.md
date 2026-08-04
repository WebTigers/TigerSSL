# TigerSSL — Features & design-of-record

Free, automatic HTTPS for a Tiger server: **Let's Encrypt / ACME** certificate issuance and renewal,
driven from the admin, no shell required for the day-to-day. This document is the **design-of-record** —
it records the decisions and their *why* so we don't relitigate them. For the pitch see
[README.md](README.md); for the module manifest see [module.json](module.json); for how to work in the
repo see [AGENTS.md](AGENTS.md).

> **Status: early beta.** The module structure, the native HTTP-01 challenge endpoint, the certificate
> store, the admin surface, and the renewal job are built. The ACME wire protocol runs through a
> **vendored pure-PHP ACME v2 client** (`vendor-lib/ACMECert`, `skoerfgen/ACMECert` v3.7.2, see §7) and
> **issuance is wired to it** (`_issueViaAcme()`) — what remains is the live end-to-end test against
> Let's Encrypt staging on a real domain, the privileged install/reload hook (§5), and DNS-01 (§4).

---

## 0. The one principle

**Issuance is unprivileged and portable; installation is privileged and host-specific. Keep them
apart.** Getting a certificate (answer an ACME challenge, fetch the signed cert) is something a PHP
module can do cleanly on any host. *Installing* it (write it where the web server reads it, then reload
the web server) needs root and touches server config — so it only happens where **you own the box**, and
it is isolated behind one narrow, auditable hook. This split is the whole design.

---

## 1. Where SSL automation comes from (context)

Before ~2015 a TLS cert was a paid product from a CA (VeriSign/Comodo/DigiCert): generate a CSR, pay,
prove domain control by email, hand-install the chain, renew yearly by hand. **Let's Encrypt** (the
nonprofit ISRG, backed by EFF + Mozilla, GA 2016) made certs **free, automated, and open**, driven by a
protocol: **ACME** (*Automated Certificate Management Environment*, RFC 8555). **certbot** is the EFF's
reference ACME client; **cPanel AutoSSL** is a host-level, root-owned service that runs the same idea for
every account (using Sectigo or Let's Encrypt underneath) — which is exactly why an unprivileged cPanel
*user* never runs it themselves. TigerSSL brings ACME issuance *into* Tiger for the servers where the
operator **does** have that control.

---

## 2. How ACME works (what TigerSSL automates)

1. Register an **account key** with the CA (Let's Encrypt by default).
2. Request a cert for one or more domains → the CA returns **challenges** (prove domain control).
3. Answer a challenge:
   - **HTTP-01** *(v1 — the default)* — serve a token at
     `http://<domain>/.well-known/acme-challenge/<token>`; the CA fetches it over port 80.
   - **DNS-01** *(roadmap)* — publish a `_acme-challenge` TXT record; the only path to **wildcards**,
     and works when port 80 is unreachable. Needs a DNS provider API.
4. Send a CSR → the CA validates the challenge → returns the signed cert + chain.
5. Certs live **90 days**; TigerSSL auto-renews at ~30 days remaining (§6).

---

## 3. HTTP-01, served natively — the elegant fit

The CA's HTTP-01 check hits `/.well-known/acme-challenge/<token>`. TigerSSL answers it with a **native
controller route** (`Tigerssl_WellKnownController::challengeAction`, mapped in `configs/routes.ini`) —
**not** a rewrite rule, not a dropped static file, not an `.htaccess` edit. This is the ARCHITECTURE §6
rule honored: *a module never touches web-server config, so it works the instant it's activated on any
host.* The controller is **guest-allowed** (the CA is anonymous) and returns the key authorization as
`text/plain`. Pending token → key-authorization pairs are held in a short-lived cache file under
`storage/cache/tigerssl/` (challenges are ephemeral — no table). Because it's a real route, it works
behind the ALB, and an HTTP→HTTPS redirect is fine (Let's Encrypt follows redirects).

---

## 4. Challenge types

| Type | Status | Use |
|---|---|---|
| **HTTP-01** | **v1** | single domains + SANs on a box that answers port 80 (the common case) |
| **DNS-01** | roadmap | **wildcards** (`*.example.com`), or when port 80 isn't reachable; pluggable DNS adapters (Route53 first — TigerCloud lives on AWS) |
| **TLS-ALPN-01** | not planned | belongs to reverse proxies (Caddy/Traefik), not a PHP app |

---

## 5. Installation & reload — the one privileged step (honest section)

Writing the issued cert where the web server reads it, then **reloading** the web server, needs root.
TigerSSL treats this as a **pluggable "installer" strategy**, detected not assumed:

| Strategy | Where | What it does |
|---|---|---|
| **`sudo-hook`** | VPS / EC2 / **TigerCloud** (you own root) | writes cert+key to a known path and runs **one** allow-listed command via a narrow `sudoers` entry (e.g. `systemctl reload httpd`). TigerCloud's golden AMI ships this hook pre-installed — nothing for the user to configure. |
| **`write-only`** | you manage the web server yourself | writes cert+key + chain to a configured directory and **stops** — you point Apache/nginx at it and reload on your schedule. No privilege needed. |
| **`stand-down`** | **cPanel / Plesk / host-managed SSL** | detects host AutoSSL (e.g. `/usr/local/cpanel`, or a config flag) and **does nothing** — the host already issues + renews. The admin screen says so plainly instead of pretending. |

**Never** does the module edit a vhost, an `.htaccess`, or a DNS zone as a side effect of *install/
activate* — that would break 1-click install (ARCHITECTURE §6). The privileged reload is a **separate,
opt-in, single-command** hook the operator (or the TigerCloud AMI) grants deliberately. Least privilege:
the sudoers entry allows exactly one reload command, nothing else.

---

## 6. Renewal — automatic, and it degrades gracefully

- Certs are checked daily; any with **≤ `renew_before_days`** (default 30) remaining are re-issued.
- **Preferred driver: TigerSchedule** — if the `schedule` capability is present, TigerSSL registers a
  daily `tigerssl:renew-due` job (WP-pseudo-cron style, no system crontab needed). If not, `bin/tigerssl.php
  renew-due` is a one-line **system cron** the operator adds.
- Renewal is **idempotent and fail-soft**: a renewal failure logs (`Tiger_Log`) + flags the row
  `status=error` + `last_error`, and the **existing cert keeps serving** until its real expiry — a
  failed renew never takes the site down. It retries on the next run.

---

## 7. The ACME engine — vendored, pure PHP (no certbot, no Python, no Composer)

Per Tiger's install-distribution model (tiny, pure-PHP, no end-user Composer), TigerSSL **does not shell
out to certbot** (that needs Python + root and edits your web server). Instead it **vendors a proven,
pure-PHP ACME v2 client** under `vendor-lib/ACMECert/` — **`skoerfgen/ACMECert` v3.7.2** (MIT, pure PHP,
needs only ext-`openssl` + ext-`curl`, HTTP-01 + DNS-01, wildcard-capable), copied in verbatim with its
upstream layout + LICENSE preserved and pinned by version + commit (see `vendor-lib/README.md`).
`Tigerssl_Service_Certificate::_issueViaAcme()` is the thin wrapper that drives it: account key (minted
once, reused), `register()`, per-domain key, and `getCertificateChain($domainKey, $config, $handler)` —
where OUR `http-01` handler publishes each token via `Tigerssl_Model_Challenge` (served by the native
route) rather than writing a docroot file.

**Why vendor rather than hand-roll:** the ACME wire protocol (JWS signing, nonce replay handling, order
polling, EAB) is intricate security-sensitive code where a subtle bug is a silent failure or a weakness.
A battle-tested library is the responsible choice; hand-writing the JOSE layer is not. It is the module's
only third-party dependency and it is pure PHP.

---

## 8. The certificate store

One table, `tigerssl_certificate` (`Tigerssl_Model_Certificate`, standard columns + `$_primary =
'certificate_id'`):

| Column | Meaning |
|---|---|
| `certificate_id` | UUID PK |
| `org_id` | scope owner (multi-tenant capable; global `''` for the platform host) |
| `domain` | the primary domain (CN) |
| `sans` | JSON array of extra SAN domains |
| `challenge_type` | `http-01` \| `dns-01` |
| `installer` | `sudo-hook` \| `write-only` \| `stand-down` |
| `auto_renew` | `TINYINT(1)` |
| `issued_at` / `expires_at` | from the issued cert |
| `serial` / `fingerprint` | issued-cert identity |
| `cert_path` / `key_path` / `chain_path` | where the installer wrote them |
| `status` | `pending` \| `active` \| `expiring` \| `error` \| `disabled` |
| `last_error` | populated by a failed issue/renew |
| standard columns | `deleted`, `created_by`, `updated_by`, `created_at`, `updated_at` |

Account keys + issued private keys are **secrets** — stored on disk with `0600` outside the web root (or,
for the account key, in the `local.ini`/secrets tier), **never** in the DB in plaintext.

---

## 9. Admin surface (`/ssl/admin`)

Built per [ADMIN.md](ADMIN.md) — thin controller, `/api` mutations, PUMA admin shell, no bespoke CSS:

- **Certificates** — a DataTables grid: domain, status pill (active / expiring / error), expiry
  countdown, auto-renew toggle, actions (issue-now / renew / remove / view chain).
- **Add a domain** — a validated form (domain + optional SANs + challenge type) → `issue()`.
- **Settings** — CA environment (**Let's Encrypt production vs staging** — staging first to avoid rate
  limits while testing), contact email (expiry notices), key type (ECDSA P-256 default / RSA-2048),
  `renew_before_days`, installer strategy + reload command, auto-renew default.
- **Host status banner** — surfaces the detected environment: *"You own this server — TigerSSL will issue
  and reload"* vs *"This host manages SSL for you (cPanel AutoSSL) — TigerSSL is standing down."*

---

## 10. Security posture (honest)

- **Issuance is safe on any host** (unprivileged: a file in the webroot + an outbound HTTPS call to the
  CA). **Installation needs root** and is isolated to one allow-listed reload command (§5) — the only
  privileged surface, opt-in and auditable.
- **Private keys + account key never touch the DB in plaintext** — `0600` on disk outside the docroot /
  the secrets tier.
- **The challenge endpoint is guest-readable by design** (the CA is anonymous) but returns *only* the
  key authorization for a *currently-pending* token, from a short-TTL cache — it can't enumerate or leak.
- **Rate-limit aware.** Let's Encrypt has real issuance rate limits; the Settings default to **staging**
  for first runs, and renewal backs off on failure rather than hammering.
- **Fail-soft everywhere.** A failed issue/renew never disables a working cert or 500s a request.

---

## 11. Rejected alternatives (so we don't relitigate)

| Rejected | Why | Chosen instead |
|---|---|---|
| Shell out to **certbot** | needs Python + root, and its `--apache`/`--nginx` plugins **edit your web server** — breaks portability + 1-click install | vendored **pure-PHP ACME v2** client; native HTTP-01 responder |
| Drop the challenge as a **static file** / `.htaccess` rule | touches the filesystem/web-server outside the module — the §6 violation | a **native controller route** — zero infra, works on activate |
| Store issued **private keys in the DB** | plaintext secrets in the config/content store | on-disk `0600` outside the docroot; secrets tier for the account key |
| Pretend to manage SSL on **shared hosting** | no root there — it would silently fail | **detect host AutoSSL and stand down**, and say so in the UI |
| Ship **TLS-ALPN-01** | that's a reverse-proxy concern, not a PHP app's | HTTP-01 now, DNS-01 (wildcards) next |
| Make the reload a **broad sudo** grant | privilege sprawl on a security module | one **allow-listed** reload command, opt-in |

---

## 12. Build order (phasing)

1. **Scaffold + native HTTP-01 endpoint + cert store + admin shell** — **done.**
2. **Wire the vendored ACME client** (`vendor-lib/ACMECert`, v3.7.2) into `_issueViaAcme()` — **done (code);
   verified in isolation.** Remaining: the **live** end-to-end issue against Let's Encrypt **staging** on a
   real domain (on tiger-dev), then flip to production.
3. **Installer strategies** — `write-only` (no priv) first; then the `sudo-hook` + the TigerCloud AMI
   sudoers entry; host-AutoSSL **stand-down** detection.
4. **Renewal** — TigerSchedule job + `bin/tigerssl.php renew-due` + expiry-notice email.
5. **DNS-01 + wildcards** — Route53 adapter first (TigerCloud is on AWS), pluggable like Tiger_Location.
6. **Polish** — multi-SAN UX, chain viewer, per-org (multi-tenant) certs, store art.

---

*This document records decisions and their rationale. If you change a decision, update the relevant
section here in the same change — the "why" is the most valuable and most perishable part.*
