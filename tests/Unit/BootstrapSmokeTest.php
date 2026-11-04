<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerSSL\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The harness itself: tiger-core's autoloader resolves the Tiger_ and Zend_ bases, and the Tigerssl_
 * module loader resolves the module's own classes. If this fails, no other test can be trusted.
 */
final class BootstrapSmokeTest extends TestCase
{
    public function testTigerCoreAutoloaderIsWired(): void
    {
        $this->assertTrue(class_exists('Tiger_Application'), 'tiger-core autoloader should resolve Tiger_*');
        $this->assertTrue(class_exists('Zend_Config'), 'tigerzf autoloader should resolve Zend_*');
    }

    public function testModuleAutoloaderResolvesTigersslClasses(): void
    {
        $this->assertTrue(class_exists('Tigerssl_Model_Challenge'), 'models/ → Tigerssl_Model_*');
        $this->assertTrue(class_exists('Tigerssl_Service_Certificate'), 'services/ → Tigerssl_Service_*');
    }
}
