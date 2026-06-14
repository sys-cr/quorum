<?php

declare(strict_types=1);

/**
 * Create form for a new Quorum survey.
 *
 * Standard Stud.IP form markup — `<form class="default">`, standard inputs,
 * `<button class="button">`. No Vue mount. Opened as a full page (not a dialog)
 * so Stud.IP widgets initialize correctly.
 *
 * @var string                       $question      prefilled question text (re-render on validation error)
 * @var list<array{label: string}>   $options       prefilled answer options
 * @var string                       $type          prefilled question type (re-render on validation error)
 * @var string                       $seminarId     prefilled course id (empty string = global)
 * @var string                       $csrf          ready-made `<input>` CSRF tag
 * @var string                       $actionUrl     form action URL (workplace/create)
 * @var string                       $cancelUrl     workplace index URL for cancel
 * @var array<int,string>            $bundleCss     Quorum token / Aurora CSS from workplace-app bundle
 * @var string                       $pluginUrl     plugin public URL root
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<style>
.quorum--workplace-new-hero-title {
    margin: 0;
    font-weight: 400;
    font-size: 0.95rem;
    color: inherit;
    opacity: 0.85;
}
.quorum--workplace-new-hero-desc {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
    color: inherit;
    line-height: 1.4;
}
.quorum--new-options-hint {
    margin-block-end: 0.5rem;
    color: #666;
}
</style>

<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>"
      method="post"
      id="quorum-new-form"
      class="default quorum--workplace-new-form quorum--container quorum--container--narrow">

    <?= $csrf ?>

    <div class="quorum--hero-empty quorum--workplace-new-hero">
        <p class="quorum--workplace-new-hero-title">
            <?= _quorum('Neue Quorum-Umfrage anlegen') ?>
        </p>
        <p class="quorum--workplace-new-hero-desc">
            <?= _quorum('Stellen Sie eine Frage und wählen Sie den Fragetyp. Die Umfrage startet sofort und ist über die Workplace-Übersicht steuerbar.') ?>
        </p>
        <?php if (($collectionId ?? '') !== ''): ?>
            <?php // Direct creation from within a collection: the target is
                  // fixed and sent along as a hidden field — no separate
                  // "Add to collection" step needed anymore. ?>
            <p class="quorum--workplace-new-hero-desc">
                <strong><?= _quorum('Sammlung') ?>:</strong>
                <?= htmlspecialchars($collectionName, ENT_QUOTES) ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (($collectionId ?? '') !== ''): ?>
        <input type="hidden" name="collection_id" value="<?= htmlspecialchars($collectionId, ENT_QUOTES) ?>">
    <?php endif; ?>

    <hr class="quorum--aurora-divider" aria-hidden="true">

    <fieldset>
        <legend><?= _quorum('Frage und Fragetyp') ?></legend>

        <label>
            <span><?= _quorum('Frage') ?> <span aria-hidden="true">*</span></span>
            <textarea name="question"
                      rows="3"
                      required
                      placeholder="<?= htmlspecialchars(_quorum('Was war heute der wichtigste Begriff …?'), ENT_QUOTES) ?>"
            ><?= htmlspecialchars($question, ENT_QUOTES) ?></textarea>
        </label>

        <label>
            <span><?= _quorum('Fragetyp') ?></span>
            <select name="type" id="quorum-new-type">
                <option value="mc"       <?= ($type ?? 'mc') === 'mc'       ? 'selected' : '' ?>><?= _quorum('Multiple Choice (eine Antwort)') ?></option>
                <option value="multi"    <?= ($type ?? 'mc') === 'multi'    ? 'selected' : '' ?>><?= _quorum('Multiple Choice (Mehrfachauswahl)') ?></option>
                <option value="scales"   <?= ($type ?? 'mc') === 'scales'   ? 'selected' : '' ?>><?= _quorum('Skala (Likert)') ?></option>
                <option value="freitext" <?= ($type ?? 'mc') === 'freitext' ? 'selected' : '' ?>><?= _quorum('Freitext (offene Antwort)') ?></option>
                <option value="emoji"    <?= ($type ?? 'mc') === 'emoji'    ? 'selected' : '' ?>><?= _quorum('Emoji-Reaktion') ?></option>
            </select>
        </label>

        <fieldset id="quorum-new-options-fieldset" class="quorum--new-options">
            <legend><?= _quorum('Antwort-Optionen') ?> <span aria-hidden="true">*</span></legend>
            <p class="quorum--new-options-hint" id="quorum-options-hint">
                <?= _quorum('Mindestens zwei Optionen — leere Felder werden ignoriert.') ?>
            </p>
            <?php foreach ($options as $i => $opt): ?>
                <label>
                    <span><?= htmlspecialchars(sprintf(_quorum('Option %d'), $i + 1), ENT_QUOTES) ?></span>
                    <input type="text"
                           name="options[]"
                           value="<?= htmlspecialchars((string) ($opt['label'] ?? ''), ENT_QUOTES) ?>"
                           placeholder="<?= htmlspecialchars(_quorum('Antwort-Text'), ENT_QUOTES) ?>">
                </label>
                <label class="quorum--new-correct" hidden>
                    <input type="checkbox" name="options_correct[]" value="<?= $i ?>"
                        <?= !empty($opt['correct']) ? 'checked' : '' ?>>
                    <?= _quorum('Richtige Antwort (Quiz)') ?>
                </label>
            <?php endforeach; ?>
            <label>
                <span><?= htmlspecialchars(_quorum('Option 3 (optional)'), ENT_QUOTES) ?></span>
                <input type="text" name="options[]"
                       placeholder="<?= htmlspecialchars(_quorum('Antwort-Text'), ENT_QUOTES) ?>">
            </label>
            <label class="quorum--new-correct" hidden>
                <input type="checkbox" name="options_correct[]" value="2">
                <?= _quorum('Richtige Antwort (Quiz)') ?>
            </label>
            <label>
                <span><?= htmlspecialchars(_quorum('Option 4 (optional)'), ENT_QUOTES) ?></span>
                <input type="text" name="options[]"
                       placeholder="<?= htmlspecialchars(_quorum('Antwort-Text'), ENT_QUOTES) ?>">
            </label>
            <label class="quorum--new-correct" hidden>
                <input type="checkbox" name="options_correct[]" value="3">
                <?= _quorum('Richtige Antwort (Quiz)') ?>
            </label>
        </fieldset>

        <fieldset id="quorum-quiz-fieldset" hidden>
            <legend><?= _quorum('Quiz-Modus') ?></legend>
            <label>
                <input type="checkbox" name="quiz_mode" value="1" id="quorum-quiz-toggle"
                    <?= !empty($quizMode) ? 'checked' : '' ?>>
                <?= _quorum('Quiz-Modus aktivieren (Punkte für richtige und schnelle Antworten, pseudonymes Leaderboard)') ?>
            </label>
            <p class="quorum--new-options-hint">
                <?= _quorum('Markieren Sie dafür oben mindestens eine Option als richtig. Teilnehmende nehmen nur mit frei gewähltem Spitznamen am Leaderboard teil (Opt-in).') ?>
            </p>
        </fieldset>

        <fieldset>
            <legend><?= _quorum('Sichtbarkeit für Studierende') ?></legend>
            <?php // Opt-out: hidden 0 BEFORE the checkbox — unchecked sends 0,
                  // checked overrides the checkbox with 1. Checked by default. ?>
            <input type="hidden" name="results_public" value="0">
            <label>
                <input type="checkbox" name="results_public" value="1"
                    <?= (!isset($resultsPublic) || $resultsPublic) ? 'checked' : '' ?>>
                <?= _quorum('Ergebnisse für Studierende im Kurs-Reiter sichtbar') ?>
            </label>
            <p class="quorum--new-options-hint">
                <?= _quorum('Standardmäßig sehen Teilnehmende die Ergebnisse beendeter Umfragen im Quorum-Reiter. Abwählen, um sie zu verbergen. Die Teilnahme an laufenden Umfragen bleibt davon unberührt.') ?>
            </p>
        </fieldset>

        <?php if ($seminarLocked): ?>
            <label>
                <span><?= _quorum('Veranstaltung') ?></span>
                <input type="hidden" name="seminar_id" value="<?= htmlspecialchars($seminarId, ENT_QUOTES) ?>">
                <p class="quorum--workplace-new-locked-seminar">
                    <strong><?= htmlspecialchars($seminarName, ENT_QUOTES) ?></strong>
                    <small><?= _quorum('Diese Veranstaltung wird automatisch verknüpft, weil Sie den Quorum-Tab dieser Veranstaltung geöffnet haben.') ?></small>
                </p>
            </label>
        <?php else: ?>
            <?php
            $currentSemester = \Semester::findCurrent();
            // Semester switcher: QuickSearch serializes the whole form and
            // sends sibling fields as contextual_data to the search; the SQL
            // param `:semesters` is bound from it and overrides the constructor
            // default live (no reload). Offered: current + future + recently
            // past semesters (newest first).
            $allSemesters = \Semester::getAll();
            $now          = time();
            $semesterOptions = array_values(array_filter($allSemesters, static function ($s) use ($now) {
                // ended no more than ~18 months ago (keeps the list short,
                // covers running + upcoming + recently past)
                return (int) $s->ende >= ($now - 60 * 60 * 24 * 550);
            }));
            // newest first
            usort($semesterOptions, static fn($a, $b) => (int) $b->beginn <=> (int) $a->beginn);
            $defaultSemesterId = $currentSemester ? $currentSemester->id : ($semesterOptions[0]->id ?? '');
            ?>
            <label>
                <span><?= _quorum('Semester (Suchbereich)') ?></span>
                <?php /* `semesters[]` (array): MyCoursesSearch binds `:semesters`
                         as an array (StudipPDO IN expansion). A scalar override
                         would break the param count (HY093); the array keeps it. */ ?>
                <select name="semesters[]" class="quorum-semester-filter" style="width: 100%;">
                    <?php foreach ($semesterOptions as $sem): ?>
                        <option value="<?= htmlspecialchars($sem->id, ENT_QUOTES) ?>"
                            <?= $sem->id === $defaultSemesterId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sem->name, ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?= _quorum('Veranstaltung verknüpfen (optional)') ?></span>
                <?php
                // Role-aware search scope (like Stud.IP's own file chooser via
                // `$GLOBALS['perm']->get_perm()`): root → all courses, admin →
                // courses of own institutes, otherwise → own taught courses.
                // `MyCoursesSearch` binds different required params per branch;
                // a missing one throws "missing parameter in query: :<name>",
                // an extra one "invalid parameter", so pass exactly the matching
                // set. `exclude=''` (exclude nothing) always applies.
                // `semesters` as constructor default keeps the filter condition
                // present in SQL (fallback without JS); the select overrides the
                // value at runtime.
                $userPerm        = $GLOBALS['perm']->get_perm();
                // `exclude` as ARRAY (not scalar): forces StudipPDO onto the
                // array expand/emulate path — otherwise (no semester bound, no
                // array param) the native path also binds the form fields sent
                // along by QuickSearch → real HY093.
                $semesterFilter  = ['exclude' => ['']];
                if ($defaultSemesterId !== '') {
                    $semesterFilter['semesters'] = [$defaultSemesterId];
                }
                if ($userPerm === 'admin') {
                    $semesterFilter['institutes'] =
                        array_column(\Institute::getMyInstitutes(), 'Institut_id') ?: '';
                } elseif ($userPerm !== 'root') {
                    $semesterFilter['userid'] = $GLOBALS['user']->id;
                }
                $seminarSearch   = new \QuickSearch(
                    'seminar_id',
                    new \MyCoursesSearch('Seminar_id', $userPerm, $semesterFilter)
                );
                $seminarSearch->withButton();
                $seminarSearch->setInputStyle('width: 100%;');
                if ($seminarId !== '') {
                    $seminarSearch->defaultValue($seminarId, '');
                }
                echo $seminarSearch->render();
                ?>
                <small><?= _quorum('Leer lassen für eine kursunabhängige Umfrage. Suchbereich = oben gewähltes Semester (Standard: laufendes; z. B. kommendes Semester wählbar).') ?></small>
            </label>
            <script>
            // Re-run the active search on semester change so a cached result
            // from the previous semester does not linger.
            (function () {
                var sel = document.querySelector('select[name="semesters[]"]');
                var inp = document.querySelector('input[name="seminar_id_parameter"]');
                if (!sel || !inp || sel.dataset.qsBound) { return; }
                sel.dataset.qsBound = '1';
                sel.addEventListener('change', function () {
                    if (!inp.value || !window.jQuery) { return; }
                    try {
                        var $inp = window.jQuery(inp);
                        // Stud.IP widget is `quicksearch` (extends ui.autocomplete).
                        if (typeof $inp.quicksearch === 'function') {
                            $inp.quicksearch('search', inp.value);
                        } else if (typeof $inp.autocomplete === 'function') {
                            $inp.autocomplete('search', inp.value);
                        }
                    } catch (e) { /* no-op */ }
                });
            }());
            </script>
        <?php endif; ?>

        <label>
            <span><?= _quorum('Zeitlimit in Minuten (optional)') ?></span>
            <input type="number" name="duration_minutes" min="0" step="1" inputmode="numeric"
                   value="" placeholder="<?= htmlspecialchars(_quorum('z. B. 5 – leer = kein Limit'), ENT_QUOTES) ?>">
            <small><?= _quorum('Nach Ablauf stoppt der Server die Abstimmung automatisch und lehnt verspätete Antworten ab. Leer oder 0 = kein Limit.') ?></small>
        </label>
    </fieldset>

    <footer data-dialog-button>
        <?= \Studip\LinkButton::createCancel(_quorum('Abbrechen'), $cancelUrl) ?>
        <?= \Studip\Button::createAccept(_quorum('Umfrage anlegen'), 'submit') ?>
    </footer>
</form>

<script>
(function () {
    const sel    = document.getElementById('quorum-new-type');
    const opts   = document.getElementById('quorum-new-options-fieldset');
    const hint   = document.getElementById('quorum-options-hint');
    const quiz   = document.getElementById('quorum-quiz-fieldset');
    const toggle = document.getElementById('quorum-quiz-toggle');
    const checks = document.querySelectorAll('.quorum--new-correct');
    const EMOJI_HINT = <?= json_encode(_quorum('Emoji-Zeichen als Antwort-Option eintragen (z. B. 😀, 😐, 😕).')) ?>;
    const MC_HINT    = <?= json_encode(_quorum('Mindestens zwei Optionen — leere Felder werden ignoriert.')) ?>;

    function update() {
        const v = sel.value;
        if (v === 'freitext') {
            opts.hidden = true;
        } else {
            opts.hidden = false;
            hint.textContent = (v === 'emoji') ? EMOJI_HINT : MC_HINT;
        }
        // Quiz only for "Multiple Choice (one answer)", in two steps: first the
        // opt-in toggle, the per-option "correct answer" boxes only once it is
        // actually on. Otherwise every ordinary poll (mc is the default) would
        // confusingly show "correct answer" next to its options.
        const isQuizable = (v === 'mc');
        quiz.hidden = !isQuizable;
        if (!isQuizable) toggle.checked = false;   // no hidden quiz_mode on submit
        const showCorrect = isQuizable && toggle.checked;
        checks.forEach(function (el) { el.hidden = !showCorrect; });
    }

    sel.addEventListener('change', update);
    toggle.addEventListener('change', update);
    update();
})();
</script>
