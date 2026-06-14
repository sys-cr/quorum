<?php

declare(strict_types=1);

/**
 * Mount view for the teacher Vue 3 `course-app`.
 *
 * Minimal by design: a single mount point plus the two Vite-hashed assets
 * (CSS + JS bundle). Vue drives the full render stack from `main.js` —
 * hash routing (see `router.js`), Pinia state, vue-i18n with
 * `STUDIP.LANGUAGE_BASE`.
 *
 * Stud.IP integration:
 *   - CSRF token: Stud.IP sets `STUDIP.CSRF_TOKEN` globally in bootstrap;
 *     `useSurveysStore` and `useVotingStore` read it from there.
 *   - Locale: `STUDIP.LANGUAGE_BASE` from the Stud.IP frame.
 *   - Vue/Pinia/vue-router/vue-i18n come via `STUDIP.Vue.load()` — not
 *     bundled here.
 *
 * @var string             $bundleJs   path inside public/assets/
 * @var array<int,string>  $bundleCss  CSS paths inside public/assets/
 * @var string             $cid        Stud.IP course id
 * @var string             $pluginUrl  plugin public URL root
 * @var string             $lang       Stud.IP locale (e.g. 'de_DE')
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<div
    id="quorum-course-app"
    data-cid="<?= htmlspecialchars($cid, ENT_QUOTES) ?>"
    data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
    data-role="<?= htmlspecialchars($role ?? 'tutor', ENT_QUOTES) ?>"
    data-plugin-url="<?= htmlspecialchars(rtrim($pluginUrl, '/'), ENT_QUOTES) ?>"
></div>

<script type="module" src="<?= htmlspecialchars($assetBaseUrl . '/public/' . $bundleJs, ENT_QUOTES) ?>"></script>
