# Security Policy / Sicherheitsrichtlinie

[Deutsch](#deutsch) | [English](#english)

---

## Deutsch

### Unterstützte Versionen

| Version | Sicherheitsupdates |
|---|---|
| 0.1.x | ja |
| < 0.1 | nein |

### Sicherheitslücke melden

Bitte Sicherheitslücken **nicht öffentlich** melden – also kein öffentliches Issue, keine Pull-Request-Beschreibung, kein Forenbeitrag. Stattdessen vertraulich per E-Mail an:

**info@arfep.de**

Bitte möglichst angeben:

- betroffene Bestandio-Version (Datei `VERSION` bzw. Fußzeile des Web-Panels, beim Scanner `--version`)
- Umgebung (PHP-Version, MySQL/MariaDB-Version, Webserver, Betriebssystem)
- Schritte zum Nachvollziehen, idealerweise mit einem minimalen Beispiel
- Auswirkung: was ein Angreifer erreichen kann und welche Rolle oder welcher Zugang dafür nötig ist
- falls vorhanden: Vorschlag für eine Behebung

Keine echten personenbezogenen Daten oder Zugangsdaten mitschicken.

### Was Sie erwarten können

Bestandio wird von einer kleinen Zahl von Personen gepflegt. Es gibt deshalb **keine fest zugesagten Reaktionszeiten**; Meldungen werden nach bestem Bemühen bearbeitet.

- Sie erhalten eine Eingangsbestätigung, sobald die Meldung gelesen wurde.
- Die Meldung wird geprüft; Sie erfahren, ob sie als Sicherheitslücke bestätigt wird.
- Bestätigte Lücken werden in einer neuen 0.1.x-Version behoben und in [CHANGELOG.md](CHANGELOG.md) vermerkt. Auf Wunsch werden Sie dort genannt.
- Bitte veröffentlichen Sie Details erst, nachdem eine behobene Version erschienen ist oder wir uns auf einen Zeitpunkt geeinigt haben.

### Geltungsbereich

Im Geltungsbereich:

- der PHP-Code dieses Repositorys (Web-Panel, Setup, Kommandozeile `bin/bestandio`, Migrationen)
- der Windows-Scanner (`scanner/`, `bestandio-scanner.exe`)
- die mitgelieferten Beispielkonfigurationen in `docs/`

Nicht im Geltungsbereich:

- Lücken in PHP, MySQL/MariaDB, Apache, nginx oder dem Betriebssystem selbst
- Fehlkonfiguration des eigenen Servers (z. B. ein anderes Document Root als `public/`, kein HTTPS, beschreibbare Code-Verzeichnisse)
- Lücken in jsQR 1.4.0 – bitte direkt beim Projekt jsQR melden; wenn Bestandio dadurch konkret angreifbar ist, gern zusätzlich an uns
- Angriffe, die bereits volle Admin-Rechte in Bestandio oder Shell-Zugriff auf den Server voraussetzen

### Eingebaute Schutzmaßnahmen (kurz)

- Strikte Content-Security-Policy ohne `unsafe-inline`, dazu `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors 'none'` und `Permissions-Policy`
- CSRF-Token bei jeder ändernden Anfrage
- Passwörter mit `password_hash` (mind. 12 Zeichen); Session-Cookie `HttpOnly`, `SameSite=Strict`, unter HTTPS `Secure`; Sitzungsende nach 2 Stunden Inaktivität bzw. spätestens 12 Stunden
- Login-Drosselung (5 Fehlversuche je Benutzer, 20 je IP in 15 Minuten)
- Rollen werden serverseitig geprüft
- Datenbankzugriffe ausschließlich über Prepared Statements
- Nur `public/` ist Document Root; Code, Konfiguration und Migrationen liegen außerhalb
- Der Windows-Scanner hat keinen Netzwerkcode und erfasst keine Benutzer-, Rechner- oder Netzwerkkennungen

---

## English

### Supported versions

| Version | Security updates |
|---|---|
| 0.1.x | yes |
| < 0.1 | no |

### Reporting a vulnerability

Please do **not** report vulnerabilities publicly – no public issue, no pull request description, no forum post. Instead, report them privately by email to:

**info@arfep.de**

Please include, where possible:

- affected Bestandio version (file `VERSION` or the web panel footer; for the scanner `--version`)
- environment (PHP version, MySQL/MariaDB version, web server, operating system)
- steps to reproduce, ideally with a minimal example
- impact: what an attacker can achieve and which role or access is required
- if available: a suggested fix

Do not send real personal data or credentials.

### What to expect

Bestandio is maintained by a small number of people. There are therefore **no guaranteed response times**; reports are handled on a best-effort basis.

- You will receive an acknowledgement once the report has been read.
- The report will be investigated, and you will be told whether it is confirmed as a vulnerability.
- Confirmed vulnerabilities are fixed in a new 0.1.x release and noted in [CHANGELOG.md](CHANGELOG.md). You will be credited there if you wish.
- Please only publish details after a fixed version has been released or a date has been agreed on.

### Scope

In scope:

- the PHP code in this repository (web panel, setup, command line `bin/bestandio`, migrations)
- the Windows scanner (`scanner/`, `bestandio-scanner.exe`)
- the example configurations shipped in `docs/`

Out of scope:

- vulnerabilities in PHP, MySQL/MariaDB, Apache, nginx or the operating system itself
- misconfiguration of your own server (e.g. a document root other than `public/`, no HTTPS, writable code directories)
- vulnerabilities in jsQR 1.4.0 – please report them to the jsQR project directly; if they make Bestandio concretely exploitable, feel free to tell us as well
- attacks that already require full admin rights in Bestandio or shell access to the server

### Built-in protections (brief)

- Strict Content-Security-Policy without `unsafe-inline`, plus `X-Content-Type-Options`, `Referrer-Policy`, `frame-ancestors 'none'` and `Permissions-Policy`
- CSRF token on every state-changing request
- Passwords hashed with `password_hash` (min. 12 characters); session cookie `HttpOnly`, `SameSite=Strict`, `Secure` under HTTPS; sessions end after 2 hours of inactivity or at the latest after 12 hours
- Login throttling (5 failed attempts per user, 20 per IP within 15 minutes)
- Roles are checked server-side
- Database access exclusively through prepared statements
- Only `public/` is the document root; code, configuration and migrations live outside it
- The Windows scanner contains no network code and does not record user, computer or network identifiers
