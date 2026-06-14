<?php

declare(strict_types=1);

/**
 * "Add to collection" form for a poll.
 *
 * Standard Stud.IP form with a selection list of the user's active
 * collections. Empty selection = remove from collection.
 *
 * @var \Quorum\Polls\Poll                       $poll
 * @var list<\Quorum\Polls\CollectionSummary>    $collections
 * @var string                                   $csrf
 * @var string                                   $actionUrl
 * @var string                                   $cancelUrl
 * @var array<int,string>                        $bundleCss
 * @var string                                   $pluginUrl
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<style>
.quorum--assign-hero-title {
    margin: 0;
    font-weight: 400;
    font-size: 0.95rem;
    color: inherit;
    opacity: 0.85;
}
.quorum--assign-hero-question {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
    color: inherit;
    line-height: 1.4;
    white-space: pre-line;
}
.quorum--assign-collection-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.quorum--assign-collection-option {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.6rem 0.8rem;
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    cursor: pointer;
    color: var(--quorum-fg);
    background: var(--quorum-bg);
}
.quorum--assign-collection-option.is-selected {
    background:   var(--quorum-hero-bg);
    color:        var(--quorum-hero-fg);
    border-color: var(--quorum-hero-border);
}
.quorum--assign-collection-option strong { font-weight: 700; }
.quorum--assign-collection-option small {
    display: block;
    font-size: 0.85rem;
    opacity: 0.85;
    margin-block-start: 0.2rem;
}
</style>

<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>" method="post"
      class="default quorum--container quorum--container--narrow">
    <?= $csrf ?>

    <div class="quorum--hero-empty">
        <p class="quorum--assign-hero-title">
            <?= _quorum('Zu Sammlung hinzufügen') ?>
        </p>
        <p class="quorum--assign-hero-question">
            <?= htmlspecialchars($poll->question, ENT_QUOTES) ?>
        </p>
    </div>

    <hr class="quorum--aurora-divider" aria-hidden="true">

    <fieldset>
        <legend><?= _quorum('Sammlung wählen') ?></legend>

        <ul class="quorum--assign-collection-list">
            <?php $currentId = $poll->collectionId; ?>

            <li>
                <label class="quorum--assign-collection-option <?= $currentId === null ? 'is-selected' : '' ?>">
                    <input type="radio" name="collection_id" value="" <?= $currentId === null ? 'checked' : '' ?>>
                    <span>
                        <strong><?= _quorum('Keine Sammlung (freistehend)') ?></strong>
                        <small><?= _quorum('Die Umfrage gehört zu keiner Sammlung — erscheint im Workplace unter „Aktive Umfragen".') ?></small>
                    </span>
                </label>
            </li>

            <?php foreach ($collections as $col): ?>
                <li>
                    <label class="quorum--assign-collection-option <?= $currentId === $col->id ? 'is-selected' : '' ?>">
                        <input type="radio" name="collection_id" value="<?= htmlspecialchars($col->id, ENT_QUOTES) ?>"
                               <?= $currentId === $col->id ? 'checked' : '' ?>>
                        <span>
                            <strong><?= htmlspecialchars($col->name, ENT_QUOTES) ?></strong>
                            <small><?= htmlspecialchars(sprintf(_quorum('%d Umfragen'), $col->pollCount), ENT_QUOTES) ?></small>
                        </span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (empty($collections)): ?>
            <p style="margin-block-start: 0.75rem; color: var(--quorum-muted);">
                <?= _quorum('Sie haben noch keine Sammlungen angelegt. Über „Sammlungen" in der Sidebar legen Sie eine an.') ?>
            </p>
        <?php endif; ?>
    </fieldset>

    <footer data-dialog-button>
        <?= \Studip\LinkButton::createCancel(_quorum('Abbrechen'), $cancelUrl) ?>
        <?= \Studip\Button::createAccept(_quorum('Übernehmen'), 'submit') ?>
    </footer>
</form>

<script>
document.addEventListener('change', (e) => {
    if (!(e.target instanceof HTMLInputElement) || e.target.name !== 'collection_id') return;
    document.querySelectorAll('.quorum--assign-collection-option').forEach((label) => {
        label.classList.toggle('is-selected', label.querySelector('input[type=radio]')?.checked === true);
    });
});
</script>
