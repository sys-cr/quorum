<?php

declare(strict_types=1);

/**
 * Collection detail page.
 *
 * Shows member polls in `collection_position` order with up/down buttons
 * (POST to `collection_reorder`). New polls can be created right here
 * ("Create new survey" → create form with the collection target preset);
 * existing standalone polls are added via the action menu.
 *
 * @var \Quorum\Polls\Collection $collection
 * @var list<array{id: string, token: string, question: string, type: string,
 *                 options: array, is_active: bool, position: int,
 *                 response_count: int}> $polls
 * @var string                   $csrf
 * @var string                   $reorderUrl
 * @var string                   $editUrl
 * @var string                   $newPollUrl
 * @var string                   $downloadUrl
 * @var string                   $backUrl
 * @var string                   $presenterUrl
 * @var string                   $pluginUrl
 * @var string                   $assetBaseUrl
 * @var array<int,string>        $bundleCss
 * @var string                   $actionsCsrf
 * @var bool                     $actionsHasPolls
 * @var bool                     $anyActive
 * @var string|null              $qrJoinUrl
 * @var string|null              $qrShortUrl
 * @var string                   $qrTitle
 * @var string                   $actionsBundleJs
 * @var array<int,string>        $actionsBundleCss
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<style>
.quorum--collection-detail-meta {
    margin: 0;
    font-size: 0.9rem;
    color: var(--quorum-muted);
    white-space: pre-line;
}
.quorum--collection-header {
    display: flex;
    gap: 0.5rem 1rem;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: nowrap;
}
.quorum--collection-headline {
    min-inline-size: 0;
    flex: 1 1 auto;
}
.quorum--collection-headline h1 { margin: 0; }
.quorum--collection-polls {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.quorum--collection-poll {
    display: grid;
    grid-template-columns: 2.5rem 1fr auto auto auto;
    gap: 0.6rem;
    align-items: center;
    padding: 0.6rem 0.75rem;
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    background: var(--quorum-bg);
}
.quorum--collection-poll-pos {
    inline-size: 2rem;
    block-size: 2rem;
    border-radius: 999px;
    background: var(--quorum-petrol);
    color: var(--quorum-accent-contrast);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}
.quorum--collection-poll-question {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.3;
    white-space: pre-line;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.quorum--collection-poll-status {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--quorum-petrol) 14%, transparent);
    color: var(--quorum-petrol);
}
.quorum--collection-poll-status[data-active='0'] {
    background: color-mix(in srgb, var(--quorum-muted) 14%, transparent);
    color: var(--quorum-muted);
}
.quorum--collection-poll-type {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--quorum-muted) 35%, transparent);
    color: var(--quorum-muted);
    white-space: nowrap;
}
.quorum--collection-reorder-buttons {
    display: inline-flex;
    gap: 0.25rem;
}
.quorum--collection-reorder-buttons button {
    inline-size: 44px;
    block-size: 44px;
    padding: 0;
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    background: var(--quorum-bg);
    color: var(--quorum-brand);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.quorum--collection-reorder-buttons button:hover:not([disabled]),
.quorum--collection-reorder-buttons button:focus-visible {
    background: color-mix(in srgb, var(--quorum-petrol) 10%, var(--quorum-bg));
    outline: none;
}
.quorum--collection-reorder-buttons button[disabled] {
    opacity: 0.4;
    cursor: not-allowed;
}
.quorum--collection-reorder-buttons svg { display: block; }
</style>

<section class="quorum--collection-detail quorum--container">
    <header class="quorum--collection-header">
        <div class="quorum--collection-headline">
            <h1><?= htmlspecialchars($collection->name, ENT_QUOTES) ?></h1>
            <?php if ($collection->description !== null && $collection->description !== ''): ?>
                <p class="quorum--collection-detail-meta">
                    <?= htmlspecialchars($collection->description, ENT_QUOTES) ?>
                </p>
            <?php endif; ?>
        </div>

        <?php // Action menu placed prominently top-right next to the title (like
              // the poll detail page), not in a separate bar, so it does not get
              // lost. All management actions live in ONE Stud.IP-style action menu
              // (QuorumActionMenu like the cards). Vue mount, because the page is
              // rendered server-side; lifecycle runs through /api/collection_*
              // (CSRF), "QR code / Share …" and "Delete" are menu entries with
              // their own dialog. ?>
        <span
            id="quorum-collection-actions"
            data-name="<?= htmlspecialchars($collection->name, ENT_QUOTES) ?>"
            data-collection-id="<?= htmlspecialchars($collection->id, ENT_QUOTES) ?>"
            data-plugin-url="<?= htmlspecialchars(rtrim((string) $pluginUrl, '/'), ENT_QUOTES) ?>"
            data-csrf="<?= htmlspecialchars($actionsCsrf, ENT_QUOTES) ?>"
            data-archived="<?= $collection->isArchived() ? '1' : '0' ?>"
            data-any-active="<?= !empty($anyActive) ? '1' : '0' ?>"
            data-has-polls="<?= $actionsHasPolls ? '1' : '0' ?>"
            data-presenter-url="<?= htmlspecialchars($presenterUrl, ENT_QUOTES) ?>"
            data-edit-url="<?= htmlspecialchars($editUrl, ENT_QUOTES) ?>"
            data-new-poll-url="<?= htmlspecialchars($newPollUrl, ENT_QUOTES) ?>"
            data-download-url="<?= htmlspecialchars($downloadUrl, ENT_QUOTES) ?>"
            data-back-url="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>"
            data-qr-url="<?= htmlspecialchars((string) $qrJoinUrl, ENT_QUOTES) ?>"
            data-qr-short="<?= htmlspecialchars((string) $qrShortUrl, ENT_QUOTES) ?>"
            data-qr-title="<?= htmlspecialchars($qrTitle, ENT_QUOTES) ?>"
        ></span>
    </header>

    <hr class="quorum--aurora-divider" aria-hidden="true">

    <?php if (empty($polls)): ?>
        <div class="quorum--hero-empty">
            <p style="margin: 0; font-weight: 400; font-size: 0.95rem; color: inherit; opacity: 0.85;">
                <?= _quorum('Diese Sammlung ist leer.') ?>
            </p>
            <hr class="quorum--aurora-divider" aria-hidden="true">
            <p style="margin: 0; font-weight: 700; font-size: 1.1rem; color: inherit;">
                <?= _quorum('Im Workplace-Index können Sie über das Aktionsmenü einer Umfrage „Zu Sammlung hinzufügen" wählen.') ?>
            </p>
        </div>
    <?php else: ?>
        <h2><?= htmlspecialchars(sprintf(_quorum('Umfragen in dieser Sammlung (%d)'), count($polls)), ENT_QUOTES) ?></h2>

        <form action="<?= htmlspecialchars($reorderUrl, ENT_QUOTES) ?>" method="post" id="quorum-reorder-form">
            <?= $csrf ?>

            <?php
                // Readable poll type labels (same wording as the card badges in
                // the Vue frontend). Unknown types are not labelled.
                $typeLabels = [
                    'mc'       => _quorum('Multiple Choice'),
                    'multi'    => _quorum('Mehrfachauswahl'),
                    'scales'   => _quorum('Skala'),
                    'emoji'    => _quorum('Emoji'),
                    'freitext' => _quorum('Freitext'),
                    'matrix'   => _quorum('Matrix'),
                ];
            ?>

            <ol class="quorum--collection-polls">
                <?php foreach ($polls as $i => $poll): ?>
                    <li class="quorum--collection-poll">
                        <span class="quorum--collection-poll-pos">
                            <?= (int) ($poll['position'] + 1) ?>
                        </span>
                        <p class="quorum--collection-poll-question">
                            <?= htmlspecialchars($poll['question'], ENT_QUOTES) ?>
                        </p>
                        <span class="quorum--collection-poll-status" data-active="<?= $poll['is_active'] ? '1' : '0' ?>">
                            <?= $poll['is_active'] ? _quorum('Läuft') : _quorum('Pausiert') ?>
                        </span>
                        <?php if (!empty($typeLabels[$poll['type']])): ?>
                            <span class="quorum--collection-poll-type">
                                <?= htmlspecialchars($typeLabels[$poll['type']], ENT_QUOTES) ?>
                            </span>
                        <?php endif; ?>
                        <?php // Each poll can also be presented on its own
                              // (single-poll presenter, new tab), not only the
                              // whole collection. ?>
                        <a class="button quorum--collection-poll-present"
                           href="<?= htmlspecialchars(rtrim($pluginUrl, '/') . '/workplace/present_poll/' . $poll['id'], ENT_QUOTES) ?>"
                           target="_blank" rel="noopener">
                            <?= htmlspecialchars(_quorum('Präsentieren'), ENT_QUOTES) ?>
                        </a>
                        <span class="quorum--collection-reorder-buttons">
                            <button type="button"
                                    data-poll-up="<?= htmlspecialchars($poll['id'], ENT_QUOTES) ?>"
                                    aria-label="<?= htmlspecialchars(_quorum('Eins nach oben'), ENT_QUOTES) ?>"
                                    title="<?= htmlspecialchars(_quorum('Eins nach oben'), ENT_QUOTES) ?>"
                                    <?= $i === 0 ? 'disabled' : '' ?>>
                                <!-- Stud.IP arr_1up icon (SVG path from
                                     public/assets/images/icons/*/arr_1up.svg) -->
                                <svg width="16" height="16" viewBox="0 0 54 54" aria-hidden="true"
                                     xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                                    <path d="M6 37.5h10.63L27 27.13 37.37 37.5H48l-21-21z"/>
                                </svg>
                            </button>
                            <button type="button"
                                    data-poll-down="<?= htmlspecialchars($poll['id'], ENT_QUOTES) ?>"
                                    aria-label="<?= htmlspecialchars(_quorum('Eins nach unten'), ENT_QUOTES) ?>"
                                    title="<?= htmlspecialchars(_quorum('Eins nach unten'), ENT_QUOTES) ?>"
                                    <?= $i === count($polls) - 1 ? 'disabled' : '' ?>>
                                <svg width="16" height="16" viewBox="0 0 54 54" aria-hidden="true"
                                     xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                                    <path d="M48 16.5H37.37L27 26.87 16.63 16.5H6l21 21z"/>
                                </svg>
                            </button>
                        </span>
                        <input type="hidden" name="polls[]" value="<?= htmlspecialchars($poll['id'], ENT_QUOTES) ?>">
                    </li>
                <?php endforeach; ?>
            </ol>

            <p style="margin-block-start: 0.75rem;">
                <?= \Studip\Button::createAccept(_quorum('Reihenfolge speichern'), 'submit') ?>
            </p>
        </form>

        <script>
        // Plain DOM reorder without drag-drop. Up/down buttons swap the <li>
        // with its previous/next sibling; the form collects the new order of
        // the `polls[]` hidden inputs on submit.
        (function () {
            const list = document.querySelector('.quorum--collection-polls');
            if (!list) return;

            const swapWithPrev = (li) => {
                const prev = li.previousElementSibling;
                if (prev) list.insertBefore(li, prev);
                refreshDisabled();
            };
            const swapWithNext = (li) => {
                const next = li.nextElementSibling;
                if (next) list.insertBefore(next, li);
                refreshDisabled();
            };
            const refreshDisabled = () => {
                const items = list.querySelectorAll('.quorum--collection-poll');
                items.forEach((li, i) => {
                    li.querySelectorAll('.quorum--collection-poll-pos')
                      .forEach(s => s.textContent = String(i + 1));
                    const up = li.querySelector('[data-poll-up]');
                    const down = li.querySelector('[data-poll-down]');
                    if (up)   up.disabled   = i === 0;
                    if (down) down.disabled = i === items.length - 1;
                });
            };

            list.addEventListener('click', (e) => {
                const t = e.target;
                if (!(t instanceof HTMLButtonElement)) return;
                const li = t.closest('.quorum--collection-poll');
                if (!li) return;
                if (t.hasAttribute('data-poll-up'))   swapWithPrev(li);
                if (t.hasAttribute('data-poll-down')) swapWithNext(li);
            });
        })();
        </script>
    <?php endif; ?>

    <?php // Action menu bundle (QuorumActionMenu + QR/delete dialog) of the detail page. ?>
    <?php foreach ($actionsBundleCss as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
    <?php endforeach; ?>
    <script type="module" src="<?= htmlspecialchars($assetBaseUrl . '/public/' . $actionsBundleJs, ENT_QUOTES) ?>"></script>
</section>
