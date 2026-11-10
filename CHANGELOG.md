# Changelog

[Deutsch](#deutsch) | [English](#english)

---

## Deutsch

Alle nennenswerten Änderungen an Bestandio werden in dieser Datei festgehalten.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/). Die aktuelle Version steht in der Datei `VERSION` im Wurzelverzeichnis; das Web-Panel zeigt sie in der Fußzeile, der Scanner gibt sie mit `--version` aus.

### [Unreleased]

#### Hinzugefügt

- Handy wie eine App: feste Leiste unten (Geräte · Scan · Neu · Mehr) mit großem runden Scan-Knopf, „Neu“ nur für Bearbeiter/Admin, „Mehr“ mit den übrigen Seiten, Sprache und Abmelden; Kopfzeile mit Logo und Seitentitel; Abstand zur iPhone-Home-Leiste; Tippflächen ab 44 px, Eingabeschrift ab 16 px. Die PC-Ansicht bleibt unverändert.
- Geräteseite auf dem Handy in Abschnitten: Übersicht, Hardware, Programme, Verlauf.
- Installierbar als App (PWA): Manifest, Symbole 192/512 und maskable, Apple-Symbol. Der Service Worker speichert nur statische Dateien unter `assets/`, nie Seiten oder Gerätedaten. CSP nur um `worker-src 'self'` und `manifest-src 'self'` erweitert. Installation braucht HTTPS.
- Etiketten-Startplatz: Beim Druck auf A4-Bogen zeigt ein Bild des Bogens (nach eingestelltem Raster) die Felder; Antippen/Klicken/Enter wählt den Startplatz, Felder davor sind grau durchgestrichen. Zusätzlich das Feld „Start bei Etikett Nr.“. Gilt nur für den jeweiligen Druck.
- Etiketten-Vorlagen in der Datenbank (Migration 006): Name, Art (Einzeletikett/A4-Bogen), Breite, Höhe, dpi, Hoch-/Querformat, beim Bogen Spalten, Zeilen, Ränder, Abstände. Admins legen an, ändern, kopieren, löschen; je Art eine Standard-Vorlage (von der Datenbank erzwungen). Mitgeliefert und nicht lösch- oder änderbar: Einzel 50 × 25, 57 × 32, 54 × 101 (quer), 102 × 152, 103 × 199 mm; A4 3 × 8 mit 70 × 36 mm.
- Druckseite: Vorlage direkt wählbar (natives Auswahlfeld, vorbelegt mit dem Standard); das Startplatz-Bild folgt dem Raster der gewählten A4-Vorlage. Alte Links mit `mode=single` nutzen die Standard-Einzelvorlage.
- Handy: weicher Seitenwechsel (View Transitions, nur CSS; Kopfzeile und Leiste bleiben stehen; aus bei reduzierter Bewegung). Tipp auf den aktiven Punkt der Leiste lädt nicht neu, sondern scrollt nach oben. Warnung beim Verlassen von Formularen mit ungespeicherten Änderungen (nicht beim Absenden).

#### Geändert

- Einstellungen: Die Etikettenmaße (A4-Raster, Einzeletikett, dpi) sind in die Etiketten-Vorlagen umgezogen; die bisherige Einstellung wird bei der Migration als eigene Standard-Vorlage übernommen. Die Einstellungsseite enthält nur noch den Firmennamen.
- Tests: Übersprungene DB-Tests werden gezählt und angezeigt (`x/y passed, n failed, m skipped`); DB-Tests laufen nur gegen Datenbanken mit „test“ im Namen.
- Kamera-Scan: Das Kamerabild füllt den Bereich zwischen Kopfzeile und unterer Leiste, mit Rahmen und Scan-Linie. Erkannt wird nur, was im Rahmen liegt; vorher wurde das ganze Kamerabild ausgewertet, auch abgeschnittene Teile, und Codes wurden erkannt, bevor sie sichtbar in der Mitte waren. Oben links ✕ zum Beenden, oben rechts ⋯ für die anderen Wege (Foto, Code eingeben). Die Kamera startet weiterhin erst über „Kamera starten“.

#### Behoben

- Handy: Kein hängender Hover-Zustand mehr nach Tippen (Hover-Stile nur bei Maus/Trackpad), kein graues Aufblitzen; dezenter eigener Druck-Zustand.
- Handy: Seitentitel nur einmal sichtbar (in der Kopfzeile); die Überschrift bleibt für Screenreader erhalten.
- Handy-Leiste: Der runde Scan-Knopf sah auf allen Seiten ausgewählt aus. Jetzt ist er nur auf der Scan-Seite gefüllt, sonst hell mit türkisem Rand.
- „Mehr“ auf dem Handy: Schrift war winzig, der Scan-Knopf ragte ins Menü, der Hintergrund war nicht abgedunkelt.
- Seitenwechsel auf dem Handy: Die untere Leiste kommt jetzt gleich am Anfang der Seite statt am Ende, die springende Fußzeile ist auf dem Handy ausgeblendet (Version steht unter „Mehr“).

### [0.1.0] - 2026-11-10

Erste Veröffentlichung.

#### Added

**Einrichtung und Betrieb**

- Einrichtungsassistent im Browser (`/setup`), danach gesperrt; lehnt zu alte PHP- und Datenbankversionen ab
- Voraussetzungen: PHP >= 8.3 (`pdo_mysql`, `mbstring`, `openssl`, `fileinfo`), MySQL >= 8.4 oder MariaDB >= 10.11 (`utf8mb4`), Apache (`mod_rewrite`) oder nginx + PHP-FPM, HTTPS
- Kommandozeile: `php bin/bestandio migrate | status | sample-data [--force]`
- Beispieldaten (30 Geräte) nur über `php bin/bestandio sample-data`; setzt ein abgeschlossenes Setup voraus, verweigert bei vorhandenen Geräten außer mit `--force`
- Versionsnummer an einer Stelle (`VERSION`), Anzeige in der Fußzeile

**Anmeldung und Sicherheit**

- Login mit `password_hash`, Passwörter mind. 12 Zeichen
- CSRF-Schutz bei jeder Änderung
- Sitzungen enden nach 2 Stunden ohne Aktivität, spätestens nach 12 Stunden
- Login-Drosselung: 5 Fehlversuche je Benutzer bzw. 20 je IP innerhalb von 15 Minuten
- Rollen Admin / Editor / Nur lesen, serverseitig geprüft
- Strikte Content-Security-Policy ohne `unsafe-inline`

**Geräte**

- Felder: Inventarnummer (eindeutig), Kategorie, Hersteller, Modell, Seriennummer, Standort, Status (in Benutzung / auf Lager / in Reparatur / ausgemustert), Kaufdatum, Garantie, Notiz
- Ausmustern statt Löschen
- Kategorien und Standorte als Listen (Umbenennen direkt in der Liste, Löschen nur wenn unbenutzt)
- Warnung bei doppelter Seriennummer
- Keine personenbezogenen Daten an Geräten
- Liste mit Suche, Filtern, Sortierung und Seitenaufteilung
- CSV-Export (Trennzeichen `;` für DE/TR, `,` für EN; Schutz gegen Formel-Injection)
- Änderungsverlauf je Feld

**Gerätecode, QR und Etiketten**

- Fester 10-stelliger Gerätecode (`random_int`, Alphabet `23456789ABCDEFGHJKMNPQRSTUVWXYZ`)
- Eigener QR-Encoder (ISO/IEC 18004, Fehlerkorrektur Q, SVG)
- Etikettendruck: A4-Bogen mit Raster in mm; Einzeletiketten mit Vorlagen DYMO 54 × 101 mm @ 300 dpi, Zebra 103 × 199 mm @ 203 dpi oder eigener Größe; QR-Module auf ganze Druckerpunkte gerundet
- Einstellungsseite für Admins

**Scannen**

- Scan-Seite `/scan`: `BarcodeDetector` des Browsers, sonst jsQR 1.4.0; Kamera nur unter HTTPS oder auf `localhost`; Foto oder manuelle Eingabe als Alternative

**Hardware-Import und Windows-Scanner**

- Import von Hardware-Scans im JSON-Format `bestandio-scan` Version 1 (siehe [docs/import-format.md](docs/import-format.md)) mit Vorschau und Bestätigung
- Zuordnung über Hersteller + Seriennummer, danach über die SMBIOS-UUID
- Softwareliste je Gerät
- Windows-Scanner `bestandio-scanner.exe` (Go, nur Standardbibliothek): keine Installation, keine Admin-Rechte, kein Netzwerk; erfasst nie Benutzer- oder Rechnername, Domäne, IP- oder MAC-Adresse; Optionen `--software`, `--out`, `--no-clipboard`, `--lang`, `--version`

**Oberfläche**

- Sprachen Deutsch, Englisch, Türkisch; alle Texte ausschließlich in `lang/i18n.json`
- Hell/Dunkel, Mobile-first

#### Known limitations

- nginx + PHP-FPM unter Linux nicht getestet; Apache unter Linux nicht getestet. Vollständig getestet (Einrichtung und Ende-zu-Ende) wurden: nginx 1.30 + PHP 8.3 (FastCGI) + MariaDB 10.11 unter Windows; Apache 2.4.58 + PHP 8.3 (FastCGI) + MariaDB 10.11 unter Windows; eingebauter Server von PHP 8.5 + MySQL 8.4.
- Unter Linux (Ubuntu 26.04, PHP 8.5.4, MySQL 8.4.11) mit dem eingebauten PHP-Server getestet: Einrichtung über `/setup`, Unit- und DB-Tests, volle Ende-zu-Ende-Suite, `./bin/bestandio migrate` und `sample-data`, Sperre aller Pfade außerhalb von `public/`, Groß-/Kleinschreibung der Dateipfade, Zeilenenden. nginx + PHP-FPM und Apache sind unter Linux weiterhin nicht getestet.
- TLS und HTTP/2 wurden lokal nicht getestet.
- Der Windows-Scanner wurde nur auf einem einzigen, selbst gebauten PC getestet.
- Der Etikettendruck wurde nur über PDF-Ausgabe getestet, nicht auf echten Etikettendruckern.
- Die .exe ist nicht signiert; Windows SmartScreen zeigt beim ersten Start eine Warnung.

---

## English

All notable changes to Bestandio are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/). The current version is stored in the file `VERSION` in the repository root; the web panel shows it in the footer, the scanner prints it with `--version`.

### [Unreleased]

#### Added

- Phone app layout: fixed bottom bar (Devices · Scan · New · More) with a large round scan button, "New" for editors/admins only, "More" with the other pages, language and sign-out; header with logo and page title; safe-area spacing for the iPhone home indicator; tap targets of at least 44 px, form text of at least 16 px. The desktop view is unchanged.
- Device page on phones in sections: overview, hardware, programs, history.
- Installable as an app (PWA): manifest, icons 192/512 and maskable, Apple touch icon. The service worker caches only static files below `assets/`, never pages or device data. CSP extended only by `worker-src 'self'` and `manifest-src 'self'`. Installing requires HTTPS.
- Label start position: when printing on an A4 sheet, a picture of the sheet (from the configured grid) shows the positions; tap/click/Enter selects the start position, positions before it are greyed out and struck through. Plus a "Start at label no." field. Applies to the current print only.
- Label templates in the database (migration 006): name, kind (single label/A4 sheet), width, height, dpi, portrait/landscape, for sheets columns, rows, margins, gaps. Admins create, change, copy and delete them; one default per kind (enforced by the database). Built in and not deletable or changeable: single 50 × 25, 57 × 32, 54 × 101 (landscape), 102 × 152, 103 × 199 mm; A4 3 × 8 with 70 × 36 mm.
- Print page: the template is chosen right there (native select, preset to the default); the start position picture follows the grid of the chosen A4 template. Old links with `mode=single` use the default single-label template.
- Phones: smooth page changes (View Transitions, CSS only; header and bar stay in place; off with reduced motion). Tapping the active bar item does not reload but scrolls to the top. Warning when leaving forms with unsaved changes (not when submitting).

#### Changed

- Settings: the label sizes (A4 grid, single label, dpi) moved to the label templates; the previous setting becomes an own default template during the migration. The settings page now only holds the company name.
- Tests: skipped DB tests are counted and shown (`x/y passed, n failed, m skipped`); DB tests only run against databases with "test" in their name.
- Camera scan: the camera view fills the area between header and bottom bar, with a frame and a scan line. Only what is inside the frame is read; before, the whole camera image was analysed, including cropped parts, and codes were detected before they were visibly in the middle. ✕ top left to stop, ⋯ top right for the other ways (photo, enter code). The camera still only starts via "Start camera".

#### Fixed

- Phones: no sticking hover state after a tap (hover styles only with mouse/trackpad), no grey flash; a subtle own pressed state.
- Phones: the page title is shown only once (in the header); the heading stays available to screen readers.
- Phone bar: the round scan button looked selected on every page. It is now filled only on the scan page, otherwise light with a lagoon ring.
- "More" on phones: tiny text, the scan button overlapped the menu, the background was not dimmed.
- Page changes on phones: the bottom bar now comes at the start of the page instead of the end; the jumping footer is hidden on phones (the version is shown under "More").

### [0.1.0] - 2026-11-10

First release.

#### Added

**Setup and operation**

- Browser-based setup wizard (`/setup`), locked afterwards; rejects PHP and database versions that are too old
- Requirements: PHP >= 8.3 (`pdo_mysql`, `mbstring`, `openssl`, `fileinfo`), MySQL >= 8.4 or MariaDB >= 10.11 (`utf8mb4`), Apache (`mod_rewrite`) or nginx + PHP-FPM, HTTPS
- Command line: `php bin/bestandio migrate | status | sample-data [--force]`
- Sample data (30 devices) only via `php bin/bestandio sample-data`; requires a completed setup, refuses if devices exist unless `--force` is given
- Version number in one place (`VERSION`), shown in the footer

**Login and security**

- Login with `password_hash`, passwords min. 12 characters
- CSRF protection on every change
- Sessions end after 2 hours of inactivity, at the latest after 12 hours
- Login throttling: 5 failed attempts per user or 20 per IP within 15 minutes
- Roles Admin / Editor / Read-only, checked server-side
- Strict Content-Security-Policy without `unsafe-inline`

**Devices**

- Fields: inventory number (unique), category, manufacturer, model, serial number, location, status (in use / in stock / in repair / retired), purchase date, warranty, note
- Retire instead of delete
- Categories and locations as lists (rename inline, delete only when unused)
- Warning on duplicate serial number
- No personal data on devices
- List with search, filters, sorting and paging
- CSV export (separator `;` for DE/TR, `,` for EN; formula injection protection)
- Per-field change history

**Device code, QR and labels**

- Fixed 10-character device code (`random_int`, alphabet `23456789ABCDEFGHJKMNPQRSTUVWXYZ`)
- Own QR encoder (ISO/IEC 18004, error correction level Q, SVG)
- Label printing: A4 sheet with a grid in mm; single labels with templates DYMO 54 × 101 mm @ 300 dpi, Zebra 103 × 199 mm @ 203 dpi or a custom size; QR modules rounded to whole printer dots
- Settings page for admins

**Scanning**

- Scan page `/scan`: the browser's `BarcodeDetector`, otherwise jsQR 1.4.0; camera only on HTTPS or `localhost`; photo or manual entry as alternatives

**Hardware import and Windows scanner**

- Import of hardware scans in the JSON format `bestandio-scan` version 1 (see [docs/import-format.md](docs/import-format.md)) with preview and confirmation
- Matching by manufacturer + serial number, then by SMBIOS UUID
- Software list per device
- Windows scanner `bestandio-scanner.exe` (Go, standard library only): no installation, no admin rights, no network; never records user or computer name, domain, IP or MAC address; options `--software`, `--out`, `--no-clipboard`, `--lang`, `--version`

**User interface**

- Languages German, English, Turkish; all texts only in `lang/i18n.json`
- Light/dark, mobile-first

#### Known limitations

- nginx + PHP-FPM on Linux not tested; Apache on Linux not tested. Fully tested (setup and end-to-end): nginx 1.30 + PHP 8.3 (FastCGI) + MariaDB 10.11 on Windows; Apache 2.4.58 + PHP 8.3 (FastCGI) + MariaDB 10.11 on Windows; PHP 8.5 built-in server + MySQL 8.4.
- Tested on Linux (Ubuntu 26.04, PHP 8.5.4, MySQL 8.4.11) with the PHP built-in server: setup via `/setup`, unit and database tests, the full end-to-end suite, `./bin/bestandio migrate` and `sample-data`, blocking of all paths outside `public/`, case of file paths, line endings. nginx + PHP-FPM and Apache on Linux are still not tested.
- TLS and HTTP/2 have not been tested locally.
- The Windows scanner has been tested on a single self-built PC only.
- Label printing has been tested via PDF output only, not on physical label printers.
- The .exe is not signed; Windows SmartScreen shows a warning on first start.
