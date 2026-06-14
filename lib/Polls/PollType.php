<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Canonical vocabulary of question types.
 *
 * Single source of truth preventing drift between service validation,
 * controllers, the Cliqr migrator and the DB default. All paths validate
 * against these constants.
 */
final class PollType
{
    public const MC       = 'mc';
    public const SCALES   = 'scales';
    public const EMOJI    = 'emoji';
    public const FREITEXT = 'freitext';
    public const MATRIX   = 'matrix';
    /** Multiple choice with MULTI-SELECT. Answer = `{selected: [id, …]}`. */
    public const MULTI    = 'multi';

    /** All valid question types. @var list<string> */
    public const ALL = [self::MC, self::SCALES, self::EMOJI, self::FREITEXT, self::MATRIX, self::MULTI];

    /**
     * Single-select types: the answer stores a SINGLE `$.selected` option
     * token (string). `multi` deliberately does NOT belong here (array
     * payload, own validation branch), but is counted option-based (OPTION_BASED).
     *
     * @var list<string>
     */
    public const SELECTION = [self::MC, self::SCALES, self::EMOJI];

    /**
     * Option-based types: result is "option ID → count" (bar/donut/bubble,
     * CSV export, ≥ 2 options on creation). Single-select PLUS multi.
     * `aggregateCountsForPoll` counts each selected option for `multi`.
     *
     * @var list<string>
     */
    public const OPTION_BASED = [self::MC, self::SCALES, self::EMOJI, self::MULTI];

    /**
     * Types supported by the simple Trails create form (`workplace/new`, flat
     * options list) — everything EXCEPT `matrix`. Matrix needs an associative
     * `{rows, scale}` schema and is only created via `api/create_action`; in
     * the flat form a matrix poll would be structurally broken, so `matrix`
     * deliberately does NOT belong here.
     *
     * @var list<string>
     */
    public const SIMPLE_FORM = [self::MC, self::SCALES, self::EMOJI, self::FREITEXT, self::MULTI];

    public static function isValid(string $type): bool
    {
        return in_array($type, self::ALL, true);
    }
}
