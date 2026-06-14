<?php

declare(strict_types=1);

/**
 * Mount view for the Quorum workplace admin full page.
 *
 * Same structure as the teacher course tab (`views/index/index.php`):
 * Vite CSS tags, a mount `<div>`, an ES module script.
 *
 * @var string             $bundleJs   path inside public/assets/
 * @var array<int,string>  $bundleCss  CSS paths inside public/assets/
 * @var string             $pluginUrl  plugin public URL root (for API calls)
 * @var string             $lang       Stud.IP locale (e.g. 'de_DE')
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<div
    id="quorum-workplace-app"
    data-plugin-url="<?= htmlspecialchars(rtrim($pluginUrl, '/'), ENT_QUOTES) ?>"
    data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
    data-view="<?= htmlspecialchars($view, ENT_QUOTES) ?>"
    data-csrf="<?= htmlspecialchars(\CSRFProtection::token(), ENT_QUOTES) ?>"
></div>

<script type="module" src="<?= htmlspecialchars($assetBaseUrl . '/public/' . $bundleJs, ENT_QUOTES) ?>"></script>
