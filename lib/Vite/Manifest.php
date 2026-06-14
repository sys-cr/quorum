<?php

declare(strict_types=1);

namespace Quorum\Vite;

/**
 * Reads the Vite build manifest and resolves entry names to final asset URLs.
 *
 * On `npm run build` Vite writes a `manifest.json` to
 * `public/.vite/manifest.json` (see `vite.config.js`: outDir `public`,
 * the manifest lands in the `.vite/` subfolder of outDir, the assets under
 * `public/assets/`).
 * Format per entry:
 *
 *     {
 *       "resources/vue/course-app/main.js": {
 *         "file": "assets/course-app-Cf5oYxbZ.js",
 *         "css":  ["assets/course-app-CMeLHiCQ.css"],
 *         "isEntry": true
 *       },
 *       ...
 *     }
 *
 * Used by the `IndexController` and its view to insert the hashed bundle URLs
 * into `<script>` and `<link rel="stylesheet">` tags — cache busting without
 * manual URL maintenance.
 */
final class Manifest
{
    /** @var array<string, array{file: string, css?: array<int, string>, imports?: array<int, string>}> */
    private array $entries;

    public function __construct(string $manifestPath)
    {
        $this->entries = $this->load($manifestPath);
    }

    /**
     * Asset URL for an entry name, relative to the plugin's public asset directory.
     *
     * Example: `assetUrl('resources/vue/course-app/main.js')`
     *          → `'assets/course-app-Cf5oYxbZ.js'`
     */
    public function assetUrl(string $entryName): string
    {
        if (!isset($this->entries[$entryName])) {
            throw new \RuntimeException(sprintf(
                'Vite-Manifest hat keinen Entry "%s" — bekannte Keys: %s',
                $entryName,
                implode(', ', array_keys($this->entries)),
            ));
        }
        return $this->entries[$entryName]['file'];
    }

    /**
     * CSS asset URLs for an entry — including the CSS of all (transitively)
     * imported chunks.
     *
     * Vite places the CSS of shared components (QrCodeDialog, the result
     * charts, studip-components) into separate chunks; their CSS lives in the
     * respective chunk's manifest entry, NOT in the entry's `css`. Linking only
     * `entry['css']` would leave these components unstyled (e.g. the QR dialog
     * without overlay), because the PHP would otherwise have to rely on Vite's
     * runtime injection — which fails with the plugin base URL.
     *
     * @return array<int, string> CSS asset URLs (deduped, in load order)
     */
    public function cssUrls(string $entryName): array
    {
        if (!isset($this->entries[$entryName])) {
            throw new \RuntimeException('Vite-Manifest hat keinen Entry "' . $entryName . '"');
        }
        $css  = [];
        $seen = [];
        $this->collectCss($entryName, $css, $seen);
        return array_values(array_unique($css));
    }

    /**
     * @param array<int, string>   $css
     * @param array<string, bool>  $seen
     */
    private function collectCss(string $name, array &$css, array &$seen): void
    {
        if (isset($seen[$name])) {
            return;
        }
        $seen[$name] = true;

        $entry = $this->entries[$name] ?? null;
        if ($entry === null) {
            return;
        }
        foreach (($entry['css'] ?? []) as $file) {
            $css[] = $file;
        }
        foreach (($entry['imports'] ?? []) as $import) {
            $this->collectCss($import, $css, $seen);
        }
    }

    public function hasEntry(string $entryName): bool
    {
        return isset($this->entries[$entryName]);
    }

    /**
     * @return array<string, array{file: string, css?: array<int, string>, imports?: array<int, string>}>
     */
    private function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf(
                'Vite-Manifest fehlt: %s — bitte `npm run build` im dev/-Verzeichnis ausführen.',
                $path,
            ));
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Vite-Manifest konnte nicht gelesen werden: ' . $path);
        }
        try {
            $decoded = json_decode($raw, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Vite-Manifest enthält invalides JSON: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException('Vite-Manifest ist kein Objekt: ' . $path);
        }
        /** @var array<string, array{file: string, css?: array<int, string>, imports?: array<int, string>}> $decoded */
        return $decoded;
    }
}
