<?php

declare(strict_types=1);

/**
 * Mount view for the anonymous student polls-app (QR-code target).
 *
 * Standalone mobile page (layout `null`): the Vue app fills the whole viewport
 * and loads the poll itself via `GET /api/polls/{token}`. The token reaches the
 * mount point via `data-token`; the language is set as the `STUDIP.LANGUAGE_BASE`
 * global that `polls-app/main.js` reads.
 *
 * @var string             $token
 * @var string             $bundleJs
 * @var array<int,string>  $bundleCss
 * @var string             $assetBaseUrl
 * @var string             $pluginUrl
 * @var string             $lang
 */
?>
<!doctype html>
<html lang="<?= htmlspecialchars(substr($lang, 0, 2), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars(_quorum('Quorum — Abstimmung'), ENT_QUOTES) ?></title>
    <style>
        /* Standalone mobile page without Stud.IP layout, so Stud.IP's Lato is
           absent. Set a sans-serif stack instead of the browser serif. */
        html { font-family: 'Lato', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-text-size-adjust: 100%; }
        body { margin: 0; }
        /* GPLv3 §7 mandatory attribution: server-rendered so it is visible
           even without JS. Sits as a normal block below the Vue app. */
        .quorum-attribution {
            margin: 2rem 0 env(safe-area-inset-bottom, 0);
            padding: 1rem 1.25rem;
            border-top: 1px solid #e0e0e0;
            color: #6a6a6a;
            font-size: .75rem;
            line-height: 1.45;
            text-align: center;
        }
        .quorum-attribution p { margin: .25rem 0; }
        .quorum-attribution a { color: inherit; text-decoration: underline; }
    </style>
    <?php foreach ($bundleCss as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
    <?php endforeach; ?>
</head>
<body class="quorum-polls-body">
    <div
        id="quorum-polls-app"
        data-token="<?= htmlspecialchars($token, ENT_QUOTES) ?>"
        data-plugin-url="<?= htmlspecialchars(rtrim($pluginUrl, '/'), ENT_QUOTES) ?>"
    ></div>

    <?php // Sibling of the Vue mount node so Vue does not replace it on render. ?>
    <footer class="quorum-attribution">
        <p>
            <a href="<?= htmlspecialchars($manualUrl, ENT_QUOTES) ?>"><?= htmlspecialchars(_quorum('Anleitung herunterladen'), ENT_QUOTES) ?></a>
        </p>
        <p><?= htmlspecialchars(\Quorum\AttributionHelper::attributionText(), ENT_QUOTES) ?></p>
        <p>
            <a href="<?= htmlspecialchars(\Quorum\AttributionHelper::REPOSITORY, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(_quorum('Quellcode (GitHub)'), ENT_QUOTES) ?></a>
            &middot;
            <a href="<?= htmlspecialchars(\Quorum\AttributionHelper::LICENSE_URL, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(_quorum('Lizenz (GPLv3)'), ENT_QUOTES) ?></a>
        </p>
    </footer>

    <script>
        window.STUDIP = window.STUDIP || {};
        window.STUDIP.LANGUAGE_BASE = <?= json_encode($lang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script type="module" src="<?= htmlspecialchars($assetBaseUrl . '/public/' . $bundleJs, ENT_QUOTES) ?>"></script>
</body>
</html>
