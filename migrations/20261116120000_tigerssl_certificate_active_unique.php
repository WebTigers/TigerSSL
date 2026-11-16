<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tigerssl_certificate: make the per-org domain key ACTIVE-ONLY (TIGER-511, the TIGER-274 schema norm).
 *
 * The original key was UNIQUE (org_id, domain, deleted) — which let only ONE soft-deleted row per org +
 * domain exist (a second removal of the same domain collided) and held nothing back for the live row the
 * norm cares about. The norm: index a generated column that is the value while the row is live and NULL
 * once it is soft-deleted, so a removal frees the value and live rows stay unique per org.
 */
return [
    'up' => [
        "ALTER TABLE `tigerssl_certificate`
            ADD COLUMN `domain_active` VARCHAR(191) GENERATED ALWAYS AS (IF(`deleted` = 0, `domain`, NULL)) VIRTUAL",
        "ALTER TABLE `tigerssl_certificate` DROP INDEX `uq_tigerssl_cert_domain`",
        "ALTER TABLE `tigerssl_certificate` ADD UNIQUE KEY `uq_tigerssl_cert_domain_active` (`org_id`, `domain_active`)",
        "ALTER TABLE `tigerssl_certificate` ADD KEY `ix_tigerssl_cert_org` (`org_id`, `deleted`)",
    ],
    'down' => [
        "ALTER TABLE `tigerssl_certificate` DROP INDEX `ix_tigerssl_cert_org`",
        "ALTER TABLE `tigerssl_certificate` DROP INDEX `uq_tigerssl_cert_domain_active`",
        "ALTER TABLE `tigerssl_certificate` DROP COLUMN `domain_active`",
        "ALTER TABLE `tigerssl_certificate` ADD UNIQUE KEY `uq_tigerssl_cert_domain` (`org_id`, `domain`, `deleted`)",
    ],
];
