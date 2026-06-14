# Mitwirken an Quorum

Danke für dein Interesse, an Quorum mitzuarbeiten! Quorum ist ein Audience-Response-System (Software-Clicker) für Stud.IP 6: Lehrende starten Live-Abstimmungen, Studierende antworten anonym per QR-Code. Dieses Repository enthält das auslieferbare Plugin.

## Aufbau, Setup & Architektur

Projektstruktur, Voraussetzungen, Build-Befehle, Schichtenmodell und Datenfluss stehen **vollständig im Entwickler-Leitfaden** — damit es dafür nur eine Quelle gibt und nichts doppelt gepflegt wird:

- Deutsch: [`docs/developer/de/README.md`](docs/developer/de/README.md)
- English: [`docs/developer/en/README.md`](docs/developer/en/README.md)

Voraussetzungen: PHP ≥ 8.1 mit `ext-gettext`, Node.js ≥ 22, Composer, eine lauffähige Stud.IP-6-Instanz zum Testen.

Kurzfassung zum Loslegen: `composer install --no-dev && npm install && npm run build`. Nach Frontend-Änderungen `npm run build` nicht vergessen — `public/.vite/manifest.json` wird zur Laufzeit gelesen.

## Grundregeln

- **Code und Kommentare auf Englisch.**
- **Keine hartkodierten UI-Texte** — PHP über `_quorum('…')` (gettext-Domain `quorum`), Frontend über vue-i18n (`resources/vue/locales/de.json` + `en.json`, beide Sprachen gleich halten).
- **Keine externen Dienste oder CDNs** — alle Assets werden vom eigenen Server ausgeliefert.
- **Stud.IP-nativ bevorzugen** — vorhandene Stud.IP-Komponenten, -Klassen und -Muster nutzen, statt eigene nachzubauen.
- **Sicherheit serverseitig** — Eingaben validieren; mutierende Endpunkte mit CSRF- und Eigentümer-Prüfung; die anonyme Vote-API nutzt eine Same-Origin-Prüfung.
- **Barrierefrei & mobil** — ab 375 px Bildschirmbreite, per Tastatur und Screenreader bedienbar.

## Änderungen einbringen

1. Repository forken und einen Feature-Branch anlegen.
2. Änderung fokussiert halten; bei Frontend-Änderungen `npm run build` ausführen, damit `public/` den neuen Stand enthält.
3. Gegen eine Stud.IP-6-Instanz testen.
4. Pull Request öffnen — beschreibe **was** und **warum**.

## Lizenz

Quorum steht unter der **GNU GPLv3 mit Zusatzbedingungen gemäß Abschnitt 7** (siehe [`LICENSE`](LICENSE) und [`SUPPLEMENTAL-TERMS.txt`](SUPPLEMENTAL-TERMS.txt)). Mit deinem Beitrag stimmst du zu, dass er unter denselben Bedingungen lizenziert wird.
