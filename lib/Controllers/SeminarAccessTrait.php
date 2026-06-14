<?php

declare(strict_types=1);

namespace Quorum\Controllers;

/**
 * Write authorization for a TARGET course (IDOR protection).
 *
 * When creating/editing/binding a poll, the `seminar_id`/`cid` comes from the
 * client (form hidden field or JSON body). Without a check a teacher could
 * bind a poll to a FOREIGN course and thereby grant foreign co-teachers
 * evaluation access via `callerMayAccessPoll`.
 *
 * Therefore: whoever binds a poll to a seminar must be at least tutor there.
 * `null` = course-independent poll (no seminar) → allowed.
 */
trait SeminarAccessTrait
{
    protected function mayWriteSeminar(?string $seminarId): bool
    {
        if ($seminarId === null || $seminarId === '') {
            return true;
        }
        $perm = $GLOBALS['perm'] ?? null;
        return $perm !== null && $perm->have_studip_perm('tutor', $seminarId);
    }

    /**
     * Access check on an existing poll for management actions (edit, present,
     * restart, compare): owner OR tutor in the poll's seminar (co-teaching).
     * Course-independent polls (`seminarId === null`) stay owner-only. Mirrors
     * the static `ApiController::callerMayAccessPoll` rule for the Trails full
     * pages. Fail-closed; `seminarId` comes from the DB-loaded poll, never from
     * the request.
     */
    protected function mayAccessPoll(\Quorum\Polls\Poll $poll, string $userId): bool
    {
        if ($poll->userId === $userId) {
            return true;
        }
        $perm = $GLOBALS['perm'] ?? null;
        return $poll->seminarId !== null
            && $perm !== null
            && $perm->have_studip_perm('tutor', $poll->seminarId);
    }
}
