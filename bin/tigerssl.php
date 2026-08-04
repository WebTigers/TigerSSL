<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSSL module CLI — for a system cron where TigerSchedule isn't used.
 *
 *   php application/modules/tigerssl/bin/tigerssl.php renew-due   # renew certs inside the window (cron this daily)
 *   php application/modules/tigerssl/bin/tigerssl.php status      # print host/SSL environment status
 *   php application/modules/tigerssl/bin/tigerssl.php renew <domain>
 *   php application/modules/tigerssl/bin/tigerssl.php issue <domain> [san,san]
 *
 * It boots Tiger the same way the web entry does (one bootstrap path — FEATURES/ARCHITECTURE §4), then
 * drives Tigerssl_Service_Certificate. Cron the `renew-due` line daily; it's idempotent and fail-soft.
 */

// Walk up to the app root (…/application/modules/tigerssl/bin → app root) and boot.
$root = dirname(__DIR__, 4);
if (!is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "TigerSSL CLI: could not locate the Tiger app root from " . __DIR__ . "\n");
    exit(2);
}
require $root . '/vendor/autoload.php';
(new Tiger_Application($root))->boot();

$argvv = $_SERVER['argv'] ?? [];
$cmd   = $argvv[1] ?? 'help';
$svc   = new Tigerssl_Service_Certificate();

switch ($cmd) {
    case 'renew-due':
        $svc->renewDue([]);
        echo _envelope($svc);
        break;

    case 'renew':
        $domain = $argvv[2] ?? '';
        if ($domain === '') { fwrite(STDERR, "usage: tigerssl.php renew <domain>\n"); exit(1); }
        $svc->renew(['domain' => $domain]);
        echo _envelope($svc);
        break;

    case 'issue':
        $domain = $argvv[2] ?? '';
        if ($domain === '') { fwrite(STDERR, "usage: tigerssl.php issue <domain> [san,san]\n"); exit(1); }
        $svc->issue(['domain' => $domain, 'sans' => $argvv[3] ?? '']);
        echo _envelope($svc);
        break;

    case 'status':
        $st = $svc->_statusForView();
        echo json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    default:
        echo "TigerSSL CLI — commands: renew-due | renew <domain> | issue <domain> [sans] | status\n";
}

/** Print the service's response envelope as JSON (result + messages), for cron logs. */
function _envelope(Tiger_Service_Service $svc): string
{
    $res = method_exists($svc, 'getResponse') ? $svc->getResponse() : null;
    $out = $res && method_exists($res, 'toArray') ? $res->toArray() : ['result' => null];
    return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
