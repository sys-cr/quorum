<?php

declare(strict_types=1);

/**
 * "Import survey" — upload form for a previously downloaded Quorum definition
 * file (`.json`). Shared by course (index/import_file → binds to the course)
 * and workplace (workplace/import_file → course-independent). The imported
 * survey is created fresh (0 responses).
 *
 * @var string $csrf       ready-made hidden <input> tag (CSRFProtection::tokenTag)
 * @var string $actionUrl  POST target (index/ or workplace/import_file_submit)
 */
?>
<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>" method="post" enctype="multipart/form-data" class="default">
    <?= $csrf ?>

    <fieldset>
        <legend><?= htmlspecialchars(_quorum('Umfrage importieren'), ENT_QUOTES) ?></legend>

        <p>
            <?= _quorum('Laden Sie eine Quorum-Definitionsdatei (.json) hoch, die zuvor über '
                . '„Herunterladen" exportiert wurde. Die Umfrage wird frisch angelegt — '
                . 'ohne Antworten.') ?>
        </p>

        <label for="quorum-import-file">
            <?= htmlspecialchars(_quorum('Definitionsdatei (.json)'), ENT_QUOTES) ?>
        </label>
        <input
            type="file"
            id="quorum-import-file"
            name="definition"
            accept="application/json,.json"
            required
        >
    </fieldset>

    <footer class="button-group">
        <?= \Studip\Button::createAccept(_quorum('Importieren'), 'submit') ?>
    </footer>
</form>
