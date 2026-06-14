<?php

declare(strict_types=1);

namespace Quorum;

/**
 * Adds two things to the Stud.IP helpbar (right-hand help panel):
 *
 *   1. Short context-dependent usage help for the current Quorum page.
 *   2. The visible attribution required by GPLv3 section 7
 *      (see SUPPLEMENTAL-TERMS.txt): author, source repository, license.
 *
 * The help texts are addressable per view: the context key is
 * `frame/action` (e.g. `workplace/new`, `course/import`). Unknown keys
 * fall back: first to the page-equivalent `workplace/…` text (course frame
 * and workplace share the form views), then to the frame base text
 * (`course`, `workplace`), finally to `general`.
 *
 * Bilingual (C-I18N): all texts go through the gettext domain `quorum`
 * (source language German, catalogs under `locale/<lang>/LC_MESSAGES/`).
 * The domain is bound lazily on first access; without a catalog or without
 * ext-gettext the German source string is returned unchanged.
 *
 * The texts (`helpFor`) are pure data and testable without Stud.IP; only
 * `addToHelpbar` touches the Stud.IP `Helpbar` and guards against its
 * absence (e.g. in CLI/test contexts).
 *
 * @author    Bodo Steffen
 * @copyright 2026 Bodo Steffen
 * @license   GPL-3.0-or-later WITH additional terms (see SUPPLEMENTAL-TERMS.txt)
 * @link      https://github.com/sys-cr/quorum
 */
final class AttributionHelper
{
    public const AUTHOR      = 'Bodo Steffen';
    public const REPOSITORY  = 'https://github.com/sys-cr/quorum';
    public const LICENSE_URL = 'https://www.gnu.org/licenses/gpl-3.0.html';

    /** gettext domain of the plugin (catalogs under `locale/`). */
    private const TEXTDOMAIN = 'quorum';

    private static bool $domainBound = false;

    /**
     * Translates via the plugin domain `quorum`; binds the domain to
     * `locale/` on first call. Without a catalog (or without ext-gettext)
     * the German source string is returned unchanged — also in tests.
     */
    private static function tr(string $text): string
    {
        if (!function_exists('dgettext')) {
            return $text;
        }
        if (!self::$domainBound) {
            bindtextdomain(self::TEXTDOMAIN, dirname(__DIR__) . '/locale');
            bind_textdomain_codeset(self::TEXTDOMAIN, 'UTF-8');
            self::$domainBound = true;
        }

        return dgettext(self::TEXTDOMAIN, $text);
    }

    /**
     * Short usage help per view (`frame/action`, e.g. `course/index`,
     * `workplace/compare`); see the class doc for the fallback chain.
     * Pure data — testable without Stud.IP.
     *
     * @return list<string>
     */
    public static function helpFor(string $context): array
    {
        $texts = self::texts();

        // Exact view key → page-equivalent workplace text
        // (`course/new` → `workplace/new`) → frame base text → general.
        $candidates = [$context];
        if (str_contains($context, '/')) {
            [$frame, $page] = explode('/', $context, 2);
            $candidates[] = 'workplace/' . $page;
            $candidates[] = $frame;
        }

        foreach ($candidates as $key) {
            if (isset($texts[$key])) {
                return $texts[$key];
            }
        }

        return $texts['general'];
    }

    /**
     * Help texts per view key. Translated at call time via `tr()` so the
     * language of the running Stud.IP session applies.
     *
     * @return array<string, list<string>>
     */
    private static function texts(): array
    {
        $course = [
            self::tr('Im Reiter „Quorum" verwalten Sie die Abstimmungen und Sammlungen dieser Veranstaltung vollständig: anlegen, starten und beenden, präsentieren, QR-Code teilen, Ergebnisse in Echtzeit verfolgen und Runden vergleichen (Peer Instruction). Über die Sidebar-Ansicht „Sammlungen" bündeln Sie mehrere Fragen.'),
            self::tr('Studierende stimmen anonym per QR-Code oder Kurz-URL ab — ohne eigenes Login. Alle Lehrenden der Veranstaltung (auch Co-Lehrende) dürfen deren Abstimmungen und Sammlungen steuern.'),
        ];
        $workplace = [
            self::tr('Am Arbeitsplatz verwalten Sie kursunabhängige Abstimmungen und Sammlungen — etwa für Gastvorlesungen oder als wiederverwendbare Vorlagen. Optional binden Sie eine Abstimmung an eine Veranstaltung.'),
            self::tr('Sie können Umfragen anlegen, importieren, herunterladen und in Sammlungen bündeln. Studierende stimmen anonym per QR-Code ab.'),
        ];

        return [
            'general' => [
                self::tr('Quorum ist ein Audience-Response-System: Lehrende stellen in Sekunden eine Live-Abstimmung, Studierende antworten anonym per QR-Code vom Smartphone.'),
            ],

            'course'          => $course,
            'course/index'    => $course,
            'workplace'       => $workplace,
            'workplace/index' => $workplace,

            'course/student' => [
                self::tr('In diesem Reiter sehen Sie die gerade laufenden Abstimmungen und Sammlungen dieser Veranstaltung und können direkt teilnehmen — anonym, ohne dass Ihr Name gespeichert wird.'),
                self::tr('Nach dem Ende einer Abstimmung sehen Sie hier die Ergebnisse, sofern die Lehrperson sie freigegeben hat. Bei Sammlungen folgt Ihr Gerät automatisch zur jeweils nächsten gestarteten Frage.'),
            ],
            'course/import' => [
                self::tr('Hier binden Sie eine Ihrer vorhandenen Umfragen in diese Veranstaltung ein: Die gewählte Umfrage wird mit der Veranstaltung verknüpft und erscheint danach im Reiter „Quorum".'),
            ],
            'workplace/new' => [
                self::tr('Hier legen Sie eine neue Umfrage an: Frage und Fragetyp wählen (Multiple Choice, Skala, Emoji, Freitext, Matrix), Antwortoptionen eintragen und optional ein Zeitlimit setzen.'),
                self::tr('Bei „Multiple Choice (eine Antwort)" lässt sich der Quiz-Modus mit Punkten und Leaderboard aktivieren. Optional binden Sie die Umfrage an eine Veranstaltung; gestartet wird sie danach aus der Übersicht.'),
            ],
            'workplace/edit' => [
                self::tr('Hier ändern Sie Fragetext und Antwortoptionen. Sobald die erste Antwort eingegangen ist, sind die Optionen gesperrt — Fragetext und Veranstaltungs-Bindung bleiben aber weiterhin änderbar.'),
            ],
            'workplace/archive' => [
                self::tr('Im Archiv liegen Ihre archivierten Umfragen und Sammlungen — auch geladene Demo-Inhalte landen hier. Sie können Einträge reaktivieren, ihre Ergebnisse erneut ansehen, herunterladen oder endgültig löschen.'),
            ],
            'workplace/results' => [
                self::tr('Diese Seite zeigt die Ergebnisse als Tabelle — Stimmen je Option, die Liste der Freitext-Antworten oder die Matrix-Auswertung. Das Ergebnis können Sie als CSV oder PDF herunterladen.'),
                self::tr('Bei Freitext-Fragen entfernen Sie einzelne unpassende Antworten direkt hier; sie verschwinden dann auch aus der Ergebnisanzeige auf dem Beamer.'),
            ],
            'workplace/load_demo' => [
                self::tr('Hier laden Sie Beispiel-Inhalte in Ihr Archiv: je eine Umfrage pro Fragetyp und eine Demo-Sammlung, alle bereits mit Antworten. Zum Ausprobieren — reaktivieren, kopieren oder als Vorlage umbauen.'),
            ],
            'workplace/restart' => [
                self::tr('Hier starten Sie dieselbe Frage als neue Runde — die bisherigen Ergebnisse bleiben erhalten. So lassen Sie z. B. nach einer Diskussionsphase erneut abstimmen und vergleichen die Runden anschließend (Peer Instruction).'),
            ],
            'workplace/import_file' => [
                self::tr('Hier laden Sie eine zuvor heruntergeladene Quorum-Definitionsdatei (.json) hoch. Die Umfrage wird neu angelegt und startet ohne bereits abgegebene Antworten.'),
            ],
            'workplace/import_collection' => [
                self::tr('Hier importieren Sie eine komplette Sammlung aus einer Definitionsdatei (.json) — alle enthaltenen Umfragen werden neu angelegt.'),
            ],
            'workplace/collections' => [
                self::tr('Sammlungen bündeln mehrere Umfragen, z. B. für eine Vorlesungssitzung. Aus einer Sammlung heraus starten Sie den Presenter-Modus, der die Fragen nacheinander auf dem Beamer zeigt.'),
            ],
            'workplace/collection' => [
                self::tr('Diese Ansicht zeigt die Umfragen einer Sammlung: Reihenfolge ändern, neue Umfragen direkt hier anlegen oder bestehende hinzufügen und entfernen.'),
                self::tr('Das Voting starten Sie für alle Fragen zugleich (Studierende klicken sich selbst durch) oder Frage für Frage über den Presenter-Modus.'),
            ],
            'workplace/collection_new' => [
                self::tr('Hier legen Sie eine neue Sammlung an. Der Name erscheint später als Titel im Presenter-Modus. Optional binden Sie die Sammlung an eine Veranstaltung — im Kurs-Reiter geschieht das automatisch; dann erscheint die Sammlung dort für Lehrende und Studierende.'),
            ],
            'workplace/collection_edit' => [
                self::tr('Hier ändern Sie Name, Beschreibung und die Veranstaltungs-Bindung der Sammlung. Der Name erscheint als Titel im Presenter-Modus. Die Kursbindung der Sammlung ist unabhängig von der Zuordnung ihrer einzelnen Fragen.'),
            ],
            'workplace/collection_assign' => [
                self::tr('Hier wählen Sie, in welche Sammlung diese Umfrage aufgenommen werden soll.'),
            ],
            'workplace/compare' => [
                self::tr('Der Vergleich stellt die Runden einer Frage nebeneinander — so sehen Sie, wie sich die Antworten z. B. nach einer Diskussionsphase verändert haben (Peer Instruction).'),
            ],
        ];
    }

    /**
     * Mandatory attribution text per GPLv3 section 7 (see SUPPLEMENTAL-TERMS.txt).
     */
    public static function attributionText(): string
    {
        return sprintf(
            self::tr('Quorum wurde von %s entwickelt und steht unter der GNU GPLv3 mit Zusatzbedingungen gemäß Abschnitt 7. Der Quellcode ist öffentlich zugänglich.'),
            self::AUTHOR
        );
    }

    /**
     * Demo onboarding as ONE HTML block: heading, explanation and load link
     * belong together (a single helpbar entry, no separator between them).
     * `$demoUrl` is already HTML-safe (PluginEngine::getURL htmlReady) and
     * `$icon` is rendered icon markup — both inserted raw; only the visible
     * texts are escaped. Plain HTML so it stays testable.
     */
    public static function demoHtml(string $demoUrl, string $icon = ''): string
    {
        $esc      = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $iconHtml = $icon !== '' ? $icon . ' ' : '';

        return sprintf(
            '<div class="quorum-demo-onboarding">'
            . '<strong>%s</strong>'
            . '<p>%s</p>'
            . '<a href="%s">%s%s</a>'
            . '</div>',
            $esc(self::tr('Quorum ausprobieren')),
            $esc(self::tr('Laden Sie Beispiel-Inhalte (eine Umfrage je Fragetyp und eine Sammlung, '
                . 'mit Antworten) in Ihr Archiv — zum Anschauen, Reaktivieren und Umbauen.')),
            $demoUrl,
            $iconHtml,
            $esc(self::tr('Demo-Inhalte laden'))
        );
    }

    /**
     * Adds the demo onboarding as ONE helpbar widget (a single `<li>`, i.e. no
     * separator between heading, text and link). The icon uses the light
     * info-alt variant — otherwise there is no contrast on the coloured ground.
     *
     * @param mixed $helpbar Stud.IP `Helpbar` instance.
     */
    private static function addDemoWidget($helpbar, string $demoUrl): void
    {
        if (!class_exists('HelpbarWidget') || !class_exists('WidgetElement')) {
            return;
        }
        $icon = class_exists('Icon')
            ? \Icon::create('vote', \Icon::ROLE_INFO_ALT)
                ->asImg(20, ['style' => 'vertical-align:text-bottom;', 'alt' => ''])
            : '';
        $widget = new \HelpbarWidget();
        $widget->addElement(new \WidgetElement(self::demoHtml($demoUrl, $icon)));
        $helpbar->addWidget($widget);
    }

    /**
     * Attribution footer as ONE HTML block: the mandatory notice (GPLv3 §7) plus
     * the license and source links on a single line. Deliberately small and
     * dimmed — clearly less prominent than the help text and Stud.IP's own
     * "Weiterführende Hilfe". Plain HTML with no Stud.IP dependency (own
     * escaping) so it stays testable.
     */
    public static function attributionHtml(string $linkIcon = ''): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        // `$linkIcon` is already rendered Stud.IP icon markup (a trusted `<img>`)
        // — do not escape it, otherwise the tag would show up as text.
        $icon = $linkIcon !== '' ? $linkIcon . ' ' : '';

        return sprintf(
            '<div class="quorum-attribution" style="font-size:0.78em;line-height:1.45;opacity:0.65;">'
            . '%s<br>'
            . '<a href="%s" target="_blank" rel="noopener noreferrer" style="color:inherit;">%s%s</a>'
            . ' · '
            . '<a href="%s" target="_blank" rel="noopener noreferrer" style="color:inherit;">%s%s</a>'
            . '</div>',
            $esc(self::attributionText()),
            $esc(self::LICENSE_URL), $icon, $esc(self::tr('Lizenz (GPLv3)')),
            $esc(self::REPOSITORY),  $icon, $esc(self::tr('Quellcode (GitHub)'))
        );
    }

    /** Guards against registering the observer twice per request. */
    private static bool $attributionQueued = false;

    /**
     * Appends context help and the demo entry point to the Stud.IP helpbar. The
     * GPLv3 section 7 attribution is NOT added here but moved to the very end of
     * the tab via `HelpbarWillRender` (see `onHelpbarWillRender`) — after
     * Stud.IP's own "Weiterführende Hilfe". No-op without `Helpbar` (CLI/test).
     *
     * `$demoUrl` (optional): when passed (workplace only), the helpbar shows
     * an entry point for loading the demo content.
     * `$manualUrl` (optional): download link of the complete manual (PDF).
     */
    public static function addToHelpbar(string $context = 'general', ?string $demoUrl = null, ?string $manualUrl = null): void
    {
        if (!class_exists('Helpbar')) {
            return;
        }
        $helpbar = \Helpbar::get();

        foreach (self::helpFor($context) as $text) {
            $helpbar->addPlainText('', $text);
        }

        // Download the complete manual as PDF (session language). Light info-alt
        // icon variant — otherwise there is no contrast on the coloured ground.
        if ($manualUrl !== null) {
            $dlIcon = class_exists('Icon') ? \Icon::create('download', \Icon::ROLE_INFO_ALT) : null;
            $helpbar->addLink(self::tr('Anleitung herunterladen'), $manualUrl, $dlIcon);
        }

        // Onboarding: example content loadable straight from the helpbar — one
        // poll per question type + one collection (with responses) in the own
        // archive. Only shown where a load URL is passed (workplace).
        if ($demoUrl !== null) {
            self::addDemoWidget($helpbar, $demoUrl);
        }

        // Attach the attribution only just before rendering — then it really
        // comes last, after the entries the core appends afterwards.
        if (class_exists('NotificationCenter')) {
            if (!self::$attributionQueued) {
                self::$attributionQueued = true;
                \NotificationCenter::addObserver(self::class, 'onHelpbarWillRender', 'HelpbarWillRender');
            }
        } else {
            self::addAttributionWidget($helpbar);
        }
    }

    /**
     * Observer for `HelpbarWillRender` (static, via `call_user_func`): appends
     * the attribution block as the last helpbar entry.
     *
     * @param mixed $helpbar The `Helpbar` subject of the notification.
     */
    public static function onHelpbarWillRender(string $event, $helpbar, mixed $userData = null): void
    {
        self::addAttributionWidget($helpbar);
    }

    /**
     * Adds the attribution HTML as ONE helpbar widget (a single `<li>`, i.e.
     * without separator lines between the text and the links).
     *
     * @param mixed $helpbar Stud.IP `Helpbar` instance.
     */
    private static function addAttributionWidget($helpbar): void
    {
        if (!class_exists('HelpbarWidget') || !class_exists('WidgetElement')) {
            return;
        }
        // Small external-link icon before both links (Stud.IP convention); on the
        // coloured helpbar background the light info-alt variant.
        $linkIcon = class_exists('Icon')
            ? \Icon::create('link-extern', \Icon::ROLE_INFO_ALT)
                ->asImg(16, ['style' => 'vertical-align:text-bottom;', 'alt' => ''])
            : '';
        $widget = new \HelpbarWidget();
        $widget->addElement(new \WidgetElement(self::attributionHtml($linkIcon)));
        $helpbar->addWidget($widget);
    }
}
