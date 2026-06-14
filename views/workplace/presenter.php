<?php

declare(strict_types=1);

/**
 * Mount view for the Quorum presenter app.
 *
 * Layout is `null` (see controller), so the page fills the whole browser
 * viewport — the Vue app calls `document.requestFullscreen()` from the first
 * user click (browser policy: fullscreen only after a gesture).
 *
 * The polls list is shipped as JSON in a `<script type="application/json">` tag
 * so the Vue bundle needs no extra API call to initialize (each "next question"
 * click then subscribes to that poll's live counts via the SSE composable).
 *
 * @var array{id: string, name: string} $collection
 * @var list<array{id: string, token: string, question: string, type: string,
 *                 options: array, is_active: bool, position: int,
 *                 response_count: int, join_url: string}> $polls
 * @var string                       $bundleJs
 * @var array<int,string>            $bundleCss
 * @var string                       $pluginUrl
 * @var string                       $lang
 * @var string                       $csrf
 * @var string                       $returnUrl
 */
?>
<!doctype html>
<html lang="<?= htmlspecialchars(substr($lang, 0, 2), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($collection['name'], ENT_QUOTES) ?> — Quorum Presenter</title>
    <?php foreach ($bundleCss as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
    <?php endforeach; ?>
</head>
<body class="quorum-presenter-body">
    <div
        id="quorum-presenter-app"
        data-plugin-url="<?= htmlspecialchars(rtrim($pluginUrl, '/'), ENT_QUOTES) ?>"
        data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>"
        data-return-url="<?= htmlspecialchars($returnUrl, ENT_QUOTES) ?>"
    ></div>

    <script type="application/json" id="quorum-presenter-data"><?=
        // JSON_HEX_TAG/AMP/APOS/QUOT prevent a `</script>` breakout via
        // free-text question/option content (stored XSS). No
        // JSON_UNESCAPED_SLASHES — `<\/script>` must stay escaped.
        json_encode([
            'collection' => $collection,
            'polls'      => $polls,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    ?></script>

    <script type="module" src="<?= htmlspecialchars($assetBaseUrl . '/public/' . $bundleJs, ENT_QUOTES) ?>"></script>
</body>
</html>
