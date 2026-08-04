# TigerSSL

**Implement Let's Encrypt / EasySSL on your Tiger server.** Free, automatic HTTPS — ACME certificate
issuance and renewal, driven from the Tiger admin, no shell needed for the day-to-day.

> **Free, first-party, BSD-3-Clause.** One button instead of a certbot tutorial. The full design +
> rationale is in [FEATURES.md](FEATURES.md); notable changes are in [CHANGELOG.md](CHANGELOG.md); how to
> work in the repo is in [AGENTS.md](AGENTS.md).

## What it does

- **Issues real certificates from Let's Encrypt** (or any ACME v2 CA) for your domains.
- **Serves the HTTP-01 challenge natively** — a built-in `/.well-known/acme-challenge/…` route, so there
  is **no `.htaccess` to edit and no vhost to touch**. It works the moment you activate the module.
- **Pure PHP** — a vendored ACME v2 client. **No certbot, no Python, no Composer** for the end user.
- **Auto-renews** before expiry (Let's Encrypt certs last 90 days) — via TigerSchedule, or a one-line
  system cron.
- **Knows its limits.** Installing a cert + reloading the web server needs root, so TigerSSL is built for
  **servers you own** (VPS / EC2 / **TigerCloud**) and ships a narrow, single-command reload hook. On
  hosts that already manage SSL for you (**cPanel AutoSSL**) it detects that and **stands down** — no
  pretending, no silent failure.

## Where it runs

| Host | TigerSSL |
|---|---|
| **TigerCloud** | issue + auto-install + auto-reload (the AMI grants the reload hook) — zero config |
| **Your own VPS / EC2** | issue + reload via a one-line `sudoers` entry, or write-only + reload yourself |
| **cPanel / Plesk shared hosting** | stands down — the host's AutoSSL already does this |

## Status

**Early beta.** Scaffolded: the module structure, the native HTTP-01 challenge endpoint, the certificate
store, the admin screen, and the renewal job. Being wired: real issuance against Let's Encrypt (staging
first) through the vendored ACME client, and the privileged reload hook. See [FEATURES.md §12](FEATURES.md)
for the phasing.

## Install

It's a Tiger module — install through the Module Manager (or drop it into
`application/modules/tigerssl/`), run `vendor/bin/tiger migrate`, and it self-registers. The **SSL**
screen appears under admin Settings.

## License

[BSD-3-Clause](LICENSE). "Tiger" and "WebTigers" are trademarks — see [TRADEMARKS.md](TRADEMARKS.md). Let's
Encrypt is a trademark of the Internet Security Research Group; TigerSSL is an independent ACME client and
is not affiliated with or endorsed by ISRG.
