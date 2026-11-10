# Bestandio

[Deutsch](#deutsch) | [English](#english)

---

## Deutsch

Bestandio ist ein selbst gehostetes Web-Panel zur Inventarverwaltung (Geräte und Ausstattung) für Unternehmen. Jedes Unternehmen installiert es auf seinem eigenen VPS mit eigener MySQL-Datenbank. Kein App-Store, keine Cloud. Lizenz: MIT.

### Funktionen (v1)

- Einrichtungsassistent im Browser (`/setup`)
- Login/Logout mit `password_hash`, CSRF-Schutz bei jeder Änderung, Session-Cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` unter HTTPS)
- Login-Drosselung: nach 5 Fehlversuchen pro Benutzername oder 20 pro IP innerhalb von 15 Minuten wird der Login für 15 Minuten gesperrt
- Rollen: **Admin** (alles inkl. Benutzerverwaltung), **Editor** (Geräte, Kategorien, Standorte), **Nur lesen** (Ansicht + CSV-Export)
- Geräte mit Kategorie, Hersteller, Modell, Seriennummer, Inventarnummer (eindeutig), Standort, Status (in Benutzung / auf Lager / in Reparatur / ausgemustert), Kaufdatum, Garantie bis, Notiz
- Kategorien und Standorte als pflegbare Listen; Warnung bei doppelter Seriennummer
- Bewusst **keine** Zuordnung von Geräten zu Personen (keine personenbezogenen Daten an Geräten)
- Liste mit Suche, Filtern (Kategorie, Standort, Status), Sortierung, Seitenaufteilung
- CSV-Export der gefilterten Liste (Semikolon-getrennt, UTF-8 mit BOM; Zellen, die mit `=` `+` `-` `@` Tab oder CR beginnen, werden gegen Formel-Injection mit einem führenden Apostroph entschärft)
- Änderungsverlauf je Gerät (wer, wann, Feld, alt, neu)
- Sprachen Deutsch/Englisch/Türkisch, Voreinstellung nach Browser, umschaltbar
- Hell/Dunkel folgt dem System; Mobile-first
- Sicherheits-Header: strikte Content-Security-Policy (ohne `unsafe-inline`), `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors 'none'`

**Noch nicht enthalten (geplant):** QR-Code-Erzeugung, QR-Scan per Kamera, Import, portable Windows-.exe.

### Voraussetzungen

- PHP >= 8.2 mit den Erweiterungen `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (`intl` wird nicht benötigt)
- MySQL >= 8.0 oder MariaDB >= 10.6, `utf8mb4`
- Webserver: Apache (mit `mod_rewrite`) oder nginx + PHP-FPM
- Keine Composer-Abhängigkeiten, kein Build-Schritt, kein CDN, keine externen Ressourcen zur Laufzeit

### Verzeichnisstruktur

```
public/          Document Root: index.php (Front-Controller), assets/, .htaccess
app/             core, controllers, models, views
config/          config.example.php; config.php wird vom Setup erzeugt (git-ignoriert)
migrations/      nummerierte SQL-Dateien (001_*.sql, ...)
lang/i18n.json   alle UI-Texte DE/EN/TR – die einzige Stelle zum Bearbeiten/Ergänzen von Übersetzungen
bin/bestandio    Kommandozeilenwerkzeug
tests/run.php    eigener Test-Runner (ohne externe Pakete)
docs/            Beispielkonfigurationen für Webserver
```

Nur `public/` ist über das Web erreichbar.

### Installation

1. **Leere Datenbank und Benutzer anlegen** (Rechte nur auf diese Datenbank):

   ```sql
   CREATE DATABASE bestandio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'bestandio'@'localhost' IDENTIFIED BY 'ein-starkes-passwort';
   GRANT ALL PRIVILEGES ON bestandio.* TO 'bestandio'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Dateien auf den Server kopieren** und den Document Root auf `public/` setzen. Beispiele: `docs/nginx.conf.example`, `docs/apache-vhost.conf.example`.
3. **`config/` für den Webserver-Benutzer beschreibbar machen** (nur für das Setup, danach wieder schreibgeschützt möglich):

   ```sh
   chown www-data /var/www/bestandio/config
   ```

4. **`https://ihr-host/setup` im Browser öffnen:** DB-Host, Port, Name, Benutzer und Passwort eingeben, „Verbindung testen“, dann Benutzername und Passwort (mind. 10 Zeichen) des ersten Admins eingeben und absenden. Das Setup führt die Migrationen aus, legt den Admin an und schreibt `config/config.php`. Danach ist `/setup` gesperrt (Antwort 403).
5. **Anmelden.**

#### Alternative: Konfiguration von Hand

```sh
cp config/config.example.php config/config.php   # Werte eintragen
php bin/bestandio migrate
```

Über die Umgebungsvariable `BESTANDIO_CONFIG` kann ein alternativer Pfad zur Konfigurationsdatei angegeben werden.

### Kommandozeile

```sh
php bin/bestandio migrate   # ausstehende Migrationen anwenden (Tabelle schema_migrations)
php bin/bestandio status    # angewendete/ausstehende Migrationen auflisten
```

### HTTPS

HTTPS ist im Produktivbetrieb Pflicht. Es ist außerdem Voraussetzung für das geplante QR-Scannen per Kamera, da Browser den Kamerazugriff nur über HTTPS (Secure Context) erlauben. Empfohlen: Let's Encrypt mit certbot. Unter HTTPS erhält das Session-Cookie automatisch das `Secure`-Flag.

### Updates

Dateien ersetzen, dann:

```sh
php bin/bestandio migrate
```

### Backups

Die Datenbank (z. B. mit `mysqldump`) und `config/config.php` sichern:

```sh
mysqldump --single-transaction -u bestandio -p bestandio > bestandio-$(date +%F).sql
```

### Entwicklung

```sh
php -S localhost:8000 -t public   # lokaler Entwicklungsserver
php tests/run.php                 # Tests ausführen
```

DB-Tests laufen, wenn `BESTANDIO_TEST_DB_HOST`, `BESTANDIO_TEST_DB_PORT`, `BESTANDIO_TEST_DB_NAME`, `BESTANDIO_TEST_DB_USER` und `BESTANDIO_TEST_DB_PASS` gesetzt sind.

> **Achtung:** Die DB-Tests löschen alle Tabellen in dieser Datenbank. Nur eine eigene Test-Datenbank verwenden.

Übersetzungen werden ausschließlich in `lang/i18n.json` gepflegt.

### Lizenz

MIT – siehe [LICENSE](LICENSE). © 2026 Halil Ayberk Bulut (ARFEP)

---

## English

Bestandio is a self-hosted inventory web panel for businesses (devices and equipment). Each business installs it on its own VPS with its own MySQL database. No app store, no cloud. License: MIT.

### Features (v1)

- Browser-based setup wizard (`/setup`)
- Login/logout with `password_hash`, CSRF protection on every change, session cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` on HTTPS)
- Login throttling: after 5 failed attempts per user name or 20 per IP within 15 minutes, login is blocked for 15 minutes
- Roles: **Admin** (everything incl. user management), **Editor** (devices, categories, locations), **Read-only** (view + CSV export)
- Devices with category, manufacturer, model, serial number, inventory number (unique), location, status (in use / in stock / in repair / retired), purchase date, warranty until, note
- Categories and locations as maintainable lists; warning on duplicate serial number
- Deliberately **no** assignment of devices to persons (no personal data on devices)
- List with search, filters (category, location, status), sorting, pagination
- CSV export of the filtered list (semicolon-separated, UTF-8 with BOM; cells starting with `=` `+` `-` `@` tab or CR are neutralised with a leading apostrophe against formula injection)
- Per-device change history (who, when, field, old, new)
- Languages German/English/Turkish, chosen by browser default, switchable
- Light/dark follows the system; mobile-first
- Security headers: strict Content-Security-Policy (no `unsafe-inline`), `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors 'none'`

**Not yet included (planned):** QR code generation, QR scanning via camera, import, portable Windows .exe.

### Requirements

- PHP >= 8.2 with extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (`intl` is not required)
- MySQL >= 8.0 or MariaDB >= 10.6, `utf8mb4`
- Web server: Apache (with `mod_rewrite`) or nginx + PHP-FPM
- No Composer dependencies, no build step, no CDN, no external resources at runtime

### Directory layout

```
public/          document root: index.php (front controller), assets/, .htaccess
app/             core, controllers, models, views
config/          config.example.php; config.php is generated by setup (git-ignored)
migrations/      numbered SQL files (001_*.sql, ...)
lang/i18n.json   all UI texts DE/EN/TR – the single place to edit or add translations
bin/bestandio    command-line tool
tests/run.php    own test runner (no external packages)
docs/            example web server configurations
```

Only `public/` is exposed to the web.

### Installation

1. **Create an empty database and a user** with rights only on it:

   ```sql
   CREATE DATABASE bestandio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'bestandio'@'localhost' IDENTIFIED BY 'a-strong-password';
   GRANT ALL PRIVILEGES ON bestandio.* TO 'bestandio'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Copy the files to the server** and point the document root to `public/`. Examples: `docs/nginx.conf.example`, `docs/apache-vhost.conf.example`.
3. **Make `config/` writable by the web server user** (only for the setup; can be made read-only again afterwards):

   ```sh
   chown www-data /var/www/bestandio/config
   ```

4. **Open `https://your-host/setup` in the browser:** enter DB host, port, name, user and password, click "Test connection", then enter the first admin's user name and password (min. 10 characters) and submit. Setup runs the migrations, creates the admin and writes `config/config.php`. After that `/setup` is locked (returns 403).
5. **Log in.**

#### Alternative: configure by hand

```sh
cp config/config.example.php config/config.php   # fill in values
php bin/bestandio migrate
```

The environment variable `BESTANDIO_CONFIG` can point to an alternative config file path.

### Command line

```sh
php bin/bestandio migrate   # apply pending migrations (tracked in table schema_migrations)
php bin/bestandio status    # list applied/pending migrations
```

### HTTPS

HTTPS is required in production. It is also mandatory for the planned camera-based QR scanning, since browsers only allow camera access on HTTPS (secure contexts). Recommended: Let's Encrypt with certbot. Under HTTPS the session cookie automatically gets the `Secure` flag.

### Updates

Replace the files, then:

```sh
php bin/bestandio migrate
```

### Backups

Back up the database (e.g. with `mysqldump`) and `config/config.php`:

```sh
mysqldump --single-transaction -u bestandio -p bestandio > bestandio-$(date +%F).sql
```

### Development

```sh
php -S localhost:8000 -t public   # local development server
php tests/run.php                 # run tests
```

DB tests run when `BESTANDIO_TEST_DB_HOST`, `BESTANDIO_TEST_DB_PORT`, `BESTANDIO_TEST_DB_NAME`, `BESTANDIO_TEST_DB_USER` and `BESTANDIO_TEST_DB_PASS` are set.

> **Warning:** DB tests drop all tables in that database. Use a dedicated test database.

Translations are maintained only in `lang/i18n.json`.

### License

MIT – see [LICENSE](LICENSE). © 2026 Halil Ayberk Bulut (ARFEP)
