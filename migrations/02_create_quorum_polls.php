<?php

declare(strict_types=1);

/**
 * Polls backend: tables for active polls and student responses.
 *
 * `quorum_polls.token` is the public URL slug encoded in the QR code (on top
 * of the short-link indirection via `quorum_short_urls` / `short_urls`).
 *
 * `quorum_responses.client_hash` is a weak server-generated identifier
 * (IP hash with daily salt) — no PII, used only for rate limiting.
 */
final class CreateQuorumPolls extends Migration
{
    public function description(): string
    {
        return 'Quorum: Polls-Backend-Tabellen (quorum_polls + quorum_responses).';
    }

    public function up(): void
    {
        DBManager::get()->exec(
            'CREATE TABLE IF NOT EXISTS `quorum_polls` (
                `id`         varchar(32)      NOT NULL,
                `token`      varchar(32)      NOT NULL,
                `seminar_id` varchar(32)      NOT NULL,
                `user_id`    varchar(32)      NOT NULL,
                `question`   text             NOT NULL,
                `type`       varchar(32)      NOT NULL DEFAULT "mc",
                `options`    json             NOT NULL,
                `is_active`  tinyint(1)       NOT NULL DEFAULT 1,
                `mkdate`     int(11) unsigned NOT NULL,
                `chdate`     int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token` (`token`),
                KEY `seminar` (`seminar_id`),
                KEY `user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        DBManager::get()->exec(
            'CREATE TABLE IF NOT EXISTS `quorum_responses` (
                `id`          varchar(32)      NOT NULL,
                `poll_id`     varchar(32)      NOT NULL,
                `response`    json             NOT NULL,
                `client_hash` varchar(64)      DEFAULT NULL,
                `mkdate`      int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                KEY `poll_mkdate` (`poll_id`, `mkdate`),
                KEY `poll_client` (`poll_id`, `client_hash`, `mkdate`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        DBManager::get()->exec('DROP TABLE IF EXISTS `quorum_responses`');
        DBManager::get()->exec('DROP TABLE IF EXISTS `quorum_polls`');
    }
}
