<?php

declare(strict_types=1);

/**
 * Remove `QUORUM_AURORA_STYLE` without replacement.
 *
 * The toggle was never evaluated: the Quorum stylesheet has always been
 * loaded unconditionally, and no PHP or frontend code reads the value. On
 * installs that created it via `onEnable()` or migration 05/09, a dead
 * switch would otherwise sit in the Stud.IP system configuration ("Quorum"
 * section) forever. This migration cleans it up.
 *
 * No recreation in `down()`: the switch never had any effect, so a rollback
 * must not bring it back into the admin UI.
 */
final class QuorumDropAuroraConfig extends Migration
{
    public function description(): string
    {
        return 'Quorum: wirkungslosen QUORUM_AURORA_STYLE-Config-Key entfernen.';
    }

    public function up(): void
    {
        \Config::get()->delete('QUORUM_AURORA_STYLE');
    }

    public function down(): void
    {
        // Intentionally empty — see class doc.
    }
}
