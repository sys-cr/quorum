<?php

declare(strict_types=1);

/**
 * Create/edit form for a Quorum collection.
 *
 * Standard Stud.IP form markup, Aurora hero header, Aurora divider.
 * Validation errors surface via `PageLayout::postError()` (notification bar).
 *
 * @var string                              $name        prefilled name
 * @var string                              $description prefilled description
 * @var string                              $mode        'create' | 'edit'
 * @var \Quorum\Polls\Collection|null       $collection  edit mode: existing collection
 * @var string                              $csrf        ready-made `<input>` CSRF tag
 * @var string                              $actionUrl   form action URL
 * @var string                              $cancelUrl   workplace URL for cancel
 * @var array<int,string>                   $bundleCss   Quorum token CSS
 * @var string                              $pluginUrl   plugin public URL root
 */
?>

<?php foreach ($bundleCss as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBaseUrl . '/public/' . $css, ENT_QUOTES) ?>">
<?php endforeach; ?>

<style>
.quorum--collection-hero-title {
    margin: 0;
    font-weight: 400;
    font-size: 0.95rem;
    color: inherit;
    opacity: 0.85;
}
.quorum--collection-hero-desc {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
    color: inherit;
    line-height: 1.4;
}
</style>

<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>"
      method="post"
      class="default quorum--collection-form quorum--container quorum--container--narrow">

    <?= $csrf ?>

    <div class="quorum--hero-empty">
        <p class="quorum--collection-hero-title">
            <?= $mode === 'edit' ? _quorum('Sammlung bearbeiten') : _quorum('Neue Sammlung anlegen') ?>
        </p>
        <p class="quorum--collection-hero-desc">
            <?= _quorum('Eine Sammlung ist eine geordnete Liste von Umfragen, die Sie im Presenter-Modus nacheinander durchspielen können.') ?>
        </p>
    </div>

    <hr class="quorum--aurora-divider" aria-hidden="true">

    <fieldset>
        <legend><?= _quorum('Sammlung') ?></legend>

        <label>
            <span><?= _quorum('Name') ?> <span aria-hidden="true">*</span></span>
            <input type="text"
                   name="name"
                   required
                   maxlength="255"
                   value="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
                   placeholder="<?= htmlspecialchars(_quorum('z. B. „Vorlesung 3 — Zellbiologie"'), ENT_QUOTES) ?>">
        </label>

        <label>
            <span><?= _quorum('Beschreibung (optional)') ?></span>
            <textarea name="description" rows="3"><?= htmlspecialchars($description, ENT_QUOTES) ?></textarea>
        </label>
    </fieldset>

    <fieldset>
        <legend><?= _quorum('Veranstaltung') ?></legend>

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
                $userPerm        = $GLOBALS['perm']->get_perm();
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
                <small><?= _quorum('Leer lassen für eine kursunabhängige Sammlung. Suchbereich = oben gewähltes Semester.') ?></small>
            </label>
            <script>
            (function () {
                var sel = document.querySelector('select[name="semesters[]"]');
                var inp = document.querySelector('input[name="seminar_id_parameter"]');
                if (!sel || !inp || sel.dataset.qsBound) { return; }
                sel.dataset.qsBound = '1';
                sel.addEventListener('change', function () {
                    if (!inp.value || !window.jQuery) { return; }
                    try {
                        var $inp = window.jQuery(inp);
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
    </fieldset>

    <footer data-dialog-button>
        <?= \Studip\LinkButton::createCancel(_quorum('Abbrechen'), $cancelUrl) ?>
        <?= \Studip\Button::createAccept($mode === 'edit' ? _quorum('Speichern') : _quorum('Sammlung anlegen'), 'submit') ?>
    </footer>
</form>
