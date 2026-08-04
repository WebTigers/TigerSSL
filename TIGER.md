# TigerSSL

*Free, automatic HTTPS for your Tiger server. Issues and renews real Let's Encrypt certificates from the
admin — no certbot, no Python, no shell for the day-to-day. Turn on the padlock and forget about it.*

> **`TIGER.md` is the vendor description** — the pitch the Module Installer shows before you install. The
> machine-readable manifest is [`module.json`](module.json); the full design is [`FEATURES.md`](FEATURES.md).

## What it does

- **Issues real certificates** from **Let's Encrypt** (or any ACME v2 authority) for your domains.
- **Answers the domain check itself** — a built-in `/.well-known/acme-challenge/…` route, so there's **no
  `.htaccess` to edit and no vhost to touch**. It works the moment you activate the module.
- **Pure PHP** — a vendored ACME v2 client. **No certbot, no Python, no Composer** on your server.
- **Auto-renews** before expiry (Let's Encrypt certificates last 90 days) — via TigerSchedule, or a
  one-line system cron. A failed renewal never takes your current certificate offline.
- **Knows its limits.** Installing a certificate + reloading the web server needs root, so TigerSSL is
  built for **servers you own** (VPS / EC2 / **TigerCloud**). On hosts that already manage SSL for you
  (**cPanel AutoSSL**) it detects that and **stands down** — no pretending, no silent failure.

## Where it runs

| Host | TigerSSL |
|---|---|
| **TigerCloud** | Issue + auto-install + auto-reload — zero config. |
| **Your own VPS / EC2** | Issue + reload via a one-line `sudo` rule, or write the files for you to wire up. |
| **cPanel / Plesk shared hosting** | Stands down — the host's AutoSSL already issues + renews. |

## How it works

Certificates use the **ACME** protocol (the same standard certbot speaks). TigerSSL registers an account,
proves you control each domain over **HTTP-01** — served natively by the module — then fetches the signed
certificate and installs it per your chosen strategy. It ships pointed at **Let's Encrypt staging** so
your first run can't hit production rate limits; flip to production once a staging issue succeeds.

## Requirements

- Tiger ≥ 0.8.0-beta, PHP ≥ 8.1 with the `openssl` + `curl` extensions (see [`module.json`](module.json)).
- A server where you control the web server (for install + reload), or a host whose SSL you let TigerSSL
  leave alone.

## License

**Free** and **BSD 3-Clause** — a first-party module, yours to use, modify, and redistribute. The
Tiger / TigerSSL / WebTigers trademarks are reserved; Let's Encrypt is a trademark of ISRG (TigerSSL is an
independent ACME client, not affiliated with ISRG). See [LICENSE](LICENSE), [NOTICE.md](NOTICE.md), and
[TRADEMARKS.md](TRADEMARKS.md).
