# vendor-lib/ — vendored third-party code (do not edit)

Bundled dependencies, copied in verbatim (Tiger installs have no Composer for the end user — see the
install-distribution model). **Do not edit these files** — they are replaced wholesale on update, and
edits would be lost + break the pin.

## ACMECert — the ACME v2 client

- **Upstream:** [skoerfgen/ACMECert](https://github.com/skoerfgen/ACMECert)
- **License:** MIT (see `ACMECert/LICENSE.md`) — © Stefan Körfgen
- **Pinned:** **v3.7.2**, commit `c2c87e9a81e0d0cab7d7d6c16986b7db2acfa2ae` (fetched 2026-08-04)
- **Layout (upstream, preserved):** `ACMECert/ACMECert.php` (the loader) → `ACMECert/src/{ACME_Exception,
  ACMEv2,ACMECert}.php`. Namespace `skoerfgen\ACMECert`. Pure PHP; needs only ext-`openssl` + ext-`curl`.

**Used by** `Tigerssl_Service_Certificate::_issueViaAcme()` — the ONLY thing that talks to it. We drive
the high-level `getCertificateChain($domainKeyPem, $domainConfig, $handler)`; our `http-01` handler
publishes each token via `Tigerssl_Model_Challenge` (served by the native `/.well-known/acme-challenge`
route) instead of writing a file to the docroot.

**To update:** re-fetch the three `src/*.php` + the root `ACMECert.php` + `LICENSE.md` from the pinned (or
newer) tag, re-run `php -l`, and bump the version above. Nothing else in the module should need to change
unless the upstream public API moves.

**Known upstream note:** on PHP 8.4+ the client emits a `Deprecated: $http_response_header` notice
(`src/ACMEv2.php`). It's a deprecation, not an error — harmless under Tiger's error handling and on our
PHP 8.1–8.3 floor; it clears when upstream migrates to `http_get_last_response_headers()`.
