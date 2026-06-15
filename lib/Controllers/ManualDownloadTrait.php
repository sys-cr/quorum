<?php

declare(strict_types=1);

namespace Quorum\Controllers;

use Quorum\Manual\ManualService;

/**
 * Delivers the Quorum manual as a Stud.IP PDF (TCPDF) for download. The language
 * follows the Stud.IP session, the scope follows the audience: teachers get the
 * complete manual, students only the "Teilnehmen" section.
 *
 * Requires a `$this->plugin` instance (PluginController) for the path to the
 * `docs/user` markdown sources.
 */
trait ManualDownloadTrait
{
    protected function sendManualPdf(string $audience): void
    {
        $lang = ManualService::langFromLocale((string) ($_SESSION['_language'] ?? $GLOBALS['_language'] ?? 'de_DE'));
        $service = new ManualService($this->plugin->getPluginPath() . '/docs/user');
        $html = $service->html($lang, $audience);

        $pdf = new \ExportPDF();
        $pdf->setHeaderTitle('Quorum');
        $pdf->addPage();
        $pdf->writeHTML($html);

        $base = $audience === ManualService::AUDIENCE_STUDENT
            ? 'quorum-teilnehmen'
            : 'quorum-anleitung';
        $pdf->dispatch($base . '-' . $lang);
    }
}
