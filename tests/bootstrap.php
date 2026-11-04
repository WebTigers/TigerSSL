<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * PHPUnit bootstrap for TigerSSL.
 *
 * The module's `Tigerssl_*` classes extend `Tiger_*` bases. We load a tiger-core checkout's autoloader
 * (Tiger_*, Zend_* via tigerzf, PHPUnit) and register a tiny `Tigerssl_*` loader mapping the ZF1 module
 * layout (services/ models/ forms/ controllers/) to class names — mirroring the web resource types the
 * Bootstrap adds. This module has no runtime vendor of its own (vendor-lib/ACMECert is require_once'd by
 * the service, not autoloaded through this prefix loader).
 *
 * Resolve tiger-core via: $TIGER_CORE_VENDOR → $TIGER_CORE_PATH/vendor → a sibling ../tiger-core.
 */

error_reporting(E_ALL);

if (!defined('APPLICATION_ENV')) {
    define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'testing');
}

$moduleRoot = dirname(__DIR__);
if (!defined('APPLICATION_ROOT')) {
    define('APPLICATION_ROOT', $moduleRoot);
}

// A per-run sandbox app root, so the file-backed Tigerssl_Model_Challenge (which derives its cache path
// from dirname(APPLICATION_PATH)) writes under a throwaway temp dir instead of the repo. Tests that touch
// the challenge store clean their own file; this just keeps it off real disk paths.
if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', sys_get_temp_dir() . '/tigerssl-test-' . getmypid() . '/application');
}

$candidates = array_filter([
    getenv('TIGER_CORE_VENDOR') ?: null,
    getenv('TIGER_CORE_PATH') ? rtrim(getenv('TIGER_CORE_PATH'), '/') . '/vendor/autoload.php' : null,
    $moduleRoot . '/../tiger-core/vendor/autoload.php',
]);
$coreVendor = '';
foreach ($candidates as $c) {
    if (is_file($c)) { $coreVendor = $c; break; }
}
if ($coreVendor === '') {
    fwrite(STDERR, "\nTigerSSL tests need tiger-core's autoloader (Tiger_*/Zend_*/PHPUnit).\n"
        . "Set TIGER_CORE_VENDOR to a tiger-core checkout's vendor/autoload.php, or place tiger-core as a\n"
        . "sibling of TigerSSL and run `composer install` in it.\n\n");
    exit(1);
}
require $coreVendor;

$coreRoot = dirname($coreVendor, 2);
if (!defined('TIGER_CORE_PATH')) {
    define('TIGER_CORE_PATH', $coreRoot);
}

set_include_path(implode(PATH_SEPARATOR, array_filter([
    $coreRoot . '/vendor/webtigers/tigerzf/library',
    $coreRoot . '/library',
    get_include_path(),
])));

// Tigerssl_* module autoloader (ZF1 module layout -> class names; mirrors the web resource types).
spl_autoload_register(static function ($class) use ($moduleRoot) {
    if (strncmp($class, 'Tigerssl_', 9) !== 0) { return; }
    if ($class === 'Tigerssl_Bootstrap') {
        $file = $moduleRoot . '/Bootstrap.php';
    } elseif (preg_match('/^Tigerssl_Service_(.+)$/', $class, $m)) {
        $file = $moduleRoot . '/services/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tigerssl_Model_(.+)$/', $class, $m)) {
        $file = $moduleRoot . '/models/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tigerssl_Form_(.+)$/', $class, $m)) {
        $file = $moduleRoot . '/forms/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tigerssl_(.+)Controller$/', $class, $m)) {
        $file = $moduleRoot . '/controllers/' . $m[1] . 'Controller.php';
    } else {
        $file = $moduleRoot . '/' . str_replace('_', '/', substr($class, 9)) . '.php';
    }
    if (is_file($file)) { require $file; }
});

// PSR-4 for the test-support namespace.
spl_autoload_register(static function ($class) {
    $prefix = 'TigerSSL\\Tests\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) { return; }
    $rel  = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $file = __DIR__ . '/' . $rel;
    if (is_file($file)) { require $file; }
});
