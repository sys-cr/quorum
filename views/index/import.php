<?php

declare(strict_types=1);

/**
 * "Import survey" — lists the user's own surveys that are not in the current
 * course (course-independent or in another course) and offers an import action
 * per row (binds `seminar_id` to this course). Renders in the course frame.
 *
 * @var list<\Quorum\Polls\PollSummary> $importable
 * @var string $cid
 * @var string $csrf       ready-made hidden <input> tag (CSRFProtection::tokenTag)
 * @var string $pluginUrl  Trails base (absolute)
 */
?>
<?php if (empty($importable)): ?>
    <?= MessageBox::info(_quorum(
        'Es gibt keine weiteren eigenen Umfragen, die Sie in diesen Kurs einbinden könnten. '
        . 'Legen Sie über „Neue Umfrage anlegen" eine neue an.'
    )) ?>
<?php else: ?>
    <p>
        <?= _quorum('Wählen Sie eine Ihrer Umfragen, um sie in diesen Kurs zu übernehmen. '
            . 'Eine bereits einem anderen Kurs zugeordnete Umfrage wird dabei in diesen Kurs verschoben.') ?>
    </p>
    <table class="default">
        <caption><?= htmlspecialchars(_quorum('Eigene Umfragen außerhalb dieses Kurses'), ENT_QUOTES) ?></caption>
        <colgroup>
            <col style="width: 48%">
            <col style="width: 24%">
            <col style="width: 12%">
            <col style="width: 16%">
        </colgroup>
        <thead>
            <tr>
                <th scope="col"><?= htmlspecialchars(_quorum('Frage'), ENT_QUOTES) ?></th>
                <th scope="col"><?= htmlspecialchars(_quorum('Bisherige Zuordnung'), ENT_QUOTES) ?></th>
                <th scope="col"><?= htmlspecialchars(_quorum('Antworten'), ENT_QUOTES) ?></th>
                <th scope="col"><?= htmlspecialchars(_quorum('Aktion'), ENT_QUOTES) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($importable as $poll): ?>
                <tr>
                    <td><?= htmlspecialchars($poll->question, ENT_QUOTES) ?></td>
                    <td>
                        <?= $poll->seminarId === null
                            ? '<em>' . htmlspecialchars(_quorum('kursunabhängig'), ENT_QUOTES) . '</em>'
                            : htmlspecialchars($poll->seminarName, ENT_QUOTES) ?>
                    </td>
                    <td><?= (int) $poll->responseCount ?></td>
                    <td>
                        <form
                            action="<?= htmlspecialchars(
                                rtrim($pluginUrl, '/') . '/index/import_submit/' . urlencode($poll->id)
                                . '?cid=' . urlencode($cid),
                                ENT_QUOTES
                            ) ?>"
                            method="post"
                            style="display: inline;"
                        >
                            <?= $csrf ?>
                            <?= \Studip\Button::createAccept(_quorum('Einbinden'), 'submit') ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
