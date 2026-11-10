# Bestandio – Importformat für Hardware-Scans

[Deutsch](#deutsch) | [English](#english)

---

## Deutsch

Dieses Dokument beschreibt das JSON-Format `bestandio-scan` (Version 1), mit dem Hardware-Scandaten in Bestandio importiert werden.

### Beispiel

```json
{
  "format": "bestandio-scan",
  "version": 1,
  "scanned_at": "2026-11-10T19:30:00Z",
  "hardware": {
    "manufacturer": "LENOVO",
    "model": "21MC0012GE",
    "serial_number": "PF4ABC12",
    "cpu": "Intel(R) Core(TM) Ultra 7 155U",
    "ram_mb": 32768,
    "disks": [ { "type": "nvme", "size_gb": 1024 } ]
  },
  "os": { "name": "Windows 11 Pro", "version": "10.0.26100" },
  "software": [ { "name": "7-Zip", "version": "24.08", "vendor": "Igor Pavlov" } ]
}
```

### Minimalbeispiel

```json
{
  "format": "bestandio-scan",
  "version": 1,
  "scanned_at": "2026-11-10T19:30:00Z",
  "hardware": {
    "manufacturer": "LENOVO",
    "serial_number": "PF4ABC12"
  }
}
```

### Datei und Eingabe

- UTF-8-kodiertes JSON; ein führendes UTF-8-BOM wird toleriert.
- Maximal 1 MiB (1.048.576 Byte), maximale Verschachtelungstiefe 16.
- Eingabe im Web-Panel auf der Seite „Import“: Text einfügen oder eine `.json`-Datei hochladen. Nur für die Rollen **Admin** und **Editor**.

### Felder

| Feld | Typ | Pflicht | Grenzen / Regeln |
|---|---|---|---|
| `format` | String | ja | genau `"bestandio-scan"` |
| `version` | Integer | ja | genau `1`; andere Versionen werden abgelehnt |
| `scanned_at` | String | ja | ISO-8601-Datum/Uhrzeit mit Zeitzone, z. B. `2026-11-10T19:30:00Z` oder `2026-11-10T20:30:00+01:00`; höchstens 24 Stunden in der Zukunft |
| `hardware` | Objekt | ja | |
| `hardware.manufacturer` | String | ja | 1–100 Zeichen |
| `hardware.serial_number` | String | ja | 1–100 Zeichen |
| `hardware.model` | String | nein | ≤ 100 Zeichen |
| `hardware.cpu` | String | nein | ≤ 190 Zeichen |
| `hardware.ram_mb` | Integer | nein | 1–16777216 |
| `hardware.disks` | Array | nein | höchstens 32 Einträge |
| `hardware.disks[].type` | String | ja (je Eintrag) | `ssd`, `hdd`, `nvme`, `emmc` oder `other` (Groß-/Kleinschreibung egal) |
| `hardware.disks[].size_gb` | Zahl | ja (je Eintrag) | > 0 und ≤ 1000000 |
| `os` | Objekt | nein | |
| `os.name` | String | nein | ≤ 100 Zeichen |
| `os.version` | String | nein | ≤ 64 Zeichen |
| `software` | Array | nein | höchstens 5000 Einträge |
| `software[].name` | String | ja (je Eintrag) | 1–190 Zeichen |
| `software[].version` | String | nein | ≤ 64 Zeichen |
| `software[].vendor` | String | nein | ≤ 190 Zeichen |

### Validierung

- Strings werden getrimmt; Steuerzeichen werden entfernt.
- Falsche Typen oder verletzte Regeln führen dazu, dass der gesamte Import mit einer Fehlerliste abgelehnt wird.
- Vor der Bestätigung wird nichts gespeichert.
- Unbekannte Felder werden auf jeder Ebene ignoriert und nie gespeichert. Die Vorschau zeigt, wie viele Felder ignoriert wurden.

### Datenschutz

Das Format enthält bewusst nur Hardwaredaten: keine Benutzernamen, keine Computer-/Hostnamen, keine IP- oder MAC-Adressen. Sendet ein Scanner solche Felder, werden sie wie jedes unbekannte Feld ignoriert.

### Zuordnung zu Geräten

Ein vorhandenes Gerät wird über **Hersteller + Seriennummer** gefunden (ohne Beachtung der Groß-/Kleinschreibung, getrimmt).

- **Ein Treffer:** Vorschau „Aktualisieren“ mit Alt/Neu-Vergleich je Feld.
- **Mehrere Treffer:** Der Benutzer wählt das Gerät aus.
- **Kein Treffer:** Vorschau „Neu anlegen“; der Benutzer wählt Kategorie, Standort und Status und gibt die Inventarnummer ein. Hat ein anderes Gerät bereits dieselbe Seriennummer, muss eine Warnung bestätigt werden.

Gespeichert wird erst nach Bestätigung. Jede Änderung wird in den Änderungsverlauf des Geräts geschrieben; Softwareänderungen erscheinen als ein Verlaufseintrag mit alter/neuer Anzahl. Die Softwareliste des Geräts wird durch die importierte Liste ersetzt.

### Feldzuordnung

| Importfeld | Gerätefeld |
|---|---|
| `hardware.manufacturer` | Hersteller |
| `hardware.model` | Modell |
| `hardware.serial_number` | Seriennummer |
| `hardware.cpu` | CPU |
| `hardware.ram_mb` | RAM |
| `hardware.disks` | Speicher (als Textzusammenfassung, z. B. `NVMe 1024 GB`) |
| `os.name` / `os.version` | Betriebssystem |
| `scanned_at` | Letzter Scan |
| `software` | Softwaretabelle des Geräts |

---

## English

This document describes the JSON format `bestandio-scan` (version 1) used to import hardware scan data into Bestandio.

### Example

```json
{
  "format": "bestandio-scan",
  "version": 1,
  "scanned_at": "2026-11-10T19:30:00Z",
  "hardware": {
    "manufacturer": "LENOVO",
    "model": "21MC0012GE",
    "serial_number": "PF4ABC12",
    "cpu": "Intel(R) Core(TM) Ultra 7 155U",
    "ram_mb": 32768,
    "disks": [ { "type": "nvme", "size_gb": 1024 } ]
  },
  "os": { "name": "Windows 11 Pro", "version": "10.0.26100" },
  "software": [ { "name": "7-Zip", "version": "24.08", "vendor": "Igor Pavlov" } ]
}
```

### Minimal example

```json
{
  "format": "bestandio-scan",
  "version": 1,
  "scanned_at": "2026-11-10T19:30:00Z",
  "hardware": {
    "manufacturer": "LENOVO",
    "serial_number": "PF4ABC12"
  }
}
```

### File and input

- UTF-8 encoded JSON; a leading UTF-8 BOM is tolerated.
- At most 1 MiB (1,048,576 bytes), maximum nesting depth 16.
- Input in the web panel on the "Import" page: paste the text or upload a `.json` file. Only for the roles **Admin** and **Editor**.

### Fields

| Field | Type | Required | Limits / rules |
|---|---|---|---|
| `format` | string | yes | exactly `"bestandio-scan"` |
| `version` | integer | yes | exactly `1`; other versions are rejected |
| `scanned_at` | string | yes | ISO 8601 date-time with time zone, e.g. `2026-11-10T19:30:00Z` or `2026-11-10T20:30:00+01:00`; at most 24 hours in the future |
| `hardware` | object | yes | |
| `hardware.manufacturer` | string | yes | 1–100 characters |
| `hardware.serial_number` | string | yes | 1–100 characters |
| `hardware.model` | string | no | ≤ 100 characters |
| `hardware.cpu` | string | no | ≤ 190 characters |
| `hardware.ram_mb` | integer | no | 1–16777216 |
| `hardware.disks` | array | no | at most 32 entries |
| `hardware.disks[].type` | string | yes (per entry) | `ssd`, `hdd`, `nvme`, `emmc` or `other` (case-insensitive) |
| `hardware.disks[].size_gb` | number | yes (per entry) | > 0 and ≤ 1000000 |
| `os` | object | no | |
| `os.name` | string | no | ≤ 100 characters |
| `os.version` | string | no | ≤ 64 characters |
| `software` | array | no | at most 5000 entries |
| `software[].name` | string | yes (per entry) | 1–190 characters |
| `software[].version` | string | no | ≤ 64 characters |
| `software[].vendor` | string | no | ≤ 190 characters |

### Validation

- Strings are trimmed; control characters are removed.
- Wrong types or violated rules cause the whole import to be rejected with an error list.
- Nothing is stored before confirmation.
- Unknown fields at any level are ignored and never stored. The preview shows how many fields were ignored.

### Data protection

The format deliberately contains only hardware data: no user names, no computer/host names, no IP or MAC addresses. If a scanner sends such fields, they are ignored like any unknown field.

### Matching devices

An existing device is found by **manufacturer + serial number** (case-insensitive, trimmed).

- **One match:** preview "update" with an old/new comparison per field.
- **Several matches:** the user chooses the device.
- **No match:** preview "create new"; the user selects category, location and status and enters the inventory number. If another device already has the same serial number, a warning must be confirmed.

Saving only happens after confirmation. Every change is written to the device's change history; software changes appear as one history entry with the old/new count. The device's software list is replaced by the imported list.

### Field mapping

| Import field | Device field |
|---|---|
| `hardware.manufacturer` | Manufacturer |
| `hardware.model` | Model |
| `hardware.serial_number` | Serial number |
| `hardware.cpu` | CPU |
| `hardware.ram_mb` | RAM |
| `hardware.disks` | Storage (stored as a text summary, e.g. `NVMe 1024 GB`) |
| `os.name` / `os.version` | Operating system |
| `scanned_at` | Last scan |
| `software` | Software table of the device |
