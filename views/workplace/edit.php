<?php

declare(strict_types=1);

/**
 * Edit form for an existing Quorum survey.
 *
 * Standard Stud.IP form markup, analogous to `views/workplace/new.php`. Option
 * fields become `readonly` once the poll has responses (`$optionsLocked ===
 * true`); question and course binding stay editable.
 *
 * Validation errors surface only via Stud.IP PageLayout notifications
 * (`PageLayout::postError`), not inline.
 *
 * @var \Quorum\Polls\Poll          $poll          original poll (header context)
 * @var string                      $question      prefilled question text
 * @var list<array{id?: string, label: string}> $options  prefilled options
 * @var string                      $seminarId     prefilled course id
 * @var bool                        $optionsLocked options locked (responses exist)
 * @var string                      $csrf          ready-made `<input>` CSRF tag
 * @var string                      $actionUrl     form action (workplace/update/{id})
 * @var string                      $cancelUrl     workplace index URL for cancel
 * @var array<int,string>           $bundleCss     Quorum token CSS
 * @var string                      $pluginUrl     plugin public URL root
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<style>
.quorum--workplace-edit-hero-title {
    margin: 0;
    font-weight: 400;
    font-size: 0.95rem;
    color: inherit;
    opacity: 0.85;
}
.quorum--workplace-edit-hero-desc {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
    color: inherit;
    line-height: 1.4;
}
</style>

<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>"
      method="post"
      class="default quorum--workplace-edit-form quorum--container quorum--container--narrow">

    <?= $csrf ?>

    <div class="quorum--hero-empty quorum--workplace-edit-hero">
        <p class="quorum--workplace-edit-hero-title">
            <?= _quorum('Umfrage bearbeiten') ?>
        </p>
        <p class="quorum--workplace-edit-hero-desc">
            <?php if ($optionsLocked): ?>
                <?= _quorum('Antwort-Optionen sind gesperrt, weil bereits Antworten eingegangen sind. Frage-Text und Veranstaltungs-Bindung können weiter geändert werden.') ?>
            <?php else: ?>
                <?= _quorum('Frage, Optionen und Veranstaltungs-Bindung können geändert werden, solange noch keine Antworten eingegangen sind.') ?>
            <?php endif; ?>
        </p>
    </div>

    <hr class="quorum--aurora-divider" aria-hidden="true">

    <fieldset>
        <legend><?= _quorum('Frage und Antwort-Optionen') ?></legend>

        <label>
            <span><?= _quorum('Frage') ?> <span aria-hidden="true">*</span></span>
            <textarea name="question"
                      rows="3"
                      required
            ><?= htmlspecialchars($question, ENT_QUOTES) ?></textarea>
        </label>

        <fieldset class="quorum--edit-options">
            <legend>
                <?= _quorum('Antwort-Optionen') ?>
                <?php if ($optionsLocked): ?>
                    <span class="quorum--edit-options-locked-marker" aria-hidden="true">🔒</span>
                <?php endif; ?>
            </legend>
            <?php foreach ($options as $i => $opt): ?>
                <label>
                    <span><?= htmlspecialchars(sprintf(_quorum('Option %d'), $i + 1), ENT_QUOTES) ?></span>
                    <input type="text"
                           name="options[]"
                           value="<?= htmlspecialchars((string) ($opt['label'] ?? ''), ENT_QUOTES) ?>"
                           <?php if ($optionsLocked): ?>readonly aria-readonly="true"<?php endif; ?>>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <?php
        // Stud.IP QuickSearch over the user's own courses with a switchable
        // semester filter (the `semesters` select overrides `:semesters` live
        // via contextual_data). Default = current semester; current + upcoming
        // + recently past are selectable.
        $currentSemester = \Semester::findCurrent();
        $allSemesters = \Semester::getAll();
        $now          = time();
        $semesterOptions = array_values(array_filter($allSemesters, static function ($s) use ($now) {
            return (int) $s->ende >= ($now - 60 * 60 * 24 * 550);
        }));
        usort($semesterOptions, static fn($a, $b) => (int) $b->beginn <=> (int) $a->beginn);
        $defaultSemesterId = $currentSemester ? $currentSemester->id : ($semesterOptions[0]->id ?? '');
        ?>
        <label>
            <span><?= _quorum('Semester (Suchbereich)') ?></span>
            <?php /* `semesters[]` (array) for StudipPDO IN expansion; a scalar would break param count (HY093). */ ?>
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
            // The current binding is prefilled as `defaultValue` so it is
            // visible and can be cleared. Search scope is role-aware (like
            // Stud.IP's file chooser): root → all courses, admin → courses of
            // own institutes, otherwise → own taught courses. `MyCoursesSearch`
            // binds different required params per branch (`:exclude` always,
            // `:userid` or `:institutes` by role); a missing one errors as
            // "missing parameter", an extra one as "invalid parameter", so pass
            // exactly the matching set.
            $userPerm        = $GLOBALS['perm']->get_perm();
            // `exclude` as ARRAY (not scalar): forces the StudipPDO expand path,
            // otherwise HY093 when no semester is bound (see new.php).
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
            // `Seminar_id` (capitalized) — Stud.IP StandardSearch convention;
            // lowercase `seminar_id` throws UnexpectedValueException.
            $seminarSearch   = new \QuickSearch(
                'seminar_id',
                new \MyCoursesSearch('Seminar_id', $userPerm, $semesterFilter)
            );
            $seminarSearch->withButton();
            $seminarSearch->setInputStyle('width: 100%;');
            if ($seminarId !== '') {
                // The course name is not available here (the repository
                // returns it only in PollSummary lists, not in Poll), so show
                // the id: the user sees that something is linked and can change
                // it via search or clear it with the QuickSearch "X".
                $seminarSearch->defaultValue($seminarId, $seminarId);
            }
            echo $seminarSearch->render();
            ?>
            <small><?= _quorum('Leer lassen für eine kursunabhängige Umfrage. Suchbereich = oben gewähltes Semester (Standard: laufendes; z. B. kommendes Semester wählbar).') ?></small>
        </label>
        <script>
        // Re-run the active search on semester change (avoid stale results).
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
    </fieldset>

    <fieldset>
        <legend><?= _quorum('Sichtbarkeit für Studierende') ?></legend>
        <?php // Opt-out: hidden 0 before the checkbox (see new.php). ?>
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

    <footer data-dialog-button>
        <?= \Studip\LinkButton::createCancel(_quorum('Abbrechen'), $cancelUrl) ?>
        <?= \Studip\Button::createAccept(_quorum('Speichern'), 'submit') ?>
    </footer>
</form>
