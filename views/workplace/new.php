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
.quorum--option-row { margin-block-end: 0.5rem; }
.quorum--option-input {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.quorum--option-input input { flex: 1 1 auto; min-inline-size: 0; }
.quorum--option-remove {
    flex: 0 0 auto;
    inline-size: 2rem;
    block-size: 2rem;
    padding: 0;
    border: 1px solid var(--quorum-border, #c5c7ca);
    border-radius: 4px;
    background: transparent;
    color: inherit;
    font-size: 1.2rem;
    line-height: 1;
    cursor: pointer;
}
.quorum--option-remove:hover { background: rgba(0, 0, 0, 0.06); }
#quorum-add-option { margin-block-start: 0.25rem; }
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

        <?php // Scale: numeric (1 … N) OR named steps. For "named" a template
              // fills the option list (JS below); for "numeric" the server
              // generates the points. Only visible for `scales`. ?>
        <fieldset id="quorum-scale-fieldset" class="quorum--new-scale" hidden>
            <legend><?= _quorum('Skala') ?> <span aria-hidden="true">*</span></legend>
            <?php $scaleModeSel = (string) ($scaleMode ?? 'numeric'); ?>
            <label>
                <span><?= _quorum('Skalentyp') ?></span>
                <select name="scale_mode" id="quorum-scale-mode">
                    <option value="numeric" <?= $scaleModeSel !== 'named' ? 'selected' : '' ?>><?= _quorum('Numerisch (1 … N)') ?></option>
                    <option value="named"   <?= $scaleModeSel === 'named' ? 'selected' : '' ?>><?= _quorum('Benannte Stufen') ?></option>
                </select>
            </label>
            <label id="quorum-scale-points-wrap">
                <span><?= _quorum('Anzahl Skalenpunkte') ?></span>
                <?php $scalePointsSel = (int) ($scalePoints ?? 5); ?>
                <select name="scale_points" id="quorum-scale-points">
                    <?php foreach ([2, 3, 4, 5] as $n): ?>
                        <option value="<?= $n ?>" <?= $n === $scalePointsSel ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </fieldset>

        <?php // Answer options for mc/multi/emoji: dynamic, 2 by default,
              // extendable via “Add option” up to 20 (JS below). ?>
        <fieldset id="quorum-new-options-fieldset" class="quorum--new-options">
            <legend><?= _quorum('Antwort-Optionen') ?> <span aria-hidden="true">*</span></legend>
            <p class="quorum--new-options-hint" id="quorum-options-hint">
                <?= _quorum('Mindestens zwei Optionen — leere Felder werden ignoriert.') ?>
            </p>
            <?php // Template picker (emoji sets / named scale steps): fills the
                  // option list via JS. Options are populated per question type
                  // from the PRESETS object below; the result stays editable. ?>
            <label id="quorum-preset-wrap" hidden>
                <span><?= _quorum('Vorlage') ?></span>
                <select id="quorum-preset">
                    <option value=""><?= _quorum('Vorlage wählen …') ?></option>
                </select>
            </label>
            <div id="quorum-options-list">
                <?php foreach ($options as $i => $opt): ?>
                    <div class="quorum--option-row" data-option-row>
                        <label>
                            <span class="quorum--option-label"><?= htmlspecialchars(sprintf(_quorum('Option %d'), $i + 1), ENT_QUOTES) ?></span>
                            <span class="quorum--option-input">
                                <input type="text"
                                       name="options[]"
                                       value="<?= htmlspecialchars((string) ($opt['label'] ?? ''), ENT_QUOTES) ?>"
                                       placeholder="<?= htmlspecialchars(_quorum('Antwort-Text'), ENT_QUOTES) ?>">
                                <button type="button" class="quorum--option-remove" hidden
                                        aria-label="<?= htmlspecialchars(_quorum('Option entfernen'), ENT_QUOTES) ?>">&times;</button>
                            </span>
                        </label>
                        <label class="quorum--new-correct" hidden>
                            <input type="checkbox" name="options_correct[]" value="<?= $i ?>"
                                <?= !empty($opt['correct']) ? 'checked' : '' ?>>
                            <?= _quorum('Richtige Antwort (optional)') ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="quorum-add-option" class="button">
                + <?= _quorum('Weitere Option') ?>
            </button>
            <p class="quorum--new-options-hint" id="quorum-option-cap-hint" hidden>
                <?= htmlspecialchars(sprintf(_quorum('Maximal %d Optionen.'), 20), ENT_QUOTES) ?>
            </p>
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
    const sel        = document.getElementById('quorum-new-type');
    const opts       = document.getElementById('quorum-new-options-fieldset');
    const scaleFs    = document.getElementById('quorum-scale-fieldset');
    const modeSel    = document.getElementById('quorum-scale-mode');
    const pointsWrap = document.getElementById('quorum-scale-points-wrap');
    const presetWrap = document.getElementById('quorum-preset-wrap');
    const presetSel  = document.getElementById('quorum-preset');
    const hint       = document.getElementById('quorum-options-hint');
    const quiz       = document.getElementById('quorum-quiz-fieldset');
    const toggle     = document.getElementById('quorum-quiz-toggle');
    const list       = document.getElementById('quorum-options-list');
    const addBtn     = document.getElementById('quorum-add-option');
    const capHint    = document.getElementById('quorum-option-cap-hint');
    const EMOJI_HINT   = <?= json_encode(_quorum('Emoji-Zeichen als Antwort-Option eintragen — oder eine Vorlage wählen.')) ?>;
    const MC_HINT      = <?= json_encode(_quorum('Mindestens zwei Optionen — leere Felder werden ignoriert.')) ?>;
    const SCALE_HINT   = <?= json_encode(_quorum('Benennen Sie die Skalenstufen (höchste zuerst) — oder wählen Sie eine Vorlage.')) ?>;
    const OPTION_LABEL = <?= json_encode(_quorum('Option %d')) ?>;
    const MIN = 2, MAX = 20;

    // Templates: fill the option list. Names are translatable (_quorum), emoji
    // characters stay literal. The BBB set follows BigBlueButton reactions.
    const PRESETS = {
        scales: [
            { name: <?= json_encode(_quorum('Zustimmung (5-stufig)')) ?>,
              items: [<?= json_encode(_quorum('trifft zu')) ?>, <?= json_encode(_quorum('trifft eher zu')) ?>, <?= json_encode(_quorum('teils-teils')) ?>, <?= json_encode(_quorum('trifft eher nicht zu')) ?>, <?= json_encode(_quorum('trifft nicht zu')) ?>] },
            { name: <?= json_encode(_quorum('Zustimmung (4-stufig)')) ?>,
              items: [<?= json_encode(_quorum('trifft zu')) ?>, <?= json_encode(_quorum('trifft eher zu')) ?>, <?= json_encode(_quorum('trifft eher nicht zu')) ?>, <?= json_encode(_quorum('trifft nicht zu')) ?>] },
            { name: <?= json_encode(_quorum('Zustimmung (3-stufig)')) ?>,
              items: [<?= json_encode(_quorum('trifft zu')) ?>, <?= json_encode(_quorum('teils-teils')) ?>, <?= json_encode(_quorum('trifft nicht zu')) ?>] },
            { name: <?= json_encode(_quorum('Häufigkeit (5-stufig)')) ?>,
              items: [<?= json_encode(_quorum('immer')) ?>, <?= json_encode(_quorum('oft')) ?>, <?= json_encode(_quorum('manchmal')) ?>, <?= json_encode(_quorum('selten')) ?>, <?= json_encode(_quorum('nie')) ?>] }
        ],
        emoji: [
            { name: <?= json_encode(_quorum('Stimmung (3-stufig)')) ?>, items: ['😀', '😐', '🙁'] },
            { name: <?= json_encode(_quorum('Stimmung (5-stufig)')) ?>, items: ['😀', '🙂', '😐', '🙁', '😞'] },
            { name: <?= json_encode(_quorum('Daumen')) ?>, items: ['👍', '👎'] },
            { name: <?= json_encode(_quorum('Verständnis')) ?>, items: ['✅', '🤔', '❌'] },
            { name: <?= json_encode(_quorum('BBB-Reaktionen')) ?>, items: ['👍', '👎', '👏', '😀', '😕', '😮'] }
        ]
    };

    function rows() { return Array.prototype.slice.call(list.querySelectorAll('[data-option-row]')); }

    // Empty row template (from the first rendered row) used for cloning.
    const TEMPLATE = rows()[0].cloneNode(true);
    TEMPLATE.querySelectorAll('input').forEach(function (inp) {
        if (inp.type === 'checkbox') { inp.checked = false; } else { inp.value = ''; }
    });

    function buildRow(value) {
        const row = TEMPLATE.cloneNode(true);
        row.querySelectorAll('input').forEach(function (inp) {
            if (inp.type === 'checkbox') { inp.checked = false; }
            else { inp.value = (inp.name === 'options[]') ? (value || '') : ''; }
        });
        return row;
    }

    // Correct marking is OPTIONAL and applies only to single/multiple choice
    // (mc/multi) — independent of the quiz toggle. Off for all other types.
    function applyCorrectVisibility() {
        const show = (sel.value === 'mc' || sel.value === 'multi');
        list.querySelectorAll('.quorum--new-correct').forEach(function (el) { el.hidden = !show; });
    }

    // Re-set "Option N" labels, checkbox values (0-based = submit position) and
    // the remove buttons (only from >2 rows) after every change.
    function renumber() {
        const rs = rows();
        rs.forEach(function (row, i) {
            const lbl = row.querySelector('.quorum--option-label');
            if (lbl) lbl.textContent = OPTION_LABEL.replace('%d', i + 1);
            const cb = row.querySelector('input[name="options_correct[]"]');
            if (cb) cb.value = String(i);
            const rm = row.querySelector('.quorum--option-remove');
            if (rm) rm.hidden = rs.length <= MIN;
        });
        addBtn.disabled = rs.length >= MAX;
        if (capHint) capHint.hidden = rs.length < MAX;
        applyCorrectVisibility();
    }

    function addRow() {
        if (rows().length >= MAX) return;
        list.appendChild(buildRow(''));
        renumber();
    }

    // Rebuild the option list from a template (at least 2 rows).
    function setRows(values) {
        const vals = values.slice(0, MAX);
        while (vals.length < MIN) vals.push('');
        list.textContent = '';
        vals.forEach(function (v) { list.appendChild(buildRow(v)); });
        renumber();
    }

    // Populate the template select per question type (keep the placeholder).
    function populatePresetSelect(type) {
        if (presetSel.dataset.type === type) return;
        presetSel.dataset.type = type;
        presetSel.length = 1;
        (PRESETS[type] || []).forEach(function (p, i) {
            const o = document.createElement('option');
            o.value = String(i);
            o.textContent = p.name;
            presetSel.appendChild(o);
        });
        presetSel.value = '';
    }

    addBtn.addEventListener('click', addRow);
    list.addEventListener('click', function (e) {
        const btn = e.target.closest('.quorum--option-remove');
        if (!btn || rows().length <= MIN) return;
        btn.closest('[data-option-row]').remove();
        renumber();
    });
    presetSel.addEventListener('change', function () {
        const type = presetSel.dataset.type;
        const idx  = parseInt(presetSel.value, 10);
        const preset = type && !isNaN(idx) ? (PRESETS[type] || [])[idx] : null;
        if (preset) setRows(preset.items);
    });

    // Type change: scale → numeric (points) or named (template + list);
    // emoji → template + list; mc/multi → list; freitext → nothing. Quiz only mc.
    function update() {
        const v = sel.value;
        const isScales   = (v === 'scales');
        const scaleNamed = isScales && modeSel.value === 'named';
        const showList   = (v === 'mc' || v === 'multi' || v === 'emoji' || scaleNamed);
        scaleFs.hidden    = !isScales;
        pointsWrap.hidden = !(isScales && modeSel.value === 'numeric');
        opts.hidden       = !showList;
        const presetType  = (v === 'emoji') ? 'emoji' : (scaleNamed ? 'scales' : '');
        presetWrap.hidden = !presetType;
        if (presetType) populatePresetSelect(presetType);
        if (showList) hint.textContent = (v === 'emoji') ? EMOJI_HINT : (scaleNamed ? SCALE_HINT : MC_HINT);
        const isQuizable = (v === 'mc');
        quiz.hidden = !isQuizable;
        if (!isQuizable) toggle.checked = false;   // no hidden quiz_mode on submit
        applyCorrectVisibility();
    }

    // Single choice: at most ONE correct answer — the checkbox acts like a
    // radio (checking another clears the previous one). Multiple choice allows
    // several. Unchecking is always possible (= none marked).
    list.addEventListener('change', function (e) {
        const cb = e.target.closest('.quorum--new-correct input[type="checkbox"]');
        if (!cb || sel.value !== 'mc' || !cb.checked) return;
        list.querySelectorAll('.quorum--new-correct input[type="checkbox"]').forEach(function (other) {
            if (other !== cb) other.checked = false;
        });
    });

    sel.addEventListener('change', update);
    modeSel.addEventListener('change', update);
    update();
    renumber();
})();
</script>
