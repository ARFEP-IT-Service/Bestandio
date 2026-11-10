# Bestandio

[Deutsch](#deutsch) | [English](#english)

---

## Deutsch

Bestandio ist ein selbst gehostetes Web-Panel zur Inventarverwaltung (Geräte und Ausstattung) für Unternehmen. Jedes Unternehmen installiert es auf seinem eigenen VPS mit eigener MySQL-Datenbank. Kein App-Store, keine Cloud. Lizenz: MIT.

### Funktionen

- Einrichtungsassistent im Browser (`/setup`)
- Login/Logout mit `password_hash` (Passwort mind. 12 Zeichen), CSRF-Schutz bei jeder Änderung, Session-Cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` unter HTTPS); Sitzungen enden nach 2 Stunden ohne Aktivität, spätestens nach 12 Stunden
- Login-Drosselung: nach 5 Fehlversuchen pro Benutzername oder 20 pro IP innerhalb von 15 Minuten wird der Login für 15 Minuten gesperrt
- Rollen: **Admin** (alles inkl. Benutzerverwaltung und Einstellungen), **Editor** (Geräte, Kategorien, Standorte, Import), **Nur lesen** (Ansicht, Scannen, CSV-Export)
- Geräte mit Kategorie, Hersteller, Modell, Seriennummer, Inventarnummer (eindeutig), Standort, Status (in Benutzung / auf Lager / in Reparatur / ausgemustert), Kaufdatum, Garantie bis, Notiz
- Gerätecode: jedes Gerät erhält einen festen, zufälligen, eindeutigen 10-stelligen Code aus `23456789ABCDEFGHJKMNPQRSTUVWXYZ`, der sich nie ändert
- QR-Codes enthalten nur diesen Gerätecode (keine URL); erzeugt von einem eingebauten Encoder (ISO/IEC 18004, Fehlerkorrektur Q, SVG) – ohne Bibliothek
- Etikettendruck für ein Gerät oder eine Auswahl aus der Liste: QR-Code, Gerätecode, Inventarnummer, optional Firmenname; A4-Bogen mit einstellbarem Raster in mm (Spalten, Zeilen, Ränder, Abstände) oder Einzeletiketten für Etikettendrucker (Vorlagen „DYMO 54 × 101 mm“, „Zebra 103 × 199 mm“ oder eigene Größe); QR-Module werden auf ganze Druckerpunkte gerundet (dpi einstellbar, Standard 203); Druck über den Browser
- Einstellungsseite für Admins (Firmenname, Etikettenvorlage, A4-Raster, dpi)
- Scan-Seite `/scan` für alle angemeldeten Benutzer: nutzt den `BarcodeDetector` des Browsers, sonst die mitgelieferte Bibliothek jsQR 1.4.0 (Apache-2.0, Lizenz unter `public/assets/vendor/jsqr-1.4.0/LICENSE`); Kamera nur unter HTTPS oder auf `localhost`; alternativ Fotoauswahl oder manuelle Code-Eingabe; unbekannter Code → „nicht gefunden“
- Import von Hardware-Scandaten (JSON, siehe [docs/import-format.md](docs/import-format.md)) für Admin/Editor mit Vorschau und Bestätigung
- Hardwarefelder (CPU, RAM, Speicher, Betriebssystem, letzter Scan) und Softwareliste auf der Geräteseite
- Kategorien und Standorte als pflegbare Listen; Warnung bei doppelter Seriennummer
- Bewusst **keine** Zuordnung von Geräten zu Personen (keine personenbezogenen Daten an Geräten)
- Liste mit Suche, Filtern (Kategorie, Standort, Status), Sortierung, Seitenaufteilung
- CSV-Export der gefilterten Liste: Trennzeichen nach UI-Sprache (Deutsch/Türkisch `;`, Englisch `,`), UTF-8 mit BOM; Zellen, die mit `=` `+` `-` `@` Tab oder CR beginnen, werden gegen Formel-Injection mit einem führenden Apostroph entschärft
- Änderungsverlauf je Gerät (wer, wann, Feld, alt, neu)
- Sprachen Deutsch/Englisch/Türkisch, Voreinstellung nach Browser, umschaltbar
- Hell/Dunkel folgt dem System; Mobile-first
- Sicherheits-Header: strikte Content-Security-Policy (ohne `unsafe-inline`), `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors 'none'`, `Permissions-Policy` (Kamera nur für den eigenen Origin)

### Scanner für Windows (`bestandio-scanner.exe`)

- Doppelklick genügt: keine Installation, keine Admin-Rechte (Manifest `asInvoker`), keine Netzwerkverbindung.
- Liest Hersteller, Modell, Seriennummer (SMBIOS), Prozessor, RAM, interne Datenträger (Typ, Größe) und Windows-Version; auf Wunsch die installierten Programme („Programme mit erfassen? (j/N)“, Standard Nein).
- Erfasst **nie** Benutzername, Rechnername, Domäne, IP- oder MAC-Adressen; solche Werte werden zusätzlich aus allen Textfeldern entfernt (`[user]`).
- Platzhalter-Seriennummern („To be filled by O.E.M.“, „Default string“ …) bleiben leer; das Gerät wird dann beim Import neu angelegt.
- Ergebnis: `bestandio-scan-JJJJMMTT-HHMMSS.json` neben der .exe (bei schreibgeschütztem Ordner auf dem Desktop) und in der Zwischenablage. Im Webpanel unter „Import“ einfügen oder hochladen.
- Optionen: `--software`, `--out <Datei oder Ordner>`, `--no-clipboard`, `--lang de|en|tr`. Sonst Sprache nach Windows-Anzeigesprache.
- Bauen (Go ≥ 1.22, nur Standardbibliothek):

```sh
go vet ./... && go test ./...
go build -trimpath -ldflags "-s -w" -o dist/bestandio-scanner.exe ./scanner
```

Die .exe ist nicht signiert; Windows SmartScreen kann beim ersten Start nachfragen.

### Voraussetzungen

- PHP >= 8.2 mit den Erweiterungen `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (`intl` wird nicht benötigt)
- MySQL >= 8.0 oder MariaDB >= 10.6, `utf8mb4`
- Webserver: Apache (mit `mod_rewrite`) oder nginx + PHP-FPM
- Keine Composer-Abhängigkeiten, kein Build-Schritt, kein CDN, keine externen Ressourcen zur Laufzeit

### Verzeichnisstruktur

```
public/          Document Root: index.php (Front-Controller), assets/ (inkl. vendor/jsqr-1.4.0), .htaccess
app/             core, controllers, models, views
config/          config.example.php; config.php wird vom Setup erzeugt (git-ignoriert)
migrations/      nummerierte Migrationen (.sql oder .php)
lang/i18n.json   alle UI-Texte DE/EN/TR – die einzige Stelle zum Bearbeiten/Ergänzen von Übersetzungen
bin/bestandio    Kommandozeilenwerkzeug
tests/run.php    eigener Test-Runner (ohne externe Pakete)
docs/            Beispielkonfigurationen für Webserver, Importformat
```

### Installation

1. **Leere Datenbank und Benutzer anlegen** (Rechte nur auf diese Datenbank):

   ```sql
   CREATE DATABASE bestandio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'bestandio'@'localhost' IDENTIFIED BY 'ein-starkes-passwort';
   GRANT ALL PRIVILEGES ON bestandio.* TO 'bestandio'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Dateien auf den Server kopieren** und den Webserver einrichten (siehe [Webserver](#webserver)).
3. **`config/` für den Webserver-Benutzer beschreibbar machen** (nur für das Setup, danach wieder schreibgeschützt möglich):

   ```sh
   chown www-data /var/www/bestandio/config
   ```

4. **`https://ihr-host/setup` im Browser öffnen:** DB-Host, Port, Name, Benutzer und Passwort eingeben, „Verbindung testen“, dann Benutzername und Passwort (mind. 12 Zeichen) des ersten Admins eingeben und absenden. Das Setup führt die Migrationen aus, legt den Admin an und schreibt `config/config.php`. Danach ist `/setup` gesperrt (Antwort 403).
5. **Anmelden.**

#### Alternative: Konfiguration von Hand

```sh
cp config/config.example.php config/config.php   # Werte eintragen
php bin/bestandio migrate
```

Über die Umgebungsvariable `BESTANDIO_CONFIG` kann ein alternativer Pfad zur Konfigurationsdatei angegeben werden.

### Webserver

**Nur das Verzeichnis `public/` darf Document Root sein.** Alles andere (`app/`, `config/`, `migrations/`, `lang/`, `bin/`, `tests/`, `docs/`) darf aus dem Web nicht erreichbar sein. Vollständige Beispiele (Pfade, Hostname, Zertifikate und PHP-FPM-Socket anpassen; auch als `docs/apache-vhost.conf.example` und `docs/nginx.conf.example`):

**Apache** (`mod_rewrite`, `mod_ssl`):

```apache
<VirtualHost *:80>
    ServerName inventory.example.com
    Redirect permanent / https://inventory.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName inventory.example.com
    DocumentRoot /var/www/bestandio/public

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/inventory.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/inventory.example.com/privkey.pem

    <Directory /var/www/bestandio/public>
        Options -Indexes +FollowSymLinks
        # public/.htaccess enthält die Rewrite-Regeln
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
        # Ohne .htaccess: "AllowOverride None" und stattdessen:
        # RewriteEngine On
        # RewriteCond %{REQUEST_FILENAME} !-f
        # RewriteCond %{REQUEST_FILENAME} !-d
        # RewriteRule ^ index.php [QSA,L]
    </Directory>

    # Versteckte Dateien sperren (.htaccess, .git, ...)
    <LocationMatch "/\.">
        Require all denied
    </LocationMatch>

    # Optional PHP-FPM statt mod_php:
    # <FilesMatch "\.php$">
    #     SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
    # </FilesMatch>
</VirtualHost>
```

**nginx** (+ PHP-FPM):

```nginx
server {
    listen 80;
    server_name inventory.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name inventory.example.com;

    root /var/www/bestandio/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/inventory.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/inventory.example.com/privkey.pem;

    charset utf-8;
    client_max_body_size 8m;
    location ~ /\. { deny all; }                 # versteckte Dateien sperren

    location / { try_files $uri /index.php?$query_string; }

    location = /index.php {                      # nur der Front-Controller wird ausgeführt
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   $realpath_root;
        fastcgi_param HTTPS on;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ \.php$ { return 404; }            # alle anderen .php-Dateien
}
```

### HTTPS

HTTPS ist Pflicht. Die Kamera zum Scannen funktioniert nur über HTTPS (oder auf `localhost`), da Browser den Kamerazugriff nur in einem Secure Context erlauben. Die `Permissions-Policy` erlaubt die Kamera nur für den eigenen Origin. Empfohlen: Let's Encrypt mit certbot (`certbot --apache` bzw. `certbot --nginx`). Unter HTTPS erhält das Session-Cookie automatisch das `Secure`-Flag.

### Kommandozeile

```sh
php bin/bestandio migrate   # ausstehende Migrationen anwenden (Tabelle schema_migrations)
php bin/bestandio status    # angewendete/ausstehende Migrationen auflisten
```

### Updates

Dateien ersetzen, dann (Migrationen können `.sql`- oder `.php`-Dateien sein):

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

Übersetzungen werden ausschließlich in `lang/i18n.json` gepflegt (auch die Texte der Scanner-.exe, eingebunden über `lang/embed.go`).

### Lizenz

MIT – siehe [LICENSE](LICENSE). © 2026 Halil Ayberk Bulut (ARFEP)

### Fremdlizenzen

| Komponente | Version | Lizenz | Ort |
|---|---|---|---|
| jsQR (QR-Erkennung im Browser, nur wenn `BarcodeDetector` fehlt) | 1.4.0 | Apache-2.0 | `public/assets/vendor/jsqr-1.4.0/` mit `LICENSE` |

Sonst keine Fremdbibliotheken: kein Composer-Paket, keine Go-Module außerhalb der Standardbibliothek.

---

## English

Bestandio is a self-hosted inventory web panel for businesses (devices and equipment). Each business installs it on its own VPS with its own MySQL database. No app store, no cloud. License: MIT.

### Features

- Browser-based setup wizard (`/setup`)
- Login/logout with `password_hash` (password min. 12 characters), CSRF protection on every change, session cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` on HTTPS); sessions end after 2 hours without activity and at the latest after 12 hours
- Login throttling: after 5 failed attempts per user name or 20 per IP within 15 minutes, login is blocked for 15 minutes
- Roles: **Admin** (everything incl. user management and settings), **Editor** (devices, categories, locations, import), **Read-only** (view, scan, CSV export)
- Devices with category, manufacturer, model, serial number, inventory number (unique), location, status (in use / in stock / in repair / retired), purchase date, warranty until, note
- Device code: every device gets a fixed, random, unique 10-character code from `23456789ABCDEFGHJKMNPQRSTUVWXYZ` that never changes
- QR codes contain only this device code (no URL); generated by a built-in encoder (ISO/IEC 18004, error correction level Q, SVG) – no library
- Label printing for a single device or a selection from the list: QR code, device code, inventory number, optional company name; A4 sheet with a configurable grid in mm (columns, rows, margins, gaps) or single labels for label printers (templates "DYMO 54 × 101 mm", "Zebra 103 × 199 mm" or custom size); QR modules are rounded to whole printer dots (dpi configurable, default 203); printing via the browser
- Settings page for admins (company name, label template, A4 grid, dpi)
- Scan page `/scan` for all signed-in users: uses the browser's `BarcodeDetector` when available, otherwise the bundled jsQR 1.4.0 library (Apache-2.0, license at `public/assets/vendor/jsqr-1.4.0/LICENSE`); camera needs HTTPS or `localhost`; photo selection and manual code entry also available; unknown code → "not found"
- Import of hardware scan data (JSON, see [docs/import-format.md](docs/import-format.md)) for Admin/Editor with preview and confirmation
- Hardware fields (CPU, RAM, storage, operating system, last scan) and software list on the device page
- Categories and locations as maintainable lists; warning on duplicate serial number
- Deliberately **no** assignment of devices to persons (no personal data on devices)
- List with search, filters (category, location, status), sorting, pagination
- CSV export of the filtered list: separator depends on the UI language (German/Turkish `;`, English `,`), UTF-8 with BOM; cells starting with `=` `+` `-` `@` tab or CR are neutralised with a leading apostrophe against formula injection
- Per-device change history (who, when, field, old, new)
- Languages German/English/Turkish, chosen by browser default, switchable
- Light/dark follows the system; mobile-first
- Security headers: strict Content-Security-Policy (no `unsafe-inline`), `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors 'none'`, `Permissions-Policy` (camera for the own origin only)

### Windows scanner (`bestandio-scanner.exe`)

- Just double-click: no installation, no admin rights (manifest `asInvoker`), no network connection.
- Reads manufacturer, model, serial number (SMBIOS), CPU, RAM, internal disks (type, size) and Windows version; optionally the installed programs ("Also record installed programs? (y/N)", default no).
- **Never** records user name, computer name, domain, IP or MAC addresses; such values are additionally removed from all text fields (`[user]`).
- Placeholder serial numbers ("To be filled by O.E.M.", "Default string" …) stay empty; the import then creates a new device.
- Result: `bestandio-scan-YYYYMMDD-HHMMSS.json` next to the .exe (on the desktop if the folder is read-only) and on the clipboard. Paste or upload it in the web panel under "Import".
- Options: `--software`, `--out <file or folder>`, `--no-clipboard`, `--lang de|en|tr`. Otherwise the language follows the Windows display language.
- Build (Go ≥ 1.22, standard library only):

```sh
go vet ./... && go test ./...
go build -trimpath -ldflags "-s -w" -o dist/bestandio-scanner.exe ./scanner
```

The .exe is not signed; Windows SmartScreen may ask on first start.

### Requirements

- PHP >= 8.2 with extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (`intl` is not required)
- MySQL >= 8.0 or MariaDB >= 10.6, `utf8mb4`
- Web server: Apache (with `mod_rewrite`) or nginx + PHP-FPM
- No Composer dependencies, no build step, no CDN, no external resources at runtime

### Directory layout

```
public/          document root: index.php (front controller), assets/ (incl. vendor/jsqr-1.4.0), .htaccess
app/             core, controllers, models, views
config/          config.example.php; config.php is generated by setup (git-ignored)
migrations/      numbered migrations (.sql or .php)
lang/i18n.json   all UI texts DE/EN/TR – the single place to edit or add translations
bin/bestandio    command-line tool
tests/run.php    own test runner (no external packages)
docs/            example web server configurations, import format
```

### Installation

1. **Create an empty database and a user** with rights only on it:

   ```sql
   CREATE DATABASE bestandio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'bestandio'@'localhost' IDENTIFIED BY 'a-strong-password';
   GRANT ALL PRIVILEGES ON bestandio.* TO 'bestandio'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Copy the files to the server** and configure the web server (see [Web server](#web-server)).
3. **Make `config/` writable by the web server user** (only for the setup; can be made read-only again afterwards):

   ```sh
   chown www-data /var/www/bestandio/config
   ```

4. **Open `https://your-host/setup` in the browser:** enter DB host, port, name, user and password, click "Test connection", then enter the first admin's user name and password (min. 12 characters) and submit. Setup runs the migrations, creates the admin and writes `config/config.php`. After that `/setup` is locked (returns 403).
5. **Log in.**

#### Alternative: configure by hand

```sh
cp config/config.example.php config/config.php   # fill in values
php bin/bestandio migrate
```

The environment variable `BESTANDIO_CONFIG` can point to an alternative config file path.

### Web server

**Only the `public/` directory may be the document root.** Everything else (`app/`, `config/`, `migrations/`, `lang/`, `bin/`, `tests/`, `docs/`) must not be reachable from the web. Complete examples (adjust paths, host name, certificates and PHP-FPM socket; also available as `docs/apache-vhost.conf.example` and `docs/nginx.conf.example`):

**Apache** (`mod_rewrite`, `mod_ssl`):

```apache
<VirtualHost *:80>
    ServerName inventory.example.com
    Redirect permanent / https://inventory.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName inventory.example.com
    DocumentRoot /var/www/bestandio/public

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/inventory.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/inventory.example.com/privkey.pem

    <Directory /var/www/bestandio/public>
        Options -Indexes +FollowSymLinks
        # public/.htaccess contains the rewrite rules
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
        # Without .htaccess: "AllowOverride None" and instead:
        # RewriteEngine On
        # RewriteCond %{REQUEST_FILENAME} !-f
        # RewriteCond %{REQUEST_FILENAME} !-d
        # RewriteRule ^ index.php [QSA,L]
    </Directory>

    # Block hidden files (.htaccess, .git, ...)
    <LocationMatch "/\.">
        Require all denied
    </LocationMatch>

    # Optional PHP-FPM instead of mod_php:
    # <FilesMatch "\.php$">
    #     SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
    # </FilesMatch>
</VirtualHost>
```

**nginx** (+ PHP-FPM):

```nginx
server {
    listen 80;
    server_name inventory.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name inventory.example.com;

    root /var/www/bestandio/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/inventory.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/inventory.example.com/privkey.pem;

    charset utf-8;
    client_max_body_size 8m;
    location ~ /\. { deny all; }                 # block hidden files

    location / { try_files $uri /index.php?$query_string; }

    location = /index.php {                      # only the front controller is executed
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   $realpath_root;
        fastcgi_param HTTPS on;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ \.php$ { return 404; }            # any other .php file
}
```

### HTTPS

HTTPS is required. The camera for scanning only works over HTTPS (or on `localhost`), since browsers only allow camera access in a secure context. The `Permissions-Policy` allows the camera for the own origin only. Recommended: Let's Encrypt with certbot (`certbot --apache` or `certbot --nginx`). Under HTTPS the session cookie automatically gets the `Secure` flag.

### Command line

```sh
php bin/bestandio migrate   # apply pending migrations (tracked in table schema_migrations)
php bin/bestandio status    # list applied/pending migrations
```

### Updates

Replace the files, then (migrations may be `.sql` or `.php` files):

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

Translations are maintained only in `lang/i18n.json` (including the texts of the scanner .exe, embedded via `lang/embed.go`).

### License

MIT – see [LICENSE](LICENSE). © 2026 Halil Ayberk Bulut (ARFEP)

### Third-party licenses

| Component | Version | License | Location |
|---|---|---|---|
| jsQR (QR detection in the browser, only when `BarcodeDetector` is missing) | 1.4.0 | Apache-2.0 | `public/assets/vendor/jsqr-1.4.0/` with `LICENSE` |

No other third-party code: no Composer packages, no Go modules outside the standard library.
