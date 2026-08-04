<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerssl_Service_Certificate — issue, renew, and manage TLS certificates over /api.
 *
 * The orchestration lives here; the ACME WIRE PROTOCOL is delegated to the vendored pure-PHP client
 * (vendor-lib/, FEATURES §7) — we never hand-roll JWS/nonce/order handling. The flow (issue):
 *
 *   1. Refuse if the host manages SSL itself (cPanel AutoSSL) — stand down, don't fake it.
 *   2. Upsert a `pending` row (DB — transaction).
 *   3. Run the ACME order (NETWORK — outside any DB transaction): register account, request the order,
 *      publish the HTTP-01 token via Tigerssl_Model_Challenge, let the CA verify our native endpoint,
 *      finalize with a CSR, download the cert + chain.
 *   4. INSTALL via the configured strategy (write files; reload only where we own root).
 *   5. Update the row to `active` with the real expiry (DB — transaction).
 *
 * Fail-soft: any failure flags the row `error` + `last_error` and returns a clean envelope; a running
 * cert is never disabled by a failed (re)issue.
 */
class Tigerssl_Service_Certificate extends Tiger_Service_Service
{
    /**
     * Issue a certificate for a domain (+ optional SANs). Params: domain, sans (comma/space list).
     *
     * @param  array $params the /api message
     * @return void
     */
    public function issue(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Tigerssl_Form_Domain();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $values = $form->getValues();

        $domain = strtolower(trim((string) $values['domain']));
        $sans   = $this->_parseSans($values['sans'] ?? '');

        if ($this->_hostStandsDown()) {
            $this->_error('tigerssl.error.host_managed');
            return;
        }

        $model = new Tigerssl_Model_Certificate();
        try {
            // Upsert a pending row so the UI reflects the in-flight order.
            $id = $this->_transaction(function () use ($model, $domain, $sans, $params) {
                $existing = $model->findByDomain($domain);
                $data = [
                    'domain'         => $domain,
                    'sans'           => $sans ? json_encode($sans) : null,
                    'challenge_type' => (string) $this->_cfg('challenge.type', 'http-01'),
                    'installer'      => $this->_installStrategy(),
                    'auto_renew'     => 1,
                    'status'         => Tigerssl_Model_Certificate::STATUS_PENDING,
                    'last_error'     => null,
                ];
                if ($existing) { $model->update($data, $model->getAdapter()->quoteInto('certificate_id = ?', $existing['certificate_id'])); return $existing['certificate_id']; }
                return $model->insert($data);
            });

            // The ACME dance is network I/O — never inside a DB transaction.
            $result = $this->_issueViaAcme($domain, $sans);   // ['cert','key','chain','expires_at','serial','fingerprint']

            // Hand the material to the installer strategy (write files; reload only where we own root).
            $paths = $this->_install($domain, $result);

            $this->_transaction(function () use ($model, $id, $result, $paths) {
                $model->update([
                    'status'      => Tigerssl_Model_Certificate::STATUS_ACTIVE,
                    'issued_at'   => date('Y-m-d H:i:s'),
                    'expires_at'  => $result['expires_at'] ?? null,
                    'serial'      => $result['serial'] ?? null,
                    'fingerprint' => $result['fingerprint'] ?? null,
                    'cert_path'   => $paths['cert'] ?? null,
                    'key_path'    => $paths['key'] ?? null,
                    'chain_path'  => $paths['chain'] ?? null,
                    'last_error'  => null,
                ], $model->getAdapter()->quoteInto('certificate_id = ?', $id));
            });

            $this->_success(['certificate_id' => $id, 'domain' => $domain], 'tigerssl.cert.issued');
        } catch (Throwable $e) {
            $this->_markError($model, $domain, $e);
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'tigerssl.error.issue_failed');
        }
    }

    /**
     * Renew a single certificate by domain (same flow as issue, reusing the stored SANs).
     *
     * @param  array $params domain
     * @return void
     */
    public function renew(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $domain = strtolower(trim((string) ($params['domain'] ?? '')));
        $model  = new Tigerssl_Model_Certificate();
        $row    = $domain !== '' ? $model->findByDomain($domain) : null;
        if (!$row) { $this->_error('tigerssl.error.not_found'); return; }

        try {
            $sans   = $row['sans'] ? (json_decode($row['sans'], true) ?: []) : [];
            $result = $this->_issueViaAcme($domain, $sans);
            $paths  = $this->_install($domain, $result);
            $this->_transaction(function () use ($model, $row, $result, $paths) {
                $model->update([
                    'status'      => Tigerssl_Model_Certificate::STATUS_ACTIVE,
                    'issued_at'   => date('Y-m-d H:i:s'),
                    'expires_at'  => $result['expires_at'] ?? null,
                    'serial'      => $result['serial'] ?? null,
                    'fingerprint' => $result['fingerprint'] ?? null,
                    'cert_path'   => $paths['cert'] ?? $row['cert_path'],
                    'key_path'    => $paths['key'] ?? $row['key_path'],
                    'chain_path'  => $paths['chain'] ?? $row['chain_path'],
                    'last_error'  => null,
                ], $model->getAdapter()->quoteInto('certificate_id = ?', $row['certificate_id']));
            });
            $this->_success(['domain' => $domain], 'tigerssl.cert.renewed');
        } catch (Throwable $e) {
            $this->_markError($model, $domain, $e);
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'tigerssl.error.renew_failed');
        }
    }

    /**
     * Renew every certificate inside the renewal window. The TigerSchedule handler + the `renew-due` CLI
     * both call this. Fail-soft PER CERT: one domain's failure never aborts the batch, and the still-valid
     * cert keeps serving until its real expiry.
     *
     * @param  array $params unused (invoked by the scheduler/CLI)
     * @return void
     */
    public function renewDue(array $params = []): void
    {
        $model = new Tigerssl_Model_Certificate();
        $days  = (int) $this->_cfg('renew.before_days', '30');
        $due   = $model->findDueForRenewal($days);
        $ok = 0; $failed = 0;
        foreach ($due as $row) {
            try {
                $sans   = $row['sans'] ? (json_decode($row['sans'], true) ?: []) : [];
                $result = $this->_issueViaAcme($row['domain'], $sans);
                $paths  = $this->_install($row['domain'], $result);
                $model->update([
                    'status'     => Tigerssl_Model_Certificate::STATUS_ACTIVE,
                    'issued_at'  => date('Y-m-d H:i:s'),
                    'expires_at' => $result['expires_at'] ?? null,
                    'cert_path'  => $paths['cert'] ?? $row['cert_path'],
                    'key_path'   => $paths['key'] ?? $row['key_path'],
                    'chain_path' => $paths['chain'] ?? $row['chain_path'],
                    'last_error' => null,
                ], $model->getAdapter()->quoteInto('certificate_id = ?', $row['certificate_id']));
                $ok++;
            } catch (Throwable $e) {
                $this->_markError($model, $row['domain'], $e);
                $failed++;
            }
        }
        $this->_success(['renewed' => $ok, 'failed' => $failed, 'due' => count($due)], 'tigerssl.cert.renew_due_done');
    }

    /**
     * Remove a managed certificate (soft-delete the row; leaves on-disk files for the operator to clean).
     *
     * @param  array $params certificate_id
     * @return void
     */
    public function remove(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $id    = (string) ($params['certificate_id'] ?? '');
        $model = new Tigerssl_Model_Certificate();
        if ($id === '') { $this->_error('tigerssl.error.not_found'); return; }
        try {
            $this->_transaction(function () use ($model, $id) { $model->softDelete($id); });
            $this->_success([], 'tigerssl.cert.removed');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * DataTables source for the admin certificates grid.
     *
     * @param  array $params the DataTables request
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $dt    = $this->_dtParams($params);
        $model = new Tigerssl_Model_Certificate();
        $rows  = $model->fetchAll($model->activeSelect()->order('created_at DESC'))->toArray();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'certificate_id' => $r['certificate_id'],
                'domain'         => $r['domain'],
                'status'         => $r['status'],
                'expires_at'     => $r['expires_at'],
                'auto_renew'     => (int) $r['auto_renew'],
                'installer'      => $r['installer'],
                'last_error'     => $r['last_error'],
                'can_edit'       => true,
                'can_delete'     => true,
            ];
        }
        $this->_dtResponse($dt['draw'], count($data), count($data), $data);
    }

    /**
     * Host/SSL environment status for the admin banner: whether we own the box or the host manages SSL.
     *
     * @param  array $params unused
     * @return void
     */
    public function status(array $params = []): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success($this->_statusForView());
    }

    /**
     * The host/SSL environment status as a plain array, for the admin banner (NOT /api-dispatchable —
     * the leading underscore keeps it out of the service dispatcher; the controller calls it directly).
     *
     * @return array
     */
    public function _statusForView(): array
    {
        return [
            'stands_down' => $this->_hostStandsDown(),
            'strategy'    => $this->_installStrategy(),
            'ca'          => (string) $this->_cfg('ca.environment', 'staging'),
            'acme_ready'  => $this->_acmeAvailable(),
            'openssl'     => extension_loaded('openssl'),
        ];
    }

    // ── internals ────────────────────────────────────────────────────────────────────────────────

    /**
     * Run the ACME order via the vendored client (vendor-lib/ACMECert) and return the issued material.
     *
     * This is where the two halves meet: the vendored client drives register → order → challenge →
     * finalize → download, and OUR http-01 handler publishes each token through Tigerssl_Model_Challenge
     * (served by the native WellKnownController) instead of writing a file to the docroot. The JOSE/crypto
     * layer is the vendored library — never reimplemented here. Network I/O only; no DB transaction.
     *
     * @param  string $domain the primary domain (CN)
     * @param  array  $sans   extra SAN domains
     * @return array          ['cert','key','chain','expires_at','serial','fingerprint']
     * @throws RuntimeException when the ACME engine or openssl is unavailable
     */
    protected function _issueViaAcme(string $domain, array $sans): array
    {
        if (!$this->_acmeAvailable()) {
            throw new RuntimeException('ACME engine not vendored (expected vendor-lib/ACMECert — see FEATURES §7).');
        }
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('PHP\'s openssl extension is required to issue certificates.');
        }

        require_once __DIR__ . '/../vendor-lib/ACMECert/ACMECert.php';

        // staging vs production is the ACMECert constructor's $live boolean.
        $live = $this->_cfg('ca.environment', 'staging') === 'production';
        $ac   = new \skoerfgen\ACMECert\ACMECert($live);

        // Account key: one per install, persisted + reused. Register is idempotent (agree TOS + contact).
        $ac->loadAccountKey($this->_accountKeyPem($ac));
        $email    = trim($this->_cfg('ca.contact_email', ''));
        $contacts = $email !== '' ? ['mailto:' . $email] : [];
        $ac->register(true, $contacts);

        // Domain key: per-domain, persisted + reused across renewals (passing a key → the client builds
        // the CSR with all our SANs internally).
        $domainKey = $this->_domainKeyPem($ac, $domain);

        // Every name goes on one cert; all use our native http-01 responder.
        $domainConfig = [];
        foreach (array_merge([$domain], $sans) as $name) {
            $domainConfig[$name] = ['challenge' => 'http-01'];
        }

        // The handler publishes the token→keyAuthorization for our WellKnownController to serve, and the
        // returned closure removes it once the CA has validated (ACMECert calls it in a finally).
        $handler = function ($opts) {
            Tigerssl_Model_Challenge::put((string) $opts['key'], (string) $opts['value']);
            return function ($opts) { Tigerssl_Model_Challenge::forget((string) $opts['key']); };
        };

        // Returns the fullchain PEM (leaf + intermediates) — what a web server wants.
        $fullchain = $ac->getCertificateChain($domainKey, $domainConfig, $handler);

        // Derive expiry / serial / fingerprint from the leaf.
        $parts = $ac->splitChain($fullchain);
        $leaf  = $parts[0] ?? $fullchain;
        $chain = count($parts) > 1 ? implode("\n", array_slice($parts, 1)) : '';
        $info  = @openssl_x509_parse($leaf) ?: [];

        return [
            'cert'        => $fullchain,
            'key'         => $domainKey,
            'chain'       => $chain,
            'expires_at'  => isset($info['validTo_time_t']) ? date('Y-m-d H:i:s', (int) $info['validTo_time_t']) : null,
            'serial'      => $info['serialNumberHex'] ?? (isset($info['serialNumber']) ? (string) $info['serialNumber'] : null),
            'fingerprint' => @openssl_x509_fingerprint($leaf, 'sha256') ?: null,
        ];
    }

    /** True once the vendored ACME v2 client is present. */
    protected function _acmeAvailable(): bool
    {
        return class_exists('skoerfgen\\ACMECert\\ACMECert')
            || is_file(__DIR__ . '/../vendor-lib/ACMECert/ACMECert.php');
    }

    /**
     * The ACME account key (PEM) — one per install, generated once and reused. A secret: 0600, outside
     * the docroot (FEATURES §10).
     *
     * @param  \skoerfgen\ACMECert\ACMECert $ac the client (used to mint the key on first run)
     * @return string                          the account key PEM
     */
    protected function _accountKeyPem(\skoerfgen\ACMECert\ACMECert $ac): string
    {
        $file = $this->_keysDir() . '/account.key';
        if (is_file($file)) { return (string) file_get_contents($file); }
        $pem = $ac->generateECKey('P-256');
        @file_put_contents($file, $pem); @chmod($file, 0600);
        return $pem;
    }

    /**
     * The per-domain private key (PEM) — generated once per domain and reused across renewals. A secret:
     * 0600, outside the docroot.
     *
     * @param  \skoerfgen\ACMECert\ACMECert $ac     the client
     * @param  string                       $domain the primary domain
     * @return string                               the domain key PEM
     */
    protected function _domainKeyPem(\skoerfgen\ACMECert\ACMECert $ac, string $domain): string
    {
        $file = $this->_keysDir() . '/' . $this->_safeName($domain) . '.key';
        if (is_file($file)) { return (string) file_get_contents($file); }
        $pem = $this->_cfg('key.type', 'ec') === 'rsa'
            ? $ac->generateRSAKey((int) $this->_cfg('key.rsa_bits', '2048'))
            : $ac->generateECKey('P-256');
        @file_put_contents($file, $pem); @chmod($file, 0600);
        return $pem;
    }

    /** The writable, docroot-external keys dir (account + domain keys). Created 0700 on first use. */
    protected function _keysDir(): string
    {
        $base = defined('APPLICATION_PATH') ? dirname(APPLICATION_PATH) : sys_get_temp_dir();
        $dir  = $base . '/storage/tigerssl/keys';
        if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
        return $dir;
    }

    /**
     * Install issued material per the configured strategy. Returns the paths written (empty on stand-down
     * or write failure the caller tolerates). NEVER edits a vhost/.htaccess (FEATURES §5).
     *
     * @param  string $domain the domain (used to namespace the on-disk path)
     * @param  array  $result the issued material (cert/key/chain)
     * @return array          ['cert'=>?, 'key'=>?, 'chain'=>?]
     */
    protected function _install(string $domain, array $result): array
    {
        $strategy = $this->_installStrategy();
        if ($strategy === 'stand-down') { return []; }

        $base = rtrim((string) $this->_cfg('install.path', '/etc/tiger/ssl'), '/') . '/' . $this->_safeName($domain);
        $paths = ['cert' => "$base/cert.pem", 'key' => "$base/privkey.pem", 'chain' => "$base/chain.pem"];

        if (!is_dir($base)) { @mkdir($base, 0700, true); }
        @file_put_contents($paths['cert'],  (string) ($result['cert']  ?? '')); @chmod($paths['cert'], 0644);
        @file_put_contents($paths['key'],   (string) ($result['key']   ?? '')); @chmod($paths['key'], 0600);
        @file_put_contents($paths['chain'], (string) ($result['chain'] ?? '')); @chmod($paths['chain'], 0644);

        // sudo-hook: reload the web server via the single allow-listed command (nothing else). write-only
        // stops here and leaves the reload to the operator.
        if ($strategy === 'sudo-hook') {
            $cmd = trim((string) $this->_cfg('install.reload_command', ''));
            if ($cmd !== '') { $this->_reload($cmd); }
        }
        return $paths;
    }

    /**
     * Run the ONE allow-listed reload command (sudo-hook strategy). Kept in a single method so the
     * privileged surface is auditable in one place. Best-effort + logged — a failed reload doesn't
     * discard a freshly issued cert.
     *
     * @param  string $cmd the configured reload command (e.g. "systemctl reload httpd")
     * @return void
     */
    protected function _reload(string $cmd): void
    {
        try {
            // The operator's sudoers entry is expected to allow exactly this command with NOPASSWD.
            @exec('sudo ' . $cmd . ' 2>&1', $out, $code);
            if ($code !== 0 && class_exists('Tiger_Log')) {
                Tiger_Log::warn('tigerssl.reload_failed', ['cmd' => $cmd, 'code' => $code]);
            }
        } catch (Throwable $e) {
            if (class_exists('Tiger_Log')) { Tiger_Log::warn('tigerssl.reload_exception', ['msg' => $e->getMessage()]); }
        }
    }

    /**
     * Whether TigerSSL should stand down because the host manages SSL itself (cPanel AutoSSL, etc.). An
     * explicit `install.strategy = stand-down` forces it; otherwise a probe path (cPanel) auto-detects.
     *
     * @return bool
     */
    protected function _hostStandsDown(): bool
    {
        if ($this->_installStrategy() === 'stand-down') { return true; }
        $probe = (string) $this->_cfg('host.autossl_probe', '');
        return $probe !== '' && file_exists($probe);
    }

    /** The configured installer strategy (sudo-hook | write-only | stand-down). */
    protected function _installStrategy(): string
    {
        return (string) $this->_cfg('install.strategy', 'write-only');
    }

    /** Flag a row `error` + record the message; fail-soft (never throws). */
    protected function _markError(Tigerssl_Model_Certificate $model, string $domain, Throwable $e): void
    {
        try {
            $row = $model->findByDomain($domain);
            if ($row) {
                $model->update(
                    ['status' => Tigerssl_Model_Certificate::STATUS_ERROR, 'last_error' => $e->getMessage()],
                    $model->getAdapter()->quoteInto('certificate_id = ?', $row['certificate_id'])
                );
            }
            if (class_exists('Tiger_Log')) { Tiger_Log::warn('tigerssl.cert_error', ['domain' => $domain, 'msg' => $e->getMessage()]); }
        } catch (Throwable $ignore) { /* fail-soft */ }
    }

    /** Read a tiger.tigerssl.<path> config value with a fallback. */
    protected function _cfg(string $path, string $default = ''): string
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return $default; }
        $node = Zend_Registry::get('Zend_Config')->get('tiger')?->get('tigerssl');
        foreach (explode('.', $path) as $seg) { $node = is_object($node) ? $node->get($seg) : null; }
        return $node === null ? $default : (string) $node;
    }

    /** Parse a comma/space-separated SAN list into a clean array of lowercased domains. */
    protected function _parseSans($raw): array
    {
        $parts = preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_map(fn($d) => strtolower(trim($d)), $parts)));
    }

    /** Filesystem-safe directory name for a domain. */
    protected function _safeName(string $domain): string
    {
        return preg_replace('/[^a-z0-9.\-]/i', '_', $domain);
    }
}
