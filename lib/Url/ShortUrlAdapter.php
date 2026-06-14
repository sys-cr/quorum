<?php

declare(strict_types=1);

namespace Quorum\Url;

/**
 * Persistence adapter for poll short links.
 *
 * Three implementations:
 *   - `NativeShortUrlAdapter`    → Stud.IP `short_urls` (6.2+)
 *   - `QuorumShortUrlAdapter`    → own `quorum_short_urls` (6.0/6.1)
 *   - `CompositeShortUrlAdapter` → transition: writes native, reads both
 *
 * All three are selected via `Quorum\Url\AdapterDetector`; poll code only
 * sees `ShortUrlService` as the facade.
 */
interface ShortUrlAdapter
{
    /**
     * Creates a new short link (or returns the existing one for `path` of the
     * same `userId` if already created). Generates a new alias internally.
     *
     * @return string the stored alias.
     */
    public function create(string $path, string $userId, ?string $title): string;

    /**
     * Imports a complete record while keeping the original alias. Used by the
     * migrator so existing QR codes and published short links keep working
     * after the migration run — `create()` would generate a new alias.
     *
     * Precondition: the caller must check `existsAlias($data->alias)` for
     * conflicts beforehand — otherwise the adapter throws a UNIQUE-constraint
     * violation.
     */
    public function import(ShortUrlData $data): void;

    /**
     * Resolves an alias. Returns `null` when the alias is unknown.
     */
    public function resolve(string $alias): ?ShortUrlData;

    /**
     * Before the migrator's write attempt: checks whether the alias is
     * already taken (conflict detection).
     */
    public function existsAlias(string $alias): bool;

    /**
     * Iterates all entries — for the CLI migrator. A generator because in the
     * composite transition phase the table may hold thousands of entries.
     *
     * @return iterable<ShortUrlData>
     */
    public function iterateAll(): iterable;

    /**
     * Deletes an entry by alias. Used by the migrator after a successful
     * native insert.
     */
    public function deleteByAlias(string $alias): void;
}
