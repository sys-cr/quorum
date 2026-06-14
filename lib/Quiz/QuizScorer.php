<?php

declare(strict_types=1);

namespace Quorum\Quiz;

/**
 * Server-side quiz scoring (Kahoot-style).
 *
 * Points depend only on correctness and answer speed (speed from the timer).
 * Only correct/incorrect + remaining time enter the calculation — no personal data.
 *
 * Formula (correct answer): `max * (0.5 + 0.5 * remaining/total)` —
 * answered instantly = full points, answered at the deadline = half points.
 * Without a timer, a correct answer always scores full points (no speed component).
 *
 * Stud.IP-free by design, so unit-testable.
 */
final class QuizScorer
{
    public const DEFAULT_MAX_POINTS = 1000;

    public function score(
        bool $correct,
        ?int $remainingSeconds,
        ?int $totalSeconds,
        int $maxPoints = self::DEFAULT_MAX_POINTS,
    ): int {
        if (!$correct) {
            return 0;
        }
        // No valid timer: full points, no speed component.
        if ($totalSeconds === null || $totalSeconds <= 0 || $remainingSeconds === null) {
            return $maxPoints;
        }
        $ratio = max(0.0, min(1.0, $remainingSeconds / $totalSeconds));
        return (int) round($maxPoints * (0.5 + 0.5 * $ratio));
    }
}
