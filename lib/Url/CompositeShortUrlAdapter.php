<?php

declare(strict_types=1);

namespace Quorum\Url;

/**
 * Transition adapter between the Quorum and Stud.IP worlds.
 *
 * Active when `short_urls` (Stud.IP-native) exists AND `quorum_short_urls`
 * (Quorum fallback) still has data — i.e. after a Stud.IP upgrade from
 * 6.0/6.1 to 6.2, before the CLI migrator has run.
 *
 * Behavior:
 *   - Write: primary only (Stud.IP-native). New aliases land immediately in
 *     the future-proof world.
 *   - Read: primary first, secondary as fallback. Old Quorum aliases stay
 *     anonymously resolvable.
 *   - existsAlias: OR over both sides — important for the migrator.
 *   - iterateAll/deleteByAlias: operate only on secondary (the migrator pulls
 *     from Quorum to native).
 *
 * Once the Quorum table is empty, `AdapterDetector` automatically switches to
 * the plain `NativeShortUrlAdapter`.
 */
final class CompositeShortUrlAdapter implements ShortUrlAdapter
{
    public function __construct(
        private readonly ShortUrlAdapter $primary,
        private readonly ShortUrlAdapter $secondary,
    ) {
    }

    public function create(string $path, string $userId, ?string $title): string
    {
        return $this->primary->create($path, $userId, $title);
    }

    public function resolve(string $alias): ?ShortUrlData
    {
        return $this->primary->resolve($alias) ?? $this->secondary->resolve($alias);
    }

    public function existsAlias(string $alias): bool
    {
        return $this->primary->existsAlias($alias)
            || $this->secondary->existsAlias($alias);
    }

    public function iterateAll(): iterable
    {
        // The migrator iterates only over the Quorum source (target is primary).
        return $this->secondary->iterateAll();
    }

    public function deleteByAlias(string $alias): void
    {
        // The migrator deletes the Quorum source record after a successful
        // native insert. The Stud.IP entry stays untouched.
        $this->secondary->deleteByAlias($alias);
    }

    public function import(ShortUrlData $data): void
    {
        // Composite is a transition adapter — anything imported should land in
        // the future world.
        $this->primary->import($data);
    }
}
