# PowerPHPBoard

Ein sicheres, leichtgewichtiges PHP-Forum-System fuer **PHP 8.4+**.
Urspruenglich entwickelt 2001-2009 von Stefan "BFG" Kramer, komplett modernisiert 2026.

- **Projekt-Website:** [https://www.powerscripts.org](https://www.powerscripts.org)
- **Projektuebersicht:** [https://www.powerscripts.org/projects-3.html](https://www.powerscripts.org/projects-3.html)
- **Repository:** [https://github.com/schubertnico/PowerPHPBoard](https://github.com/schubertnico/PowerPHPBoard)

---

## Schnellstart (Docker, empfohlen)

Du brauchst nur **Git**, **Composer** und **Docker Desktop** (oder docker compose v2).

```bash
# 1. Repository klonen
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard

# 2. Dev-Dependencies fuer Tests/Tooling installieren
composer install

# 3. Container starten (Forum + MySQL + Mailpit + phpMyAdmin)
cd .docker
docker compose up -d --build
cd ..

# 4. Im Browser oeffnen
#    http://localhost:8085  -> Forum
```

Beim ersten Start wird `install.sql` automatisch in die MySQL-DB geladen.
Ein Admin-Account "Gott" wird angelegt (Passwort steht in `install.sql` - nach erstem Login sofort aendern!).

| Service       | URL                            | Zweck                            |
|---------------|--------------------------------|----------------------------------|
| Forum         | http://localhost:8085          | Hauptanwendung                   |
| phpMyAdmin    | http://localhost:8088          | DB-Verwaltung                    |
| Mailpit (UI)  | http://localhost:8032          | E-Mails von Registrierung/Reset  |
| Mailpit SMTP  | mailpit:1025 (intern)          | SMTP-Ziel der App                |
| MySQL         | localhost:3315                 | Direkter DB-Zugriff (Dev)        |

### Stoppen, Aktualisieren, Zuruecksetzen

```bash
# Container stoppen
docker compose -f .docker/docker-compose.yml down

# Neu bauen (z. B. nach PHP-Version-Upgrade)
docker compose -f .docker/docker-compose.yml up -d --build

# Komplett zuruecksetzen (alle Daten loeschen!)
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

### Bugfix-Migration einspielen (fuer bestehende Installationen)

Wenn du eine aeltere Version updatest, fuehre zusaetzlich die Migration aus:

```bash
mysql -u <user> -p <dbname> < install_bugfix_2026-04-23.sql
```

Sie legt neue Tabellen fuer Password-Reset-Tokens und Rate-Limits an und setzt `UNIQUE` auf `username`.

### Deploy-Paket fuer den Live-Server

Die `.gitattributes` ist so konfiguriert, dass Dev-Artefakte (Tests, Docker, CI,
Dokumentation, statische Analyse-Configs, Setup-Skripte) beim `git archive`
automatisch ausgeschlossen werden. Ein sauberes Deploy-Paket erstellst du mit:

```bash
# Tarball fuer den Live-Server bauen (ohne Tests, Docker, Docs, CI ...)
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
2. [Voraussetzungen](#voraussetzungen)
3. [Konfiguration](#konfiguration)
4. [Projektstruktur](#projektstruktur)
5. [Core-Klassen](#core-klassen)
6. [Sicherheit](#sicherheit)
7. [BBCode-Referenz](#bbcode-referenz)
8. [Testing](#testing)
9. [Entwicklung](#entwicklung)
10. [Changelog](#changelog)
11. [Lizenz](#lizenz)
12. [Kontakt](#kontakt)

---

## Features

### Forum-Funktionen
- Foren mit Kategorien, Boards, Threads und Posts
- Benutzerregistrierung, Profilverwaltung, Signaturen, Biography
- Moderatoren pro Board, Admin-Rolle
- Private Boards mit Passwortschutz
- BBCode, Smilies, optional HTML
- E-Mail-Versand (Registrierung, Passwort-Reset, User-zu-User)
- Mehrsprachig: Deutsch (Sie/Du), Englisch

### Sicherheit
- **CSRF-Schutz** auf allen POST-Formularen
- **Prepared Statements** (PDO) durchgaengig
- **XSS-Praevention** (htmlspecialchars, Whitelist-strip_tags fuer Signaturen)
- **Argon2id** Passwort-Hashing mit Legacy-Migration
- **HttpOnly Session-Cookies**, SameSite=Strict, Secure-fertig
- **Rate-Limiting** gegen Brute-Force (Login, Passwort-Reset)
- **Token-basierter Passwort-Reset** (einmalige, zeitlich begrenzte Tokens)
- **Unified Error Messages** (keine User-Enumeration)
- **Username-UNIQUE** Constraint auf DB-Ebene

### Technische Features
- PHP 8.4 Strict Types, Match Expressions, Named Arguments, readonly
- PSR-4 Autoloading, modulare `includes/`-Klassen
- 192 PHPUnit-Tests (137 Unit + 55 Feature)
- PHPStan Level 8, Psalm, PHP-CS-Fixer, Rector, Infection
- Docker-Compose Dev-Stack mit Mailpit und phpMyAdmin
- GitHub Actions CI

---

## Voraussetzungen

| Komponente | Version  | Hinweis                                   |
|------------|----------|-------------------------------------------|
| PHP        | 8.4+     | mit `pdo_mysql`, `mbstring`, `openssl`    |
| MySQL      | 8.0+     | oder MariaDB 10.5+                        |
| Composer   | 2.0+     | Autoloader und Dev-Tooling                |
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

define('PPB_VERSION', '2.1.0');
define('PPB_SESSION_LIFETIME', 3600);
define('PPB_CSRF_ENABLED', true);
define('PPB_DEBUG', (bool) (getenv('PPB_DEBUG') ?: false));
```

Nach Aenderungen am Config-Schema nicht vergessen, Zugangsdaten der Produktions-Instanz entsprechend zu setzen.

---

## Projektstruktur

```
PowerPHPBoard/
├── .docker/                       # Dev-Stack (Apache+PHP, MySQL, Mailpit, phpMyAdmin)
├── .github/workflows/             # CI
├── admin/                         # Admin-Panel
├── docs/                          # Audit-Berichte, Plaene, Dokumentation
├── images/                        # Smilies, UI-Grafiken
├── inc/                           # Legacy-Header/Footer-Templates
├── includes/                      # Core-Klassen (PSR-4, Namespace PowerPHPBoard\)
│   ├── CSRF.php                   # CSRF-Tokens
│   ├── Database.php               # PDO-Wrapper (Singleton)
│   ├── DatabaseRateLimitStorage.php
│   ├── ErrorHandler.php           # Error + Security-Logging
│   ├── Mailer.php                 # SMTP-Versand direkt zu Mailpit/SMTP-Relay
│   ├── RateLimiter.php            # Fenster/Lock-basiertes Rate-Limit
│   ├── RateLimiterStorage.php     # Interface fuer verschiedene Backends
│   ├── Security.php               # escape, hashPassword, verifyPassword, isValidEmail ...
│   ├── Session.php                # Session-Verwaltung (login/logout, regenerate)
│   ├── TextFormatter.php          # BBCode + Smilies
│   └── Validator.php              # Username-, Laengen-, Passwortregeln
├── logs/                          # PHP- und Security-Logs (nicht versioniert)
├── tests/
│   ├── Unit/                      # 137 Unit-Tests
│   └── Feature/                   # 55 Feature-/Integrations-Tests
├── config.inc.php                 # Zentrale Konfiguration (DB, Mail, Konstanten)
├── header.inc.php / footer.inc.php
├── functions.inc.php              # Hilfsfunktionen (default_error, getrank, ...)
├── index.php                      # Startseite, Boardlist
├── login.php / logout.php
├── register.php                   # Registrierung (CSRF, Validator, Mailer)
├── profile.php                    # Profil-Edit (Re-Auth fuer sensible Aenderungen)
├── sendpassword.php               # Reset-Link anfordern (Token-Flow)
├── resetpassword.php              # Reset via Token einloesen
├── showboard.php / showthread.php
├── newthread.php / newpost.php / editpost.php  # Posting (nur Session)
├── showprofile.php / showip.php / sendmail.php / statistics.php
├── bbcode.php / smilies.php       # Referenz-Seiten
├── english.inc.php                # Sprachdateien
├── deutsch-sie.inc.php
├── deutsch-du.inc.php
├── install.sql                    # DB-Schema + Default-Daten
├── install_bugfix_2026-04-23.sql  # Migration fuer bestehende Installationen
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
Security::isValidEmail($email);          // RFC-konforme Pruefung
Security::hashPassword($pw);             // Argon2id (mit bcrypt-Fallback)
Security::verifyPassword($pw, $hash);    // stuetzt Legacy-Base64 (Migration)
Security::needsRehash($hash);            // true = alter Hash, re-hashen
Security::getString($key, 'POST');       // sicherer Zugriff auf $_POST / $_GET / $_REQUEST
Security::getInt($key, 'REQUEST', 0);
```

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

### `Validator` (neu, 2026-04-24)

```php
use PowerPHPBoard\Validator;

Validator::isValidUsername('alice');       // [A-Za-z0-9._-], 2-50 Zeichen
Validator::isStrongPassword('Passw0rd!');  // min. 8 Zeichen
Validator::withinLength($bio, Validator::BIOGRAPHY_MAX); // 1000
Validator::withinLength($sig, Validator::SIGNATURE_MAX); // 500
Validator::withinLength($post, Validator::POST_MAX);     // 65000
```

### `RateLimiter` (neu, 2026-04-24)

```php
use PowerPHPBoard\{RateLimiter, DatabaseRateLimitStorage};

$rl = new RateLimiter(
    new DatabaseRateLimitStorage($db),
    maxAttempts: 10,    // nach 10 Fehlversuchen ...
    windowSeconds: 900, // ... innerhalb von 15 Minuten ...
    lockSeconds: 900    // ... fuer 15 Minuten sperren.
);
$ident = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!$rl->check('login', $ident)) { /* blockiert */ }
$rl->recordFailure('login', $ident);
$rl->recordSuccess('login', $ident);
```

Das Interface `RateLimiterStorage` erlaubt das Austauschen des Backends (z. B. Redis).

### `Mailer` (neu, 2026-04-24)

Minimalistischer SMTP-Client - verbindet sich direkt per `stream_socket_client` zu
einem SMTP-Server (in Dev: Mailpit auf Port 1025). Keine externen Abhaengigkeiten.

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

Versand schlaegt nicht stumm fehl - Fehler landen im `error_log`.

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

---

## Sicherheit

Siehe [SECURITY.md](SECURITY.md) fuer Details. Kurzfassung:

- **Passwoerter**: Argon2id (mit Pfeffer durch globale Konstante, falls konfiguriert), automatische Migration aus altem Base64.
- **CSRF**: Token pro Session, regeneriert bei Login und sensiblen Aenderungen.
- **XSS**: `htmlspecialchars` + Whitelist-`strip_tags` fuer Signaturen, Post-Rendering
  ueber `TextFormatter::formatPost`; Signaturen immer mit `htmlcode=OFF`.
- **SQL-Injection**: Ausschliesslich Prepared Statements. Keine dynamischen Queries.
- **Session**: HttpOnly + SameSite=Strict + strict_mode, Regenerate bei Login/Logout.
- **Rate-Limits**: Login (10 / 15 min / 15 min Lock), Passwort-Reset (5 / 1 h / 1 h Lock).
- **Passwort-Reset**: Token (32 Byte random, SHA-256 gehasht in DB), 1 h Gueltigkeit, einmalig einloesbar. Keine Preisgabe, ob eine Email existiert.
- **Logout**: nur per POST mit CSRF-Token (keine GET-CSRF-Angriffe mehr).

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

Aktueller Stand: **192 Tests** (137 Unit + 55 Feature), **258 Assertions**, 8 Tests
werden ohne verfuegbare DB automatisch geskipped.

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
docker compose down -v          # stoppen + alle Daten loeschen
```

### Git-Workflow

```bash
# Branch fuer neue Arbeit
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

---

## Changelog

### Version 2.1.0 - 2026-04-24 (Bugfix-Release)

**Basierend auf dem Userbereich-Audit vom 2026-04-23 (siehe `docs/2026-04-23-Userbereichs-bugs.md`).**
Alle 18 dokumentierten Bugs behoben.

#### Neue Features
- `Validator`-Klasse (Username-Regex, Passwortregeln, Length-Checks)
- `RateLimiter` + `DatabaseRateLimitStorage` (Login- und Reset-Bruteforce-Schutz)
- `Mailer`-Klasse (SMTP direkt, ersetzt stummes `@mail()`)
- `resetpassword.php` (Token-basierter Passwort-Reset)
- `install_bugfix_2026-04-23.sql` (Migration fuer Username-UNIQUE, Tokens, Rate-Limits)

#### Sicherheits-Fixes
- Stored XSS via Signatur verhindert (Whitelist-`strip_tags` + `htmlcode=OFF` im Rendering)
- Passwort-Reset ueberschreibt Passwoerter nicht mehr sofort - Token-Flow
- Logout nur noch per POST mit CSRF-Token
- Login/Reset liefern vereinheitlichte Fehlermeldungen (keine User-Enumeration)
- Brute-Force-Schutz auf Login (10/15min) und Reset (5/1h)
- Re-Authentifizierung per aktuellem Passwort bei Email-/Passwort-Wechsel
- UNIQUE-Index auf `ppb_users.username`

#### UX / Workflow
- Registrierungsformular liest `acception` aus REQUEST (nicht mehr nur GET) - Registrierung funktioniert jetzt ohne Workaround
- Profilseite: Passwortfelder optional (leer = keine Aenderung), ICQ-Default-`0` wird leer angezeigt
- Posting: nur per Session (kein paralleler Email+Password-Auth-Pfad mehr)
- Laengen-Checks mit klaren Fehlermeldungen (statt stummen PDO-Exceptions)
- Login-Erfolgstext entfernt Legacy-"360-Tage-Cookie"-Hinweis

### Version 2.0.0 - 2026-01 (PHP 8.4 Migration)
- Komplette Modernisierung auf PHP 8.4
- PHPUnit-Testsuite eingefuehrt
- PHPStan Level 8, Psalm, PHP-CS-Fixer, Rector, Infection
- GitHub Actions CI/CD
- Zentrales Error-Handling, Security-Event-Logging
- PSR-4 Autoloading
- Session-basierte Authentifizierung statt Cookie mit Passwort

### Version 1.x - 2001-2009
- Urspruengliche Entwicklung von Stefan "BFG" Kramer
- PHP 4/5, `mysql_*`-Funktionen, Basis-Forum

---

## Lizenz

MIT License

Copyright (c) 2001-2009 Stefan "BFG" Kramer (PowerScripts)
Copyright (c) 2024 Nico Schubert / PowerScripts

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

- Telefon: +49 (0) 3612 3002247 (Mo.-Fr. 9-12 und 13-18 Uhr)
- Telefax: +49 (0) 3612 3004636
- E-Mail: [info@schubertmedia.de](mailto:info@schubertmedia.de)

Projekte:
- [https://www.powerscripts.org](https://www.powerscripts.org) - PowerScripts Hauptseite
- [https://www.powerscripts.org/projects-3.html](https://www.powerscripts.org/projects-3.html) - Alle Projekte
- [https://github.com/schubertnico/PowerPHPBoard](https://github.com/schubertnico/PowerPHPBoard) - Quellcode

Bug-Reports und Feature-Requests bitte ueber die GitHub-Issues:
[https://github.com/schubertnico/PowerPHPBoard/issues](https://github.com/schubertnico/PowerPHPBoard/issues)
