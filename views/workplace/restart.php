<?php

declare(strict_types=1);

/**
 * Restart form for an existing Quorum survey.
 *
 * Standard Stud.IP form markup, analogous to `views/workplace/new.php` and
 * `edit.php`. Mode choice as two radio cards — the selected one gets the Aurora
 * hero background (like empty states).
 *
 * @var \Quorum\Polls\Poll          $poll          original poll
 * @var int                         $responseCount current response count (preview subtitle)
 * @var string                      $mode          'compare' | 'duplicate' (preselected)
 * @var string                      $csrf          ready-made `<input>` CSRF tag
 * @var string                      $actionUrl     form action (workplace/restart_submit/{id})
 * @var string                      $cancelUrl     workplace index URL for cancel
 * @var array<int,string>           $bundleCss     Quorum token CSS
 * @var string                      $pluginUrl     plugin public URL root
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<style>
.quorum--workplace-restart-hero-title {
    margin: 0;
    font-weight: 400;
    font-size: 0.95rem;
    color: inherit;
    opacity: 0.85;
}
.quorum--workplace-restart-hero-question {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
    color: inherit;
    line-height: 1.4;
    /* Plaintext question — keep line breaks visible. */
    white-space: pre-line;
}
.quorum--workplace-restart-hero-meta {
    margin: 0;
    font-size: 0.85rem;
    opacity: 0.8;
}

/* Mode cards: the active card inherits the Aurora hero look. */
.quorum--restart-mode-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.quorum--restart-mode {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.75rem 1rem;
    border: 1px solid var(--quorum-border);
    border-radius: var(--quorum-radius);
    cursor: pointer;
    color: var(--quorum-fg);
    background: var(--quorum-bg);
}
.quorum--restart-mode:hover {
    background: color-mix(in srgb, var(--quorum-petrol) 6%, var(--quorum-bg));
}
.quorum--restart-mode.is-selected {
    background:   var(--quorum-hero-bg);
    color:        var(--quorum-hero-fg);
    border-color: var(--quorum-hero-border);
}
.quorum--restart-mode input[type="radio"] {
    margin-block-start: 0.2rem;
    flex: 0 0 auto;
}
.quorum--restart-mode-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.quorum--restart-mode-text strong { font-weight: 700; }
.quorum--restart-mode-text span   { font-size: 0.9rem; opacity: 0.85; }
.quorum--restart-mode:not(.is-selected) .quorum--restart-mode-text span {
    color: var(--quorum-muted);
    opacity: 1;
}
</style>

<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>"
      method="post"
      class="default quorum--workplace-restart-form quorum--container quorum--container--narrow">

    <?= $csrf ?>

    <!-- Aurora hero header previewing the original question -->
    <div class="quorum--hero-empty quorum--workplace-restart-hero">
        <p class="quorum--workplace-restart-hero-title">
            <?= _quorum('Umfrage erneut starten') ?>
        </p>
        <p class="quorum--workplace-restart-hero-question">
            <?= htmlspecialchars($poll->question, ENT_QUOTES) ?>
        </p>
        <p class="quorum--workplace-restart-hero-meta">
            <?= htmlspecialchars(sprintf(_quorum('Bisherige Antworten: %d'), $responseCount), ENT_QUOTES) ?>
        </p>
    </div>

    <hr class="quorum--aurora-divider" aria-hidden="true">

    <fieldset>
        <legend><?= _quorum('Modus wählen') ?></legend>

        <ul class="quorum--restart-mode-list">
            <li>
                <label class="quorum--restart-mode <?= $mode === 'compare' ? 'is-selected' : '' ?>">
                    <input type="radio"
                           name="mode"
                           value="compare"
                           <?= $mode === 'compare' ? 'checked' : '' ?>
                           required>
                    <div class="quorum--restart-mode-text">
                        <strong><?= _quorum('Vergleichen') ?></strong>
                        <span>
                            <?= _quorum('Neue Runde gehört zur selben Vergleichs-Kette. Antworten der ersten Runde bleiben separat erhalten und können später nebeneinander angezeigt werden.') ?>
                        </span>
                    </div>
                </label>
            </li>
            <li>
                <label class="quorum--restart-mode <?= $mode === 'duplicate' ? 'is-selected' : '' ?>">
                    <input type="radio"
                           name="mode"
                           value="duplicate"
                           <?= $mode === 'duplicate' ? 'checked' : '' ?>>
                    <div class="quorum--restart-mode-text">
                        <strong><?= _quorum('Duplizieren') ?></strong>
                        <span>
                            <?= _quorum('Neue Umfrage mit denselben Inhalten, aber eigenständig. Kein Bezug zur ursprünglichen Runde.') ?>
                        </span>
                    </div>
                </label>
            </li>
        </ul>
    </fieldset>

    <footer data-dialog-button>
        <?= \Studip\LinkButton::createCancel(_quorum('Abbrechen'), $cancelUrl) ?>
        <?= \Studip\Button::createAccept(_quorum('Erneut starten'), 'submit') ?>
    </footer>
</form>

<script>
// Clicking a mode card marks it selected immediately (otherwise the look waits
// for the next re-render). A pure CSS `:has(:checked)` solution would suffice
// but is not available in older browsers, so use this JS fallback.
document.addEventListener('change', (e) => {
    if (!(e.target instanceof HTMLInputElement) || e.target.name !== 'mode') return;
    document.querySelectorAll('.quorum--restart-mode').forEach((label) => {
        label.classList.toggle('is-selected', label.querySelector('input[type=radio]')?.checked === true);
    });
});
</script>
