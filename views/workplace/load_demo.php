<?php

declare(strict_types=1);

/**
 * "Load demo content" — confirmation page. Explains what gets created and
 * triggers (via POST) the seeding of the example content into the own
 * archive (showcase material, can be reactivated and remodeled).
 *
 * @var string $csrf       ready-made hidden <input> tag (CSRFProtection::tokenTag)
 * @var string $actionUrl  POST target (workplace/load_demo_submit)
 * @var string $cancelUrl  cancel target (archive)
 */
?>
<form action="<?= htmlspecialchars($actionUrl, ENT_QUOTES) ?>" method="post" class="default">
    <?= $csrf ?>

    <fieldset>
        <legend><?= htmlspecialchars(_quorum('Demo-Inhalte laden'), ENT_QUOTES) ?></legend>

        <p>
            <?= _quorum('Quorum legt Ihnen Beispiel-Inhalte in Ihr Archiv — je eine Umfrage '
                . 'pro Fragetyp (Multiple Choice, Skala, Emoji, Freitext, Matrix) sowie eine '
                . 'Demo-Sammlung, alle bereits mit Beispiel-Antworten.') ?>
        </p>
        <p>
            <?= _quorum('So sehen Sie auf einen Blick, was Quorum kann, ohne selbst etwas '
                . 'anlegen zu müssen. Die Inhalte liegen im Archiv und lassen sich jederzeit '
                . 'reaktivieren, kopieren oder als Vorlage umbauen.') ?>
        </p>
    </fieldset>

    <footer class="button-group">
        <?= \Studip\Button::createAccept(_quorum('Demo-Inhalte laden'), 'submit') ?>
        <?= \Studip\LinkButton::createCancel(_quorum('Abbrechen'), $cancelUrl) ?>
    </footer>
</form>
