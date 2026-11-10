# Changelog

[Deutsch](#deutsch) | [English](#english)

---

## Deutsch

Alle nennenswerten Änderungen an Bestandio werden in dieser Datei festgehalten.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/). Die aktuelle Version steht in der Datei `VERSION` im Wurzelverzeichnis; das Web-Panel zeigt sie in der Fußzeile, der Scanner gibt sie mit `--version` aus.

### [Unreleased]

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
- Unter Linux (Ubuntu 26.04, PHP 8.5, eingebauter Server) nur ohne Datenbank geprüft: Unit-Tests, Sperre aller Pfade außerhalb von `public/`, Setup-Seite, Groß-/Kleinschreibung der Dateipfade, Zeilenenden, ausführbares `bin/bestandio`. Einrichtung über `/setup`, Ende-zu-Ende-Suite, `migrate` und `sample-data` unter Linux stehen noch aus.
- TLS und HTTP/2 wurden lokal nicht getestet.
- Der Windows-Scanner wurde nur auf einem einzigen, selbst gebauten PC getestet.
- Der Etikettendruck wurde nur über PDF-Ausgabe getestet, nicht auf echten Etikettendruckern.
- Die .exe ist nicht signiert; Windows SmartScreen zeigt beim ersten Start eine Warnung.

---

## English

All notable changes to Bestandio are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/). The current version is stored in the file `VERSION` in the repository root; the web panel shows it in the footer, the scanner prints it with `--version`.

### [Unreleased]

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
- On Linux (Ubuntu 26.04, PHP 8.5, built-in server) only checked without a database: unit tests, blocking of all paths outside `public/`, setup page, case of file paths, line endings, executable `bin/bestandio`. Setup via `/setup`, the end-to-end suite, `migrate` and `sample-data` on Linux are still pending.
- TLS and HTTP/2 have not been tested locally.
- The Windows scanner has been tested on a single self-built PC only.
- Label printing has been tested via PDF output only, not on physical label printers.
- The .exe is not signed; Windows SmartScreen shows a warning on first start.
