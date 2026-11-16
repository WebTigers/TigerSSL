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
 *   2. Refuse any name the caller's org does not own (Tigerssl_Model_HostOwner).
 *   3. Upsert a `pending` row + its covered names (DB — transaction).
 *   4. Run the ACME order (NETWORK — outside any DB transaction): register account, request the order,
 *      publish the HTTP-01 token via Tigerssl_Model_Challenge, let the CA verify our native endpoint,
 *      finalize with a CSR, download the cert + chain.
 *   5. INSTALL via the configured strategy (write files; reload only where we own root).
 *   6. Update the row to `active` with the real expiry (DB — transaction).
 *
 * ORG LANE (TIGER-511). A certificate belongs to an org and may cover several hostnames (SAN). Every
 * admin method acts for the CALLER'S org only — list, issue, renew, remove, renew-due — and every write
 * goes through an org-scoped model method, so another org's certificate id is simply "not found". The
 * one install-wide path is the scheduled renewal job (_renewAllDue), which renews each certificate as its
 * own org, re-checking that the org still owns every name on it — one certificate, one org, never mixed.
 *
 * Fail-soft: any failure flags the row `error` + `last_error` and returns a clean envelope; a running
 * cert is never disabled by a failed (re)issue.
 */
class Tigerssl_Service_Certificate extends Tiger_Service_Service
{
    /** Let's Encrypt's per-certificate name limit. */
    const MAX_NAMES = 100;

    /**
     * Issue a certificate for the caller's org. Params: domain (the primary name), sans (comma/space list
     * of extra names on the same certificate). Every name must be one the org owns.
     *
     * @param  array $params the /api message
     * @return void
     */
    public function issue(array $params): void
    {
        if (!$this->_isAllowed('issue')) { $this->_error('core.api.error.not_allowed'); return; }
        $org = $this->_orgId();
        if ($org === '') { $this->_error('tigerssl.error.no_org'); return; }

        $form = new Tigerssl_Form_Domain();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $values = $form->getValues();

        $domain = strtolower(trim((string) $values['domain']));
        $sans   = $this->_parseSans($values['sans'] ?? '');
        $valid  = new Zend_Validate_Hostname(Zend_Validate_Hostname::ALLOW_DNS);
        foreach ($sans as $san) {
            if (!$valid->isValid($san)) {
                $form->getElement('sans')->addError('tigerssl.error.san_invalid');
                $this->_formErrors($form);
                return;
            }
        }
        $names = array_values(array_unique(array_merge([$domain], $sans)));
        if (count($names) > self::MAX_NAMES) { $this->_error('tigerssl.error.too_many_names'); return; }

        // An org may only certify names it owns: its own site_domain hosts (+ their www), or — for the
        // install org — a host no tenant is mapped to. Another org's host, or an unmapped one asked for
        // by a tenant, is refused before anything is written or any CA is called.
        $foreign = Tigerssl_Model_HostOwner::firstForeign($org, $names);
        if ($foreign !== null) { $this->_error('tigerssl.error.name_not_owned', ['name' => $foreign]); return; }

        if ($this->_hostStandsDown()) {
            $this->_error('tigerssl.error.host_managed');
            return;
        }

        $model    = new Tigerssl_Model_Certificate();
        $nameRows = new Tigerssl_Model_CertificateName();
        $existing = $model->findByDomainForOrg($org, $domain);
        foreach ($names as $name) {
            $holder = $nameRows->certificateFor($name);
            if ($holder !== null && (!$existing || $holder !== $existing['certificate_id'])) {
                $this->_error('tigerssl.error.name_taken', ['name' => $name]);
                return;
            }
        }

        $id = null;
        try {
            // Upsert a pending row (+ its names) so the UI reflects the in-flight order.
            $id = $this->_transaction(function () use ($model, $nameRows, $existing, $org, $domain, $names) {
                $data = [
                    'domain'         => $domain,
                    // Kept in step for a 1.0.x rollback; the names table is the authority from 1.1.0.
                    'sans'           => count($names) > 1 ? json_encode(array_slice($names, 1)) : null,
                    'challenge_type' => (string) $this->_cfg('challenge.type', 'http-01'),
                    'installer'      => $this->_installStrategy(),
                    'auto_renew'     => 1,
                    'status'         => Tigerssl_Model_Certificate::STATUS_PENDING,
                    'last_error'     => null,
                ];
                if ($existing) {
                    $certId = (string) $existing['certificate_id'];
                    $model->updateForOrg($org, $certId, $data);
                } else {
                    $certId = (string) $model->insert($data + ['org_id' => $org]);
                }
                $nameRows->replaceNames($certId, $org, $names);
                return $certId;
            });

            // The ACME dance is network I/O — never inside a DB transaction.
            $result = $this->_issueViaAcme($org, $names);   // ['cert','key','chain','expires_at','serial','fingerprint']
            $paths  = $this->_install($domain, $result);

            $this->_transaction(function () use ($model, $org, $id, $result, $paths) {
                $model->updateForOrg($org, $id, $this->_issuedColumns($result, $paths, []));
            });

            $this->_success(['certificate_id' => $id, 'domain' => $domain, 'names' => $names], 'tigerssl.cert.issued');
        } catch (Throwable $e) {
            if ($id !== null) { $this->_markError($model, $org, $id, $e); }
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'tigerssl.error.issue_failed');
        }
    }

    /**
     * Renew one of the caller's org's certificates (same ACME flow as issue, for the names it covers).
     * Params: certificate_id (or, for the CLI, the primary domain).
     *
     * @param  array $params the /api message
     * @return void
     */
    public function renew(array $params): void
    {
        if (!$this->_isAllowed('renew')) { $this->_error('core.api.error.not_allowed'); return; }
        $org = $this->_orgId();
        if ($org === '') { $this->_error('tigerssl.error.no_org'); return; }

        $form = new Tigerssl_Form_Certificate();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $values = $form->getValues();

        $model = new Tigerssl_Model_Certificate();
        $id    = (string) ($values['certificate_id'] ?? '');
        $row   = $id !== ''
            ? $model->findForOrg($org, $id)
            : $model->findByDomainForOrg($org, (string) ($values['domain'] ?? ''));
        if (!$row) { $this->_error('tigerssl.error.not_found'); return; }

        $failure = $this->_renewOne($model, $row);
        if ($failure === null) {
            $this->_success(['certificate_id' => $row['certificate_id'], 'domain' => $row['domain']], 'tigerssl.cert.renewed');
            return;
        }
        $this->_error($failure);
    }

    /**
     * Renew every certificate of the CALLER'S org that is inside the renewal window. (The scheduled job
     * renews every org's — see _renewAllDue.) Fail-soft per certificate.
     *
     * @param  array $params unused
     * @return void
     */
    public function renewDue(array $params = []): void
    {
        if (!$this->_isAllowed('renewDue')) { $this->_error('core.api.error.not_allowed'); return; }
        $org = $this->_orgId();
        if ($org === '') { $this->_error('tigerssl.error.no_org'); return; }

        $model = new Tigerssl_Model_Certificate();
        $due   = $model->findDueForRenewal((int) $this->_cfg('renew.before_days', '30'), $org);
        $this->_success($this->_renewBatch($model, $due), 'tigerssl.cert.renew_due_done');
    }

    /**
     * The scheduled renewal job (TigerSchedule + the `renew-due` CLI): renew every org's certificates that
     * are inside the window. Each certificate is renewed AS ITS OWN ORG, for exactly the names it carries
     * — orgs are never mixed into one order — and only while its org still owns every one of those names
     * and has auto-renew on. NOT /api-dispatchable (the leading underscore keeps it out of the dispatcher):
     * an admin's renewDue stays in their own org's lane.
     *
     * @return array{renewed:int,failed:int,skipped:int,due:int}
     */
    public function _renewAllDue(): array
    {
        $model = new Tigerssl_Model_Certificate();
        $due   = $model->findDueForRenewal((int) $this->_cfg('renew.before_days', '30'), null);
        return $this->_renewBatch($model, $due);
    }

    /**
     * Remove one of the caller's org's certificates from management (soft-delete the row + its names;
     * leaves on-disk files for the operator to clean). Params: certificate_id.
     *
     * @param  array $params the /api message
     * @return void
     */
    public function remove(array $params): void
    {
        if (!$this->_isAllowed('remove')) { $this->_error('core.api.error.not_allowed'); return; }
        $org = $this->_orgId();
        if ($org === '') { $this->_error('tigerssl.error.no_org'); return; }

        $form = new Tigerssl_Form_Certificate();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $id = (string) ($form->getValues()['certificate_id'] ?? '');

        $model = new Tigerssl_Model_Certificate();
        $row   = $model->findForOrg($org, $id);
        if (!$row) { $this->_error('tigerssl.error.not_found'); return; }

        try {
            $this->_transaction(function () use ($model, $org, $id) {
                $model->softDeleteForOrg($org, $id);
                (new Tigerssl_Model_CertificateName())->removeFor($id);
            });
            $this->_success(['certificate_id' => $id], 'tigerssl.cert.removed');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * DataTables source for the admin certificates grid — the caller's org's certificates only.
     *
     * @param  array $params the DataTables request
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAllowed('datatable')) { $this->_error('core.api.error.not_allowed'); return; }
        $dt    = $this->_dtParams($params);
        $org   = $this->_orgId();
        $rows  = (new Tigerssl_Model_Certificate())->forOrg($org);
        $names = new Tigerssl_Model_CertificateName();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'certificate_id' => $r['certificate_id'],
                'domain'         => $r['domain'],
                'names'          => $names->namesFor((string) $r['certificate_id']),
                'status'         => $r['status'],
                'expires_at'     => $r['expires_at'],
                'auto_renew'     => (int) $r['auto_renew'],
                'installer'      => $r['installer'],
                'last_error'     => $r['last_error'],
                'can_edit'       => $this->_isAllowed('renew'),
                'can_delete'     => $this->_isAllowed('remove'),
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
        if (!$this->_isAllowed('status')) { $this->_error('core.api.error.not_allowed'); return; }
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
     * Renew a batch of due certificates, one at a time, each as its own org. Fail-soft per certificate;
     * a certificate whose org turned auto-renew off is skipped, not failed.
     *
     * @param  Tigerssl_Model_Certificate $model the store
     * @param  array                      $due   the rows
     * @return array{renewed:int,failed:int,skipped:int,due:int}
     */
    protected function _renewBatch(Tigerssl_Model_Certificate $model, array $due): array
    {
        $ok = 0; $failed = 0; $skipped = 0;
        foreach ($due as $row) {
            if (!$this->_orgAutoRenew((string) $row['org_id'])) { $skipped++; continue; }
            $this->_renewOne($model, $row) === null ? $ok++ : $failed++;
        }
        return ['renewed' => $ok, 'failed' => $failed, 'skipped' => $skipped, 'due' => count($due)];
    }

    /**
     * Renew ONE certificate as its own org, for exactly the names it covers. Re-checks ownership first: a
     * name that has since moved to another org (or off the install org) fails the renewal rather than
     * keeping a certificate alive for a host its org no longer owns.
     *
     * @param  Tigerssl_Model_Certificate $model the store
     * @param  array                      $row   the certificate row
     * @return string|null                       null on success, else the error to report
     */
    protected function _renewOne(Tigerssl_Model_Certificate $model, array $row): ?string
    {
        $org   = (string) $row['org_id'];
        $id    = (string) $row['certificate_id'];
        $names = (new Tigerssl_Model_CertificateName())->namesFor($id);
        if (!$names) { $names = [(string) $row['domain']]; }

        $foreign = Tigerssl_Model_HostOwner::firstForeign($org, $names);
        if ($foreign !== null) {
            $this->_markError($model, $org, $id, new RuntimeException('Name no longer owned by this org: ' . $foreign));
            return 'tigerssl.error.name_not_owned';
        }

        try {
            $result = $this->_issueViaAcme($org, $names);
            $paths  = $this->_install((string) $row['domain'], $result);
            $this->_transaction(function () use ($model, $org, $id, $row, $result, $paths) {
                $model->updateForOrg($org, $id, $this->_issuedColumns($result, $paths, $row));
            });
            return null;
        } catch (Throwable $e) {
            $this->_markError($model, $org, $id, $e);
            return APPLICATION_ENV !== 'production' ? $e->getMessage() : 'tigerssl.error.renew_failed';
        }
    }

    /** The columns written once a certificate is (re)issued; a missing path keeps the previous one. */
    protected function _issuedColumns(array $result, array $paths, array $row): array
    {
        return [
            'status'      => Tigerssl_Model_Certificate::STATUS_ACTIVE,
            'issued_at'   => date('Y-m-d H:i:s'),
            'expires_at'  => $result['expires_at'] ?? null,
            'serial'      => $result['serial'] ?? null,
            'fingerprint' => $result['fingerprint'] ?? null,
            'cert_path'   => $paths['cert'] ?? ($row['cert_path'] ?? null),
            'key_path'    => $paths['key'] ?? ($row['key_path'] ?? null),
            'chain_path'  => $paths['chain'] ?? ($row['chain_path'] ?? null),
            'last_error'  => null,
        ];
    }

    /** Whether an org has auto-renew on (its own setting, else the install's, else on). */
    protected function _orgAutoRenew(string $orgId): bool
    {
        $v = Tiger_Model_Config::orgValue($orgId, 'tiger.tigerssl.renew.auto', '1');
        return !in_array(strtolower(trim((string) $v)), ['0', 'off', 'false', 'no', ''], true);
    }

    /**
     * Run the ACME order via the vendored client (vendor-lib/ACMECert) and return the issued material.
     *
     * This is where the two halves meet: the vendored client drives register → order → challenge →
     * finalize → download, and OUR http-01 handler publishes each token through Tigerssl_Model_Challenge
     * (served by the native WellKnownController) instead of writing a file to the docroot. The JOSE/crypto
     * layer is the vendored library — never reimplemented here. Network I/O only; no DB transaction.
     *
     * One call = one order = one org's names. Callers pass exactly one certificate's names.
     *
     * @param  string            $orgId the org the certificate belongs to (namespaces its private key)
     * @param  array<int,string> $names every name on the certificate, primary (CN) first
     * @return array                    ['cert','key','chain','expires_at','serial','fingerprint']
     * @throws RuntimeException when the ACME engine or openssl is unavailable
     */
    protected function _issueViaAcme(string $orgId, array $names): array
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

        // Domain key: per org + primary domain, persisted + reused across renewals (passing a key → the
        // client builds the CSR with all our SANs internally).
        $domainKey = $this->_domainKeyPem($ac, $orgId, (string) $names[0]);

        // Every name goes on one cert; all use our native http-01 responder.
        $domainConfig = [];
        foreach ($names as $name) {
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
     * The private key (PEM) for one org's certificate — generated once and reused across renewals. Kept
     * under keys/<org>/ so a host that later moves to another org never inherits the first org's key. A
     * pre-1.1 key (keys/<domain>.key) is adopted by the install org, which owned every legacy row.
     *
     * @param  \skoerfgen\ACMECert\ACMECert $ac     the client
     * @param  string                       $orgId  the certificate's org
     * @param  string                       $domain the primary domain
     * @return string                               the domain key PEM
     */
    protected function _domainKeyPem(\skoerfgen\ACMECert\ACMECert $ac, string $orgId, string $domain): string
    {
        $dir = $this->_keysDir() . '/' . $this->_safeName($orgId);
        if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
        $file = $dir . '/' . $this->_safeName($domain) . '.key';
        if (is_file($file)) { return (string) file_get_contents($file); }

        $legacy = $this->_keysDir() . '/' . $this->_safeName($domain) . '.key';
        $pem = (is_file($legacy) && $orgId === Tigerssl_Model_HostOwner::installOrgId())
            ? (string) file_get_contents($legacy)
            : ($this->_cfg('key.type', 'ec') === 'rsa'
                ? $ac->generateRSAKey((int) $this->_cfg('key.rsa_bits', '2048'))
                : $ac->generateECKey('P-256'));
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
     * @param  string $domain the primary domain (used to namespace the on-disk path)
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

    /** Flag one org's certificate `error` + record the message; fail-soft (never throws). */
    protected function _markError(Tigerssl_Model_Certificate $model, string $orgId, string $certificateId, Throwable $e): void
    {
        try {
            $model->updateForOrg($orgId, $certificateId, ['status' => Tigerssl_Model_Certificate::STATUS_ERROR, 'last_error' => $e->getMessage()]);
            if (class_exists('Tiger_Log')) { Tiger_Log::warn('tigerssl.cert_error', ['certificate_id' => $certificateId, 'msg' => $e->getMessage()]); }
        } catch (Throwable $ignore) { /* fail-soft */ }
    }

    /**
     * Read an INSTALL-level tiger.tigerssl.<path> value: the install-wide (global) config row, else the
     * ini, else $default. Deliberately never an org row — these settings drive a root-run reload command,
     * the install's ACME account and where certificates are written, so the merged Zend_Config (which
     * folds the signed-in caller's org tier) is not where they are read from.
     */
    protected function _cfg(string $path, string $default = ''): string
    {
        $v = Tiger_Model_Config::orgValue('', 'tiger.tigerssl.' . $path, $default);
        return ($v === null || is_array($v) || is_object($v)) ? $default : (string) $v;
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
