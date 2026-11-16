<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Create tigerssl_certificate_name — the hostnames a certificate covers (TIGER-511).
 *
 * A certificate is per ORG and MULTI-DOMAIN: one row per covered name (the primary CN is one of them,
 * flagged `is_primary`), so a single-domain certificate is simply a SAN list of one. `org_id` is copied
 * from the parent certificate so a name's tenant is answerable without a join.
 *
 * `name_active` is the active-only unique (the TIGER-274 norm): a live hostname sits on exactly ONE live
 * certificate install-wide — a host resolves to one org (`site_domain` is globally unique), so two live
 * certificates claiming the same name is always a mistake — while a soft-deleted name frees the value.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `tigerssl_certificate_name` (
            `certificate_name_id` CHAR(36)     NOT NULL,
            `certificate_id`      CHAR(36)     NOT NULL,
            `org_id`              VARCHAR(191) NOT NULL DEFAULT '',
            `name`                VARCHAR(191) NOT NULL,
            `is_primary`          TINYINT(1)   NOT NULL DEFAULT 0,
            `status`              VARCHAR(32)  NOT NULL DEFAULT 'active',
            `deleted`             TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`          CHAR(36)         NULL,
            `updated_by`          CHAR(36)         NULL,
            `created_at`          DATETIME         NULL,
            `updated_at`          DATETIME         NULL,
            `name_active`         VARCHAR(191) GENERATED ALWAYS AS (IF(`deleted` = 0, `name`, NULL)) VIRTUAL,
            PRIMARY KEY (`certificate_name_id`),
            UNIQUE KEY `uq_tigerssl_name_active` (`name_active`),
            KEY `ix_tigerssl_name_cert` (`certificate_id`, `deleted`),
            KEY `ix_tigerssl_name_org` (`org_id`, `deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `tigerssl_certificate_name`",
    ],
];
