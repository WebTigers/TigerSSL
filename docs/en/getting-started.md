<!-- tiger:doc title="TigerSSL — Getting started" slug="tigerssl/getting-started" visibility="admin" icon="fa-lock" -->

# TigerSSL — free, automatic HTTPS

TigerSSL issues and renews real TLS certificates from **Let's Encrypt** (or any ACME v2 authority) for
your Tiger server — from the admin, no shell needed for day-to-day.

## Will it work on my host?

TigerSSL needs to **install** the certificate and **reload** your web server — and that needs root. So:

| Your host | What TigerSSL does |
|---|---|
| **TigerCloud** | Everything, automatically. The server image already grants the reload hook. |
| **Your own VPS / EC2** | Issues certificates, and either reloads via a one-line sudo rule, or writes the files for you to point your web server at. |
| **cPanel / Plesk shared hosting** | **Stands down** — your host's AutoSSL already issues and renews certificates for you. TigerSSL detects this and tells you. |

If the SSL screen shows *"This host manages SSL for you,"* you're already covered — there's nothing to do.

## Issue your first certificate

1. Open **Admin → Settings → SSL**.
2. On the **Settings** page, leave the CA on **Staging** for your first run (it has generous limits),
   set your **contact email**, and pick an **install strategy**:
   - **Write-only** — TigerSSL writes the certificate files; you point your web server at them.
   - **Sudo-hook** — TigerSSL also reloads your web server (needs the one-line sudo rule below).
3. Back on the **Certificates** page, click **Add a domain**, enter the domain (it must already point at
   this server), and **Issue certificate**.
4. TigerSSL answers the domain-control check automatically at `/.well-known/acme-challenge/…` — you don't
   configure anything for that.
5. Once it succeeds on staging, switch the CA to **Production** in Settings and re-issue.

## The sudo rule (sudo-hook strategy only)

So TigerSSL can reload your web server without full root, add exactly one command to your sudoers (adjust
the reload command to your server):

```
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl reload httpd
```

Then set **Reload command** in Settings to `systemctl reload httpd`. TigerSSL runs *only* that command —
nothing else.

## Renewal

Let's Encrypt certificates last **90 days**. TigerSSL renews them automatically about 30 days before
expiry. If you have **TigerSchedule** installed, that's fully automatic. If not, add this to a daily cron:

```
php /path/to/app/application/modules/tigerssl/bin/tigerssl.php renew-due
```

A renewal that fails is retried, is logged, and **never takes your current certificate offline** — the
existing one keeps serving until it truly expires.
