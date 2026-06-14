<?php

declare(strict_types=1);

namespace Quorum\Controllers;

use Quorum\Polls\Exceptions\InvalidResponseException;

/**
 * Safely reads an uploaded Quorum definition file (`.json`) — shared by
 * IndexController (course import) and WorkplaceController (workplace import).
 * Security measures:
 *   - canonical `is_uploaded_file()` defense (only genuine HTTP POST uploads,
 *     prevents injected tmp_name paths / LFI),
 *   - size limit (definitions are small),
 *   - a clear `InvalidResponseException` instead of a 500/stack trace on any problem.
 */
trait UploadDefinitionTrait
{
    private function readUploadedDefinition(string $field, int $maxBytes = 1024 * 1024): string
    {
        $upload = $_FILES[$field] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK) {
            throw new InvalidResponseException(
                _quorum('Bitte wählen Sie eine Quorum-Definitionsdatei (.json) zum Hochladen.')
            );
        }
        if ((int) ($upload['size'] ?? 0) > $maxBytes) {
            throw new InvalidResponseException(_quorum('Die Datei ist zu groß für eine Quorum-Definition.'));
        }
        $tmp = (string) ($upload['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidResponseException(_quorum('Der Datei-Upload ist ungültig.'));
        }
        return (string) @file_get_contents($tmp);
    }
}
