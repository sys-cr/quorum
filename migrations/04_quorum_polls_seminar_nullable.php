<?php

declare(strict_types=1);

/**
 * Make `quorum_polls.seminar_id` nullable so teachers can create global polls
 * (without a course binding) from the workplace widget; binding a poll to a
 * course later stays possible.
 *
 * Only the NOT NULL constraint is dropped; existing polls keep their
 * `seminar_id`. The `KEY seminar` index remains and ignores NULL values, which
 * matches the `WHERE seminar_id = ?` filter queries.
 */
final class QuorumPollsSeminarNullable extends Migration
{
    public function description(): string
    {
        return 'Quorum: quorum_polls.seminar_id wird nullable (Workplace).';
    }

    public function up(): void
    {
        DBManager::get()->exec(
            'ALTER TABLE `quorum_polls`
                MODIFY COLUMN `seminar_id` varchar(32) DEFAULT NULL'
        );
    }

    public function down(): void
    {
        // Restore NOT NULL: set NULL seminar_id back to '' so the constraint
        // can apply again.
        DBManager::get()->exec(
            "UPDATE `quorum_polls` SET `seminar_id` = '' WHERE `seminar_id` IS NULL"
        );
        DBManager::get()->exec(
            'ALTER TABLE `quorum_polls`
                MODIFY COLUMN `seminar_id` varchar(32) NOT NULL'
        );
    }
}
