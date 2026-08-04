<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Create tigerssl_certificate — the managed-certificate store.
 *
 * Timestamp version (YYYYMMDDHHMMSS), never 0001: the tiger_migration ledger is one shared bare-version
 * namespace across core + app + all modules, so a low number collides and silently no-ops.
 *
 * No key/secret columns: private keys + the ACME account key are on-disk 0600 files referenced by the
 * *_path columns (FEATURES §8/§10).
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `tigerssl_certificate` (
            `certificate_id` CHAR(36)     NOT NULL,
            `org_id`         VARCHAR(191)  NOT NULL DEFAULT '',
            `domain`         VARCHAR(191)  NOT NULL,
            `sans`           TEXT          NULL,               -- JSON array of extra SAN domains
            `challenge_type` VARCHAR(16)   NOT NULL DEFAULT 'http-01',
            `installer`      VARCHAR(16)   NOT NULL DEFAULT 'write-only',
            `auto_renew`     TINYINT(1)    NOT NULL DEFAULT 1,
            `issued_at`      DATETIME      NULL,
            `expires_at`     DATETIME      NULL,
            `serial`         VARCHAR(191)  NULL,
            `fingerprint`    VARCHAR(191)  NULL,
            `cert_path`      VARCHAR(512)  NULL,
            `key_path`       VARCHAR(512)  NULL,
            `chain_path`     VARCHAR(512)  NULL,
            `status`         VARCHAR(16)   NOT NULL DEFAULT 'pending',
            `last_error`     TEXT          NULL,
            `deleted`        TINYINT(1)    NOT NULL DEFAULT 0,
            `created_by`     CHAR(36)      NULL,
            `updated_by`     CHAR(36)      NULL,
            `created_at`     DATETIME      NULL,
            `updated_at`     DATETIME      NULL,
            PRIMARY KEY (`certificate_id`),
            UNIQUE KEY `uq_tigerssl_cert_domain` (`org_id`, `domain`, `deleted`),
            KEY `ix_tigerssl_cert_expires` (`expires_at`),
            KEY `ix_tigerssl_cert_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `tigerssl_certificate`",
    ],
];
