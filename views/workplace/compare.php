<?php

declare(strict_types=1);

/**
 * Mount view for the workplace compare full page (peer instruction).
 *
 * Same structure as `views/workplace/index.php`, but with `data-mode="compare"`
 * and `data-root-id="…"` so the `workplace-app` bootstrapper renders the
 * `WorkplaceCompare` component instead of the index list.
 *
 * @var string             $bundleJs   path inside public/assets/
 * @var array<int,string>  $bundleCss  CSS paths inside public/assets/
 * @var string             $pluginUrl  plugin public URL root (for API calls)
 * @var string             $lang       Stud.IP locale (e.g. 'de_DE')
 * @var string             $rootId     id of the compare chain's root poll
 * @var string             $rootQ      root question text (plaintext)
 * @var string             $csrf       Stud.IP CSRF token (for POST actions)
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<div
    id="quorum-workplace-app"
    data-plugin-url="<?= htmlspecialchars(rtrim($pluginUrl, '/'), ENT_QUOTES) ?>"
    data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
    data-mode="compare"
    data-root-id="<?= htmlspecialchars($rootId, ENT_QUOTES) ?>"
    data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>"
></div>

<script type="module" src="<?= htmlspecialchars($assetBaseUrl . '/public/' . $bundleJs, ENT_QUOTES) ?>"></script>
