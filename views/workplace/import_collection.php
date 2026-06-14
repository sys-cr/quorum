<?php

declare(strict_types=1);

/**
 * "Import collection" — upload form for a previously downloaded Quorum
 * collection definition (`.json`). The collection and all member surveys are
 * created fresh (course-independent). Renders in the workplace frame.
 *
 * @var string $csrf       ready-made hidden <input> tag (CSRFProtection::tokenTag)
 * @var string $actionUrl  POST target (workplace/import_collection_submit)
 */
?>
<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>" method="post" enctype="multipart/form-data" class="default">
    <?= $csrf ?>

    <fieldset>
        <legend><?= htmlspecialchars(_quorum('Sammlung importieren'), ENT_QUOTES) ?></legend>

        <p>
            <?= _quorum('Laden Sie eine Quorum-Sammlungs-Definition (.json) hoch, die zuvor über '
                . '„Herunterladen" exportiert wurde. Die Sammlung und alle enthaltenen '
                . 'Umfragen werden frisch angelegt — ohne Antworten, in der gespeicherten '
                . 'Reihenfolge.') ?>
        </p>

        <label for="quorum-import-collection">
            <?= htmlspecialchars(_quorum('Sammlungs-Datei (.json)'), ENT_QUOTES) ?>
        </label>
        <input
            type="file"
            id="quorum-import-collection"
            name="definition"
            accept="application/json,.json"
            required
        >
    </fieldset>

    <footer class="button-group">
        <?= \Studip\Button::createAccept(_quorum('Importieren'), 'submit') ?>
    </footer>
</form>
