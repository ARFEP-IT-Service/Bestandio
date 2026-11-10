# Contributing / Mitwirken

[Deutsch](#deutsch) | [English](#english)

---

## Deutsch

Danke für Ihr Interesse an Bestandio. Beiträge sind willkommen – bitte vorher diese Regeln lesen. Sicherheitslücken bitte **nicht** als Issue oder Pull Request melden, sondern wie in [SECURITY.md](SECURITY.md) beschrieben.

### Projektregeln

**Allgemein**

- Kein Framework, keine Composer-Abhängigkeiten.
- Kein CDN und keine externen Ressourcen zur Laufzeit (Skripte, Stylesheets, Schriften, Bilder) – alles wird mitgeliefert.
- PHP >= 8.3; Code muss auf PHP 8.3 laufen.
- Keine personenbezogenen Daten in Testdaten, Beispieldaten, Screenshots oder Issues.

**PHP und HTML**

- Jede HTML-Ausgabe läuft durch `e()`.
- Kein Inline-CSS und kein Inline-JavaScript (auch keine `style`- oder `on…`-Attribute) – die Content-Security-Policy ist strikt und erlaubt kein `unsafe-inline`.
- Datenbankzugriffe nur über Prepared Statements; keine Benutzereingaben in SQL-Strings.
- Schemaänderungen nur als neue, fortlaufend nummerierte Migration in `migrations/` (z. B. `006_beschreibung.sql` oder `.php`); bestehende Migrationen nicht ändern.

**Texte und Übersetzungen**

- Alle UI-Texte stehen ausschließlich in `lang/i18n.json` – keine Texte fest im Code.
- Deutsch, Englisch und Türkisch müssen exakt dieselben Schlüssel haben. Neue Schlüssel immer in allen drei Sprachen anlegen.

**Windows-Scanner (Go)**

- Nur die Go-Standardbibliothek, keine externen Module.
- Keine Netzwerkpakete (`net`, `net/http` usw.) – der Scanner hat keine Netzwerkfunktion.
- Niemals Benutzername, Rechnername, Domäne, IP- oder MAC-Adressen erfassen.

### Vor jedem Beitrag prüfen

1. Syntax aller geänderten PHP-Dateien:

   ```sh
   php -l pfad/zur/datei.php
   ```

2. Tests:

   ```sh
   php tests/run.php
   ```

   Die DB-Tests laufen nur, wenn `BESTANDIO_TEST_DB_HOST`, `BESTANDIO_TEST_DB_PORT`, `BESTANDIO_TEST_DB_NAME`, `BESTANDIO_TEST_DB_USER` und `BESTANDIO_TEST_DB_PASS` gesetzt sind.

   > **Achtung:** Die DB-Tests löschen alle Tabellen der angegebenen Datenbank. Nur eine eigene, leere Test-Datenbank verwenden – nie eine produktive.

3. Bei Änderungen am Scanner zusätzlich:

   ```sh
   go vet ./...
   go test ./...
   ```

### Commits und Pull Requests

- Commit-Nachrichten auf Englisch im Imperativ, z. B. `Add location filter to CSV export`, `Fix label grid rounding`.
- Ein Pull Request pro Thema; kurz beschreiben, was sich ändert und warum.
- Nutzerrelevante Änderungen im Abschnitt `[Unreleased]` von [CHANGELOG.md](CHANGELOG.md) eintragen.
- Mit dem Einreichen eines Beitrags stimmen Sie zu, dass er unter der MIT-Lizenz des Projekts ([LICENSE](LICENSE)) veröffentlicht wird.

---

## English

Thank you for your interest in Bestandio. Contributions are welcome – please read these rules first. Please do **not** report security vulnerabilities as issues or pull requests; follow [SECURITY.md](SECURITY.md) instead.

### Project rules

**General**

- No framework, no Composer dependencies.
- No CDN and no external resources at runtime (scripts, stylesheets, fonts, images) – everything is bundled.
- PHP >= 8.3; code must run on PHP 8.3.
- No personal data in test data, sample data, screenshots or issues.

**PHP and HTML**

- Every HTML output goes through `e()`.
- No inline CSS and no inline JavaScript (including `style` or `on…` attributes) – the Content-Security-Policy is strict and does not allow `unsafe-inline`.
- Database access only through prepared statements; never put user input into SQL strings.
- Schema changes only as a new, consecutively numbered migration in `migrations/` (e.g. `006_description.sql` or `.php`); do not modify existing migrations.

**Texts and translations**

- All UI texts live only in `lang/i18n.json` – no hard-coded texts.
- German, English and Turkish must have exactly the same keys. Always add new keys in all three languages.

**Windows scanner (Go)**

- Go standard library only, no external modules.
- No network packages (`net`, `net/http`, etc.) – the scanner has no network functionality.
- Never record user name, computer name, domain, IP or MAC addresses.

### Before every contribution

1. Syntax check of all changed PHP files:

   ```sh
   php -l path/to/file.php
   ```

2. Tests:

   ```sh
   php tests/run.php
   ```

   The DB tests only run when `BESTANDIO_TEST_DB_HOST`, `BESTANDIO_TEST_DB_PORT`, `BESTANDIO_TEST_DB_NAME`, `BESTANDIO_TEST_DB_USER` and `BESTANDIO_TEST_DB_PASS` are set.

   > **Warning:** The DB tests drop all tables in the given database. Use a dedicated, empty test database only – never a production one.

3. For scanner changes, additionally:

   ```sh
   go vet ./...
   go test ./...
   ```

### Commits and pull requests

- Commit messages in English, imperative mood, e.g. `Add location filter to CSV export`, `Fix label grid rounding`.
- One pull request per topic; briefly describe what changes and why.
- Add user-facing changes to the `[Unreleased]` section of [CHANGELOG.md](CHANGELOG.md).
- By submitting a contribution you agree that it is published under the project's MIT license ([LICENSE](LICENSE)).
