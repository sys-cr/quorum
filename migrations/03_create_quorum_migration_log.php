<?php

declare(strict_types=1);

/**
 * Audit trail for Cliqr-to-Quorum migrations.
 *
 * One row per `etask_assignments.id` holding the target poll id. A repeated
 * migrator run detects the entry and skips it — idempotent without touching
 * the source data.
 */
final class CreateQuorumMigrationLog extends Migration
{
    public function description(): string
    {
        return 'Quorum: Audit-Tabelle für Cliqr-→-Quorum-Migrationen.';
    }

    public function up(): void
    {
        DBManager::get()->exec(
            'CREATE TABLE IF NOT EXISTS `quorum_migration_log` (
                `etask_assignment_id` int(11)          NOT NULL,
                `quorum_poll_id`      varchar(32)      NOT NULL,
                `mkdate`              int(11) unsigned NOT NULL,
                PRIMARY KEY (`etask_assignment_id`),
                KEY `quorum_poll` (`quorum_poll_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        DBManager::get()->exec('DROP TABLE IF EXISTS `quorum_migration_log`');
    }
}
