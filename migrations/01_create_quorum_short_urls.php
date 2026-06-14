<?php

declare(strict_types=1);

/**
 * Quorum-owned short-link table.
 *
 * Schema mirrors Stud.IP `short_urls` (6.2+) 1:1, so the same rows can move
 * into native `short_urls` unchanged.
 *
 * Idempotent via `CREATE TABLE IF NOT EXISTS`.
 */
final class CreateQuorumShortUrls extends Migration
{
    public function description(): string
    {
        return 'Quorum: Kurzlink-Tabelle für Stud.IP < 6.2 (Adapter-Fallback)';
    }

    public function up(): void
    {
        DBManager::get()->exec(
            'CREATE TABLE IF NOT EXISTS `quorum_short_urls` (
                `id`      int(11)          NOT NULL AUTO_INCREMENT,
                `alias`   varchar(255)     NOT NULL,
                `path`    varchar(255)     NOT NULL,
                `title`   varchar(255)     NOT NULL DEFAULT "",
                `user_id` varchar(32)      NOT NULL,
                `mkdate`  int(11) unsigned NOT NULL,
                `chdate`  int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `alias` (`alias`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        DBManager::get()->exec('DROP TABLE IF EXISTS `quorum_short_urls`');
    }
}
