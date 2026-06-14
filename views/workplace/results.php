<?php

declare(strict_types=1);

/**
 * Mount view for the retrospective results page of a poll.
 *
 * Same structure as `views/workplace/index.php`, but with
 * `data-mode="results"` + `data-poll-id` — the `workplace-app` bootstrapper
 * then renders `PollResults` (chart per question type, CSV export,
 * definition download).
 *
 * @var string             $bundleJs   path within public/assets/
 * @var array<int,string>  $bundleCss  CSS paths within public/assets/
 * @var string             $pluginUrl  plugin public URL root (for API calls)
 * @var string             $lang       Stud.IP locale (e.g. 'de_DE')
 * @var string             $pollId     poll whose results are shown
 * @var string             $csrf       Stud.IP CSRF token (free-text moderation)
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<div
    id="quorum-workplace-app"
    data-plugin-url="<?= htmlspecialchars(rtrim($pluginUrl, '/'), ENT_QUOTES) ?>"
    data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
    data-mode="results"
    data-poll-id="<?= htmlspecialchars($pollId, ENT_QUOTES) ?>"
    data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>"
></div>

<script type="module" src="<?= htmlspecialchars($assetBaseUrl . '/public/' . $bundleJs, ENT_QUOTES) ?>"></script>
