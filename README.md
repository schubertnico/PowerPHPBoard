# PowerPHPBoard

[![PHP](https://img.shields.io/badge/PHP-8.4-blue?style=flat-square)](https://www.php.net/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?style=flat-square)](https://getbootstrap.com/)
[![tests](https://img.shields.io/badge/tests-198%20passing-brightgreen?style=flat-square)](#testing)
[![lighthouse](https://img.shields.io/badge/Lighthouse-100%2F100%2F100%2F100-brightgreen?style=flat-square)](#accessibility)
[![license](https://img.shields.io/badge/license-MIT-yellow?style=flat-square)](LICENSE)

Ein sicheres, leichtgewichtiges PHP-Forum-System für **PHP 8.4+** mit
modernem **Bootstrap-5-Frontend**.
Ursprünglich entwickelt 2001-2009 von Stefan "BFG" Kramer, modernisiert 2026.

- **Projekt-Website:** [https://www.powerscripts.org](https://www.powerscripts.org)
- **Projektübersicht:** [https://www.powerscripts.org/projects-3.html](https://www.powerscripts.org/projects-3.html)
- **Repository:** [https://github.com/schubertnico/PowerPHPBoard](https://github.com/schubertnico/PowerPHPBoard)

---

## Schnellstart (Docker, empfohlen)

Du brauchst nur **Git**, **Composer** und **Docker Desktop** (oder docker compose v2).

```bash
# 1. Repository klonen
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard

# 2. Dev-Dependencies für Tests/Tooling installieren
composer install

# 3. Container starten (Forum + MySQL + Mailpit + phpMyAdmin)
cd .docker
docker compose up -d --build
cd ..

# 4. Im Browser öffnen
#    http://localhost:8085  -> Forum
```

Beim ersten Start wird `install.sql` automatisch in die MySQL-DB geladen.
Ein Admin-Account "Gott" wird angelegt (Passwort steht in `install.sql` – nach erstem Login sofort ändern!).

| Service       | URL                            | Zweck                            |
|---------------|--------------------------------|----------------------------------|
| Forum         | http://localhost:8085          | Hauptanwendung                   |
| phpMyAdmin    | http://localhost:8088          | DB-Verwaltung                    |
| Mailpit (UI)  | http://localhost:8032          | E-Mails von Registrierung/Reset  |
| Mailpit SMTP  | mailpit:1025 (intern)          | SMTP-Ziel der App                |
| MySQL         | localhost:3315                 | Direkter DB-Zugriff (Dev)        |

### Test-Accounts (Dev-Stack, Bootstrap-Refactor)

Nach dem ersten Start sind folgende Test-Accounts vorhanden – die Passwörter
sind nur für die lokale Entwicklung gedacht und müssen produktiv geändert
oder gelöscht werden:

| Benutzer      | Rolle          | E-Mail                       | Passwort     |
|---------------|----------------|------------------------------|--------------|
| `RalphAdmin`  | Administrator  | `ralphadmin@example.com`     | `Test1234!`  |
| `RalphUser`   | Normal user    | `ralphuser@example.com`      | `Test1234!`  |

### Stoppen, Aktualisieren, Zurücksetzen

```bash
# Container stoppen
docker compose -f .docker/docker-compose.yml down

# Neu bauen (z. B. nach PHP-Version-Upgrade)
docker compose -f .docker/docker-compose.yml up -d --build

# Komplett zurücksetzen (alle Daten löschen!)
docker compose -f .docker/docker-compose.yml down -v
```

### Schnellstart ohne Docker

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard
composer install

# MySQL-DB anlegen und Schema importieren
mysql -u root -p -e "CREATE DATABASE PowerPHPBoard_v2 CHARACTER SET utf8mb4;"
mysql -u root -p PowerPHPBoard_v2 < install.sql

# config.inc.php anpassen (Zugangsdaten, SMTP)
# Dann Webserver auf das Projekt-Verzeichnis zeigen lassen,
# oder PHP-Builtin:
php -S localhost:8085
```

### Bugfix-Migration einspielen (für bestehende Installationen)

Wenn du eine ältere Version updatest, führe zusätzlich die Migration aus:

```bash
mysql -u <user> -p <dbname> < install_bugfix_2026-04-23.sql
```

Sie legt neue Tabellen für Password-Reset-Tokens und Rate-Limits an und setzt `UNIQUE` auf `username`.

### Deploy-Paket für den Live-Server

Die `.gitattributes` ist so konfiguriert, dass Dev-Artefakte (Tests, Docker, CI,
Dokumentation, statische Analyse-Configs, Setup-Skripte) beim `git archive`
automatisch ausgeschlossen werden. Ein sauberes Deploy-Paket erstellst du mit:

```bash
# Tarball für den Live-Server bauen (ohne Tests, Docker, Docs, CI ...)
git archive --format=tar.gz --prefix=powerphpboard/ HEAD > deploy.tar.gz

# Oder direkt ins Zielverzeichnis packen
git archive --format=tar HEAD | tar -C /var/www/html -xf -

# Auf dem Live-Server: nur Produktions-Dependencies installieren
composer install --no-dev --optimize-autoloader
```

Bei einem "dummen" Upload (FTP/SFTP) bitte die in `.gitattributes` mit
`export-ignore` markierten Verzeichnisse manuell NICHT mit hochladen.

---

## Inhaltsverzeichnis

1. [Features](#features)
2. [Frontend (Bootstrap 5)](#frontend-bootstrap-5)
3. [Voraussetzungen](#voraussetzungen)
4. [Konfiguration](#konfiguration)
5. [Projektstruktur](#projektstruktur)
6. [Core-Klassen](#core-klassen)
7. [Sicherheit](#sicherheit)
8. [Adminbereich](#adminbereich)
9. [Accessibility](#accessibility)
10. [BBCode-Referenz](#bbcode-referenz)
11. [Testing](#testing)
12. [Entwicklung](#entwicklung)
13. [Changelog](#changelog)
14. [Lizenz](#lizenz)
15. [Kontakt](#kontakt)

---

## Features

### Forum-Funktionen
- Foren mit Kategorien, Boards, Threads und Posts
- Benutzerregistrierung, Profilverwaltung, Signaturen, Biografie
- Moderatoren pro Board, Admin-Rolle
- Private Boards mit Passwortschutz
- BBCode, Smilies, optional HTML
- E-Mail-Versand (Registrierung, Passwort-Reset, User-zu-User)
- Mehrsprachig: Deutsch (Sie/Du), Englisch

### Sicherheit
- **CSRF-Schutz** auf allen POST-Formularen
- **Prepared Statements** (PDO) durchgängig
- **XSS-Prävention** (htmlspecialchars, Whitelist-strip_tags für Signaturen)
- **Argon2id** Passwort-Hashing mit Legacy-Migration
- **HttpOnly Session-Cookies**, SameSite=Strict, Secure-fertig
- **Rate-Limiting** gegen Brute-Force (Login, Passwort-Reset)
- **Token-basierter Passwort-Reset** (einmalige, zeitlich begrenzte Tokens)
- **Unified Error Messages** (keine User-Enumeration)
- **Username-UNIQUE** Constraint auf DB-Ebene
- **Admin-Guard** im Adminbereich (Early-Return mit Bootstrap-Alert)

### Technische Features
- PHP 8.4 Strict Types, Match Expressions, Named Arguments, readonly
- PSR-4 Autoloading, modulare `includes/`-Klassen
- 198 PHPUnit-Tests (137 Unit + 61 Feature)
- PHPStan Level 8, Psalm, PHP-CS-Fixer, Rector, Infection
- Docker-Compose Dev-Stack mit Mailpit und phpMyAdmin
- GitHub Actions CI

---

## Frontend (Bootstrap 5)

Seit Version 2.2.0 nutzt PowerPHPBoard ein durchgängiges **Bootstrap-5.3.3-Frontend**:

### Layout
- HTML5 (`<!DOCTYPE html>`, `<html lang="de">`, Viewport-Meta, Meta-Description)
- Responsives Layout über `container-xl`, `row`, `col-*`, `d-flex`, `gap-*`
- **Dunkle Navbar** für das Forum (`navbar-dark bg-dark`)
- **Rote Admin-Navbar** (`navbar-dark bg-danger`) zur klaren visuellen Trennung
- Footer mit `bg-dark text-light` und `link-light` für Kontrast

### Komponenten
- Cards (`card`, `card-header`, `card-body`, `card-footer`) für jede Section
- Bootstrap-Tabellen (`table`, `table-hover`, `table-striped`, `table-responsive`)
- Bootstrap-Forms (`form-label`, `form-control`, `form-select`, `form-check`,
  `invalid-feedback`, `form-text`)
- Bootstrap-Alerts (`alert alert-success/danger/warning/info`) für alle
  Status- und Fehlermeldungen
- Bootstrap-Buttons (`btn-primary`, `btn-success`, `btn-danger`,
  `btn-outline-secondary`, `btn-link`)
- Bootstrap-Pagination für Thread-Seiten
- Bootstrap-Breadcrumbs für Navigationspfade
- Bootstrap-Badges für Status (Administrator, Privat, Geschlossen, …)
- **Bootstrap-Icons** (`bi-*`) statt Legacy-GIF-Icons für Status-Anzeigen

### Validierung
- Server-seitige Validierung bleibt führend (`Validator`-Klasse)
- Bootstrap-Client-Validierung als Ergänzung (`needs-validation`/`was-validated`)
- Hilfetexte (`form-text`) bei jedem nicht-trivialen Eingabefeld
- Pflichtfelder mit `*` markiert, Beschreibung über `aria-describedby`

### CDN + ppb.css
- Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 via jsdelivr CDN mit SRI-Hashes
- Schlanke `ppb.css` für projektspezifische Anpassungen
  (Link-Kontrast für WCAG-AA, BBCode-Quote-Styling, Wortumbruch in Posts)

---

## Voraussetzungen

| Komponente | Version  | Hinweis                                          |
|------------|----------|--------------------------------------------------|
| PHP        | 8.4+     | mit `pdo_mysql`, `mbstring`, `openssl`           |
| MySQL      | 8.0+     | oder MariaDB 10.5+                               |
| Composer   | 2.0+     | Autoloader und Dev-Tooling                       |
| Browser    | modern   | Bootstrap 5.3 unterstützt alle aktuellen Browser |
| SMTP       | -        | z. B. Mailpit im Dev-Setup, produktiv SMTP-Relay |

---

## Konfiguration

### Environment-Variablen

| Variable         | Beschreibung           | Standard                      |
|------------------|------------------------|-------------------------------|
| `PPB_DB_HOST`    | Datenbank-Host         | `localhost`                   |
| `PPB_DB_USER`    | Datenbank-Benutzer     | `root`                        |
| `PPB_DB_PASS`    | Datenbank-Passwort     | (leer)                        |
| `PPB_DB_NAME`    | Datenbank-Name         | `PowerPHPBoard_v2`            |
| `PPB_MAIL_HOST`  | SMTP-Host              | `mailpit`                     |
| `PPB_MAIL_PORT`  | SMTP-Port              | `1025`                        |
| `PPB_MAIL_FROM`  | Absender (Fallback)    | `noreply@powerphpboard.local` |
| `PPB_DEBUG`      | Debug-Modus            | `false`                       |

### `config.inc.php`

```php
<?php
declare(strict_types=1);

$mysql = [
    'server'   => getenv('PPB_DB_HOST') ?: 'localhost',
    'user'     => getenv('PPB_DB_USER') ?: 'root',
    'password' => getenv('PPB_DB_PASS') ?: '',
    'database' => getenv('PPB_DB_NAME') ?: 'PowerPHPBoard_v2',
];

$mail = [
    'host' => getenv('PPB_MAIL_HOST') ?: 'mailpit',
    'port' => (int) (getenv('PPB_MAIL_PORT') ?: 1025),
    'from' => getenv('PPB_MAIL_FROM') ?: 'noreply@powerphpboard.local',
];

define('PPB_VERSION', '2.2.0');
define('PPB_SESSION_LIFETIME', 3600);
define('PPB_CSRF_ENABLED', true);
define('PPB_DEBUG', (bool) (getenv('PPB_DEBUG') ?: false));
```

Nach Änderungen am Config-Schema nicht vergessen, Zugangsdaten der Produktions-Instanz entsprechend zu setzen.

### Sprache

Die UI-Sprache wird in der Datenbank gesetzt: Tabelle `ppb_config`, Spalte
`language`. Möglich sind `English`, `Deutsch-Sie`, `Deutsch-Du`. Default
seit dem Bootstrap-Refactor ist `Deutsch-Du`.

```sql
UPDATE ppb_config SET language = 'Deutsch-Du' WHERE id = 1;
```

---

## Projektstruktur

```
PowerPHPBoard/
├── .docker/                       # Dev-Stack (Apache+PHP, MySQL, Mailpit, phpMyAdmin)
├── .github/workflows/             # CI
├── admin/                         # Admin-Panel (Bootstrap, eigene Navbar)
│   ├── header.inc.php             # Bootstrap-Layout mit bg-danger Navbar + Admin-Guard
│   ├── footer.inc.php
│   ├── index.php                  # Dashboard mit Stats-Tiles
│   ├── general.php                # Allgemeine Einstellungen
│   ├── boards.php / addboard.php / editboard.php
│   ├── addboardcategory.php / editboardcategory.php
│   ├── boarddesign.php
│   └── user.php / adduser.php / edituser.php
├── docs/                          # Audit-Berichte, Pläne, Dokumentation
├── images/                        # Smilies, UI-Grafiken (Icons jetzt via Bootstrap Icons CDN)
├── inc/                           # HTML5-Layout-Templates (öffnen/schließen Body+Bootstrap-CSS/JS)
│   ├── header.ppb                 # <!DOCTYPE> + <head> mit Bootstrap-CDN
│   └── footer.ppb                 # Bootstrap-JS + needs-validation-Init
├── includes/                      # Core-Klassen (PSR-4, Namespace PowerPHPBoard\)
│   ├── CSRF.php                   # CSRF-Tokens
│   ├── Database.php               # PDO-Wrapper (Singleton)
│   ├── DatabaseRateLimitStorage.php
│   ├── ErrorHandler.php           # Error + Security-Logging
│   ├── Mailer.php                 # SMTP-Versand direkt zu Mailpit/SMTP-Relay
│   ├── RateLimiter.php            # Fenster/Lock-basiertes Rate-Limit
│   ├── RateLimiterStorage.php     # Interface für verschiedene Backends
│   ├── Security.php               # escape, hashPassword, verifyPassword, isValidEmail …
│   ├── Session.php                # Session-Verwaltung (login/logout, regenerate)
│   ├── TextFormatter.php          # BBCode + Smilies
│   └── Validator.php              # Username-, Längen-, Passwortregeln
├── logs/                          # PHP- und Security-Logs (nicht versioniert)
├── tests/
│   ├── Unit/                      # 137 Unit-Tests
│   └── Feature/                   # 61 Feature-/Integrations-Tests
├── config.inc.php                 # Zentrale Konfiguration (DB, Mail, Konstanten)
├── header.inc.php / footer.inc.php
│                                  # Bootstrap-Wrapper für jedes Frontend-Skript
├── functions.inc.php              # default_error (Bootstrap-Card-Alert), getrank,
│                                  # getpages (Bootstrap-Pagination), ppb_alert,
│                                  # ppb_onoff_label, render_action_button
├── ppb.css                        # Schlanke projektspezifische Styles (Link-Kontrast etc.)
├── index.php                      # Startseite, Boardliste (Bootstrap-Tabelle)
├── login.php / logout.php
├── register.php                   # Registrierung (CSRF, Validator, Mailer)
├── profile.php                    # Profil-Edit (Re-Auth für sensible Änderungen)
├── sendpassword.php               # Reset-Link anfordern (Token-Flow)
├── resetpassword.php              # Reset via Token einlösen
├── showboard.php / showthread.php
├── newthread.php / newpost.php / editpost.php  # Posting (nur Session)
├── showprofile.php / showip.php / sendmail.php / statistics.php
├── bbcode.php / smilies.php       # Referenz-Seiten
├── english.inc.php                # Sprachdateien
├── deutsch-sie.inc.php
├── deutsch-du.inc.php
├── install.sql                    # DB-Schema + Default-Daten
├── install_bugfix_2026-04-23.sql  # Migration für bestehende Installationen
├── composer.json / composer.lock
└── phpunit.xml / phpstan.neon / psalm.xml / rector.php / infection.json5
```

---

## Core-Klassen

### `Database` (Singleton PDO-Wrapper)

```php
use PowerPHPBoard\Database;

$db   = Database::getInstance($mysql);
$user = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$id]);
$rows = $db->fetchAll('SELECT * FROM ppb_posts WHERE boardid = ?', [$boardId]);
$db->query('UPDATE ppb_users SET username = ? WHERE id = ?', [$name, $id]);
$db->beginTransaction();
$db->commit();
$db->rollBack();
```

### `Security` (Escape, Hashing, Validierung)

```php
use PowerPHPBoard\Security;

Security::escape($input);                // htmlspecialchars wrapper
Security::isValidEmail($email);          // RFC-konforme Prüfung
Security::hashPassword($pw);             // Argon2id (mit bcrypt-Fallback)
Security::verifyPassword($pw, $hash);    // stützt Legacy-Base64 (Migration)
Security::needsRehash($hash);            // true = alter Hash, re-hashen
Security::getString($key, 'POST');       // sicherer Zugriff auf $_POST / $_GET / $_REQUEST
Security::getInt($key, 'REQUEST', 0);
```

> ⚠️ **Wichtig:** `$lang_*`-Strings dürfen **nicht** durch `Security::escape()`
> umschlossen werden. Sie enthalten beabsichtigtes HTML (`<b>`, `<small>`,
> `&uuml;` etc.) und kommen aus den vertrauenswürdigen Sprachdateien.
> Doppeltes Escapen führt zu sichtbaren `&lt;b&gt;`-Tags im Browser.

### `CSRF`

```php
use PowerPHPBoard\CSRF;

echo CSRF::getTokenField();              // <input type="hidden" name="csrf_token" ...>
if (!CSRF::validateFromPost()) { /* 400 */ }
CSRF::regenerate();                      // nach Login/sensiblem Change
```

### `Session`

```php
use PowerPHPBoard\Session;

Session::start();
Session::login((int) $userId);
if (Session::isLoggedIn()) { $uid = Session::getUserId(); }
Session::set('theme', 'dark');
Session::get('theme', 'light');
Session::logout();
```

### `Validator`

```php
use PowerPHPBoard\Validator;

Validator::isValidUsername('alice');       // [A-Za-z0-9._-], 2-50 Zeichen
Validator::isStrongPassword('Passw0rd!');  // min. 8 Zeichen
Validator::withinLength($bio, Validator::BIOGRAPHY_MAX); // 1000
Validator::withinLength($sig, Validator::SIGNATURE_MAX); // 500
Validator::withinLength($post, Validator::POST_MAX);     // 65000
```

### `RateLimiter`

```php
use PowerPHPBoard\{RateLimiter, DatabaseRateLimitStorage};

$rl = new RateLimiter(
    new DatabaseRateLimitStorage($db),
    maxAttempts: 10,    // nach 10 Fehlversuchen …
    windowSeconds: 900, // … innerhalb von 15 Minuten …
    lockSeconds: 900    // … für 15 Minuten sperren.
);
$ident = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!$rl->check('login', $ident)) { /* blockiert */ }
$rl->recordFailure('login', $ident);
$rl->recordSuccess('login', $ident);
```

Das Interface `RateLimiterStorage` erlaubt das Austauschen des Backends (z. B. Redis).

### `Mailer`

Minimalistischer SMTP-Client – verbindet sich direkt per `stream_socket_client` zu
einem SMTP-Server (in Dev: Mailpit auf Port 1025). Keine externen Abhängigkeiten.

```php
use PowerPHPBoard\Mailer;

$mailer = new Mailer(
    smtpHost: $mail['host'] ?? 'mailpit',
    smtpPort: (int) ($mail['port'] ?? 1025),
);

$mailer->send(
    to:      'alice@example.com',
    from:    'noreply@powerphpboard.local',
    subject: 'Willkommen!',
    body:    "Hallo Alice,\n\ndein Account wurde angelegt."
);
```

Versand schlägt nicht stumm fehl – Fehler landen im `error_log`.

### `TextFormatter` (BBCode + Smilies)

```php
use PowerPHPBoard\TextFormatter;

echo TextFormatter::formatPost(
    $text,
    $settings['bbcode']   ?? 'ON',
    $settings['smilies']  ?? 'ON',
    $settings['htmlcode'] ?? 'OFF' // in Signaturen hart auf OFF erzwungen!
);
```

### Helper-Funktionen in `functions.inc.php`

```php
// Bootstrap-Card-Alert für Fehler-Seiten (legacy-API mit Bootstrap-Output)
default_error($message, $backUrl, $backText);

// Generischer Bootstrap-Alert (success/info/warning/danger)
echo ppb_alert('Profil gespeichert.', 'success', 'Status');

// Bootstrap-Pagination für Thread-Seiten
echo getpages($threadId, $db, $currentOffset);

// ON/OFF/YES/NO-Setting in deutsche "an"/"aus"-Anzeige
echo ppb_onoff_label($settings['htmlcode'] ?? 'OFF'); // → "an" oder "aus"
```

---

## Sicherheit

Siehe [docs/SECURITY.md](docs/SECURITY.md) für Details. Kurzfassung:

- **Passwörter**: Argon2id (mit Pfeffer durch globale Konstante, falls
  konfiguriert), automatische Migration aus altem Base64.
- **CSRF**: Token pro Session, regeneriert bei Login und sensiblen Änderungen.
- **XSS**: `htmlspecialchars` + Whitelist-`strip_tags` für Signaturen,
  Post-Rendering über `TextFormatter::formatPost`; Signaturen immer mit
  `htmlcode=OFF`.
- **SQL-Injection**: Ausschließlich Prepared Statements. Keine dynamischen Queries.
- **Session**: HttpOnly + SameSite=Strict + strict_mode, Regenerate bei Login/Logout.
- **Rate-Limits**: Login (10 / 15 min / 15 min Lock),
  Passwort-Reset (5 / 1 h / 1 h Lock).
- **Passwort-Reset**: Token (32 Byte random, SHA-256 gehasht in DB),
  1 h Gültigkeit, einmalig einlösbar. Keine Preisgabe, ob eine E-Mail existiert.
- **Logout**: nur per POST mit CSRF-Token (keine GET-CSRF-Angriffe mehr).
- **Adminbereich**: Eigener Guard mit Early-Return-Bootstrap-Alert.
- **Lösch-Funktionen** (Boards, Kategorien): Mehrstufige Bestätigung im
  "Gefahrenzone"-Card. Kategorien können nur gelöscht werden, wenn sie
  keine Boards mehr enthalten. Boards mit Threads erfordern eine separate
  zweite Bestätigungs-Checkbox, bevor alle Beiträge unwiderruflich entfernt werden.

---

## Adminbereich

- Erreichbar unter `/admin/` für Nutzer mit `status='Administrator'`.
- Eingeloggte Administratoren sehen im Frontend-Header zusätzlich einen
  **"Adminbereich"**-Link mit Schild-Icon (`link-warning`).
- Nicht-Administratoren erhalten beim Aufruf eine `alert alert-danger`-Card
  und werden zum Login geschickt.

### Funktionen

| Bereich              | Beschreibung                                                          |
|----------------------|-----------------------------------------------------------------------|
| **Übersicht**        | Stats-Tiles (Nutzer / Boards / Threads / Beiträge), Schnellzugriffe   |
| **Allgemein**        | Boardtitel, URL, Admin-E-Mail, Sprache, Feature-Schalter (HTML/BBCode/Smilies) |
| **Boards**           | Kategorien + Boards anlegen, bearbeiten, **löschen** mit Bestätigung  |
| **Nutzer**           | Komplette Liste mit Filter-Tabs (Alle / Administratoren / Normale Nutzer / Deaktiviert), Pagination (25 / Seite), Volltext-Suche per `LIKE` |

### Test-Accounts

Im Dev-Stack sind nach dem ersten Start folgende Accounts verfügbar:

| Benutzer      | Rolle          | E-Mail                       | Passwort     |
|---------------|----------------|------------------------------|--------------|
| `RalphAdmin`  | Administrator  | `ralphadmin@example.com`     | `Test1234!`  |
| `RalphUser`   | Normal user    | `ralphuser@example.com`      | `Test1234!`  |

> Für Produktion: löschen oder Passwörter ändern.

---

## Accessibility

Lighthouse-Audit (Desktop) für `/showthread.php` mit Bootstrap-Refactor:

| Kategorie         | Score |
|-------------------|-------|
| Accessibility     | 100   |
| Best Practices    | 100   |
| SEO               | 100   |
| Agentic Browsing  | 100   |

Was wir dafür gemacht haben:

- Semantisches HTML5: ein `<h1>` pro Seite, `<h2>` für Untertitel,
  `<nav>` mit `aria-label`, `<main>`, `<header>`, `<footer>`, `<aside>`.
- `<label for>` an jedem Eingabefeld; `aria-describedby` für Hilfetexte.
- `<fieldset>/<legend>` für Radio-Gruppen.
- `visually-hidden` Texte für Icon-only-Status, sichtbare `title`-Attribute.
- `<dl class="row">` mit Bootstrap-Spalten statt inline-`<dt>/<dd>`-Gemurkse.
- Kontrast-Optimierungen in `ppb.css`:
  - Breadcrumb- und Card-Body-Links: `#0a58ca` (Bootstrap-Hover-Blau,
    ~5.6:1 Kontrast auf `bg-body-tertiary`) statt Default-`#0d6efd` (~4.26:1).
  - Admin-Status: `text-danger-emphasis` statt `text-danger`.
- Meta-Description in `inc/header.ppb` für SEO/Agentic Browsing.
- Form-Validation client-seitig (Bootstrap `was-validated`),
  serverseitig immer Validator-Klasse.

---

## BBCode-Referenz

| Tag                            | Ergebnis                                    |
|--------------------------------|---------------------------------------------|
| `[b]fett[/b]`                  | **fett**                                    |
| `[i]kursiv[/i]`                | *kursiv*                                    |
| `[u]unterstrichen[/u]`         | <u>unterstrichen</u>                        |
| `[url]https://...[/url]`       | Link                                        |
| `[url=https://...]Text[/url]`  | Link mit Text                               |
| `[img]https://.../img.png[/img]`| Bild                                       |
| `[quote]Text[/quote]`          | Zitat                                       |
| `[code]... [/code]`            | Code-Block                                  |
| `[color=#ff0000]rot[/color]`   | Farbiger Text                               |

Smilies: `:)` `:P` `;)` `:D` `:(` `:o` `:cool:` `:eek:` `:mad:` `:confused:` `:rolleyes:`

Siehe auch `/bbcode.php` und `/smilies.php` im laufenden Forum.

---

## Testing

```bash
# Alle Tests
composer test

# Nur Unit
vendor/bin/phpunit --testsuite Unit

# Nur Feature (braucht DB)
vendor/bin/phpunit --testsuite Feature

# Coverage (Xdebug erforderlich)
composer test-coverage
```

Aktueller Stand: **198 Tests** (137 Unit + 61 Feature), **362 Assertions**,
0 Failures.

Statische Analyse:

```bash
composer phpstan   # PHPStan Level 8
composer psalm     # Psalm
composer phpmd     # PHP Mess Detector
composer cs-check  # PHP-CS-Fixer (dry-run)
composer cs-fix    # PHP-CS-Fixer (apply)
composer rector-dry
composer qa        # alles in Folge
```

---

## Entwicklung

### Docker-Workflow

```bash
cd .docker
docker compose up -d            # starten
docker compose logs -f web      # Logs beobachten
docker compose exec web bash    # Shell im Container
docker compose down             # stoppen (Daten bleiben)
docker compose down -v          # stoppen + alle Daten löschen
```

### Git-Workflow

```bash
# Branch für neue Arbeit
git checkout -b feature/xyz

# Lokal testen
composer qa

# Pushen + PR
git push -u origin feature/xyz
gh pr create --title "..." --body "..."
```

### PHP-CLI im Container

```bash
docker compose exec web php -v
docker compose exec web php -l includes/Validator.php
docker compose exec web composer test
```

### Frontend anpassen

Das gesamte Layout liegt in `inc/header.ppb` (Doctype + `<head>` mit Bootstrap-CDN)
und `inc/footer.ppb` (Bootstrap-JS + needs-validation-Init). Page-spezifischer
Wrapper kommt aus `header.inc.php` / `footer.inc.php` (Navbar, Breadcrumb,
Board-Header, Footer).

Eigene CSS-Anpassungen nur in `ppb.css` machen (klein und dokumentiert).
**Keine** globalen `a { color: ... }`-Regeln, weil sonst die Navbar-Linkfarben
auf dunklem Hintergrund unleserlich werden.

---

## Changelog

### Version 2.2.0 – 2026-05-10 (Bootstrap-5-Frontend)

**Komplettes Frontend-Refactoring** – die gesamte HTML-Ausgabe wurde in
ein konsistentes Bootstrap-5.3.3-Template überführt (siehe `.ralph-loop/` für
das Audit-Protokoll).

#### Neue Features
- HTML5 + Bootstrap 5.3.3 + Bootstrap Icons via CDN mit SRI
- Komplette Sprachvereinheitlichung auf Deutsch (Default `Deutsch-Du`)
- Test-Accounts `RalphAdmin` / `RalphUser` (passwort `Test1234!`)
- Helper-Funktion `ppb_onoff_label()` für deutsche an/aus-Anzeige
- Helper-Funktion `ppb_alert()` für Bootstrap-Alerts
- Bootstrap-Setup-Layout für `create-admin.php` (Setup-Skript)
- Adminbereich mit eigener `bg-danger`-Navbar und Admin-Guard
- **Adminbereich-Link** im Frontend-Header (gelb, Schild-Icon) – nur für
  eingeloggte Administratoren sichtbar
- **Komplette Nutzerliste** in `admin/user.php` mit Filter-Tabs
  (Alle / Administratoren / Normale Nutzer / Deaktiviert), Pagination und
  Volltext-Suche
- **Lösch-Funktion** für Boards und Kategorien: "Gefahrenzone"-Card mit
  kontextueller Bestätigung, Cascade-Delete für Threads/Posts/Visits beim
  Board-Löschen, Schutz vor Kategorie-Löschung wenn Boards drin sind
- Klare Beschriftungen + Info-Alerts für Legacy-Design-Felder
  (`Hg 1/2/3` → "Tabelle Hintergrund 1/2/3" + Erklärung "Helle Zeile" etc.;
  `Header-Datei` → "Eigenes Header-Template" + Hilfetext;
  `Button "Neuer Thread"` → "Bild für 'Neuer Thread'-Button" + Größenhinweis)
- Color-Preview-Squares neben jedem Hex-Farb-Input
- Footer auf https://www.powerscripts.org verlinkt (statt GitHub + MIT-Link)
- Default-Boardtitel: `PowerPHPBoard 2.2.0` (statt `1.0 BETA`)

#### Refactor
- 18 Forum-PHP-Dateien (index, showboard, showthread, login, logout,
  register, profile, showprofile, newthread, newpost, editpost,
  sendpassword, resetpassword, sendmail, showip, bbcode, smilies, statistics)
- 13 Admin-Dateien (alle in `admin/`)
- `header.inc.php`, `footer.inc.php`, `functions.inc.php`
- HTML5-Templates in `inc/header.ppb`/`inc/footer.ppb`
- `ppb.css` schlank, ohne Legacy-Link-Color-Override

#### Sicherheits-Fixes
- `editpost.php`: `editpost`-Parameter wird aus REQUEST gelesen (war fälschlich
  POST), Edit-Submit funktioniert jetzt
- Doppeltes HTML-Escapen von Lang-Strings entfernt (zeigte `<b>` als Text)
- Operator-Präzedenz-Bug in 5 Stellen behoben (`('<a>' . $lang_x ?? 'Y' . '</a>')`
  produzierte offene `<a>`-Tags)

#### Sprache & Lesbarkeit
- Alle Transliterationen (`ae`/`oe`/`ue`/`ss`) durch echte Umlaute ersetzt
  (ä/ö/ü/ß) – konsequent in allen geänderten Dateien und Sprachfiles
- "eMail Adresse" → "E-Mail-Adresse" (alle Sprachfiles)
- "Forumpasswort" → "Forum-Passwort"
- "Letzer Beitrag" → "Letzter Beitrag" (Tippfehler-Fix)
- "Anzeigen" (Spalte für Views) → "Aufrufe"
- ON/OFF in Forms → an/aus
- yes/no-Fallbacks → ja/nein
- "Zur&uuml;cksetzten" → "Zur&uuml;cksetzen" (`$lang_reset`)

#### Accessibility & SEO
- Lighthouse Desktop: 100/100/100/100
- WCAG-AA-Kontrast überall (Breadcrumb-Links, Admin-Badges)
- Korrekte Heading-Hierarchie (`<h1>` einmal pro Seite, `<h2>` für Sektionen)
- `<dl class="row">` mit semantisch sauberen `<dt>`/`<dd>`-Paaren
- Meta-Description ergänzt
- Mobile responsive (Hamburger-Navbar, responsive Tabellen)

#### Tests
- 5 vorbestehende PHPUnit-Failures (Test-Fixture-Bugs in
  `FeatureTestCase::createTestUser` – falsche Spaltennamen) gefixt
- 198/198 Tests grün (137 Unit + 61 Feature, 362 Assertions)

### Version 2.1.0 – 2026-04-24 (Bugfix-Release)

**Basierend auf dem Userbereich-Audit vom 2026-04-23 (siehe `docs/2026-04-23-Userbereichs-bugs.md`).**
Alle 18 dokumentierten Bugs behoben.

#### Neue Features
- `Validator`-Klasse (Username-Regex, Passwortregeln, Length-Checks)
- `RateLimiter` + `DatabaseRateLimitStorage` (Login- und Reset-Bruteforce-Schutz)
- `Mailer`-Klasse (SMTP direkt, ersetzt stummes `@mail()`)
- `resetpassword.php` (Token-basierter Passwort-Reset)
- `install_bugfix_2026-04-23.sql` (Migration für Username-UNIQUE, Tokens, Rate-Limits)

#### Sicherheits-Fixes
- Stored XSS via Signatur verhindert (Whitelist-`strip_tags` + `htmlcode=OFF` im Rendering)
- Passwort-Reset überschreibt Passwörter nicht mehr sofort – Token-Flow
- Logout nur noch per POST mit CSRF-Token
- Login/Reset liefern vereinheitlichte Fehlermeldungen (keine User-Enumeration)
- Brute-Force-Schutz auf Login (10/15min) und Reset (5/1h)
- Re-Authentifizierung per aktuellem Passwort bei E-Mail-/Passwort-Wechsel
- UNIQUE-Index auf `ppb_users.username`

#### UX / Workflow
- Registrierungsformular liest `acception` aus REQUEST (nicht mehr nur GET) –
  Registrierung funktioniert jetzt ohne Workaround
- Profilseite: Passwortfelder optional (leer = keine Änderung),
  ICQ-Default-`0` wird leer angezeigt
- Posting: nur per Session (kein paralleler E-Mail+Password-Auth-Pfad mehr)
- Längen-Checks mit klaren Fehlermeldungen (statt stummen PDO-Exceptions)
- Login-Erfolgstext entfernt Legacy-"360-Tage-Cookie"-Hinweis

### Version 2.0.0 – 2026-01 (PHP 8.4 Migration)
- Komplette Modernisierung auf PHP 8.4
- PHPUnit-Testsuite eingeführt
- PHPStan Level 8, Psalm, PHP-CS-Fixer, Rector, Infection
- GitHub Actions CI/CD
- Zentrales Error-Handling, Security-Event-Logging
- PSR-4 Autoloading
- Session-basierte Authentifizierung statt Cookie mit Passwort

### Version 1.x – 2001-2009
- Ursprüngliche Entwicklung von Stefan "BFG" Kramer
- PHP 4/5, `mysql_*`-Funktionen, Basis-Forum

---

## Lizenz

MIT License

Copyright (c) 2001-2009 Stefan "BFG" Kramer (PowerScripts)
Copyright (c) 2026 Nico Schubert / PowerScripts

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

## Kontakt

**SchubertMedia**
Inhaber: Nico Schubert
Stauffenbergallee 57
D-99085 Erfurt

- Telefon: +49 (0) 3612 3002247 (Mo.–Fr. 9–12 und 13–18 Uhr)
- Telefax: +49 (0) 3612 3004636
- E-Mail: [info@schubertmedia.de](mailto:info@schubertmedia.de)

Projekte:
- [https://www.powerscripts.org](https://www.powerscripts.org) – PowerScripts Hauptseite
- [https://www.powerscripts.org/projects-3.html](https://www.powerscripts.org/projects-3.html) – Alle Projekte
- [https://github.com/schubertnico/PowerPHPBoard](https://github.com/schubertnico/PowerPHPBoard) – Quellcode

Bug-Reports und Feature-Requests bitte über die GitHub-Issues:
[https://github.com/schubertnico/PowerPHPBoard/issues](https://github.com/schubertnico/PowerPHPBoard/issues)
