<?php

declare(strict_types=1);

/**
 * Mount view for the collection list.
 *
 * Identical structure to `views/workplace/index.php`, but with
 * `data-mode="collections"` — the `workplace-app` bootstrapper then renders
 * `CollectionsIndex` (Vue) instead of the polls list. Collection cards thus
 * get the same action menu + lifecycle behavior as the single-survey cards
 * (previously: plain PHP cards without actions).
 *
 * @var string             $bundleJs   path inside public/assets/
 * @var array<int,string>  $bundleCss  CSS paths inside public/assets/
 * @var string             $pluginUrl  plugin public URL root (for API calls)
 * @var string             $lang       Stud.IP locale (e.g. 'de_DE')
 * @var string             $csrf       Stud.IP CSRF token (lifecycle POSTs)
 * @var string             $cid        course ID when inside the course frame ('' otherwise)
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<div
    id="quorum-workplace-app"
    class="<?= !empty($highContrast) ? 'theme-high-contrast' : '' ?>"
    data-plugin-url="<?= htmlspecialchars(rtrim($pluginUrl, '/'), ENT_QUOTES) ?>"
    data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
    data-mode="collections"
    data-cid="<?= htmlspecialchars($cid, ENT_QUOTES) ?>"
    data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>"
></div>

<script type="module" src="<?= htmlspecialchars($assetBaseUrl . '/public/' . $bundleJs, ENT_QUOTES) ?>"></script>
