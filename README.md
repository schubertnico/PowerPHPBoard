# PowerPHPBoard

Ein sicheres, leichtgewichtiges PHP-Forum-System fuer PHP 8.4+.

Urspruenglich entwickelt 2001-2009, komplett modernisiert fuer aktuelle PHP-Standards.

**Repository:** https://github.com/schubertnico/PowerPHPBoard

---

## Inhaltsverzeichnis

1. [Features](#features)
2. [Voraussetzungen](#voraussetzungen)
3. [Installation](#installation)
4. [Konfiguration](#konfiguration)
5. [Projektstruktur](#projektstruktur)
6. [Core-Klassen](#core-klassen)
7. [Sicherheit](#sicherheit)
8. [BBCode-Referenz](#bbcode-referenz)
9. [Testing](#testing)
10. [Entwicklung](#entwicklung)
11. [API-Dokumentation](#api-dokumentation)
12. [Changelog](#changelog)
13. [Lizenz](#lizenz)

---

## Features

### Forum-Funktionen
- Foren mit Kategorien und Boards
- Thread- und Post-Erstellung
- Benutzerregistrierung und -profile
- Moderatoren-System mit Berechtigungen
- Private Boards mit Passwortschutz
- BBCode und Smilies
- E-Mail-Benachrichtigungen

### Technische Features
- **PHP 8.4**: Strict Types, Match Expressions, Named Arguments
- **Sicherheit**: CSRF, Prepared Statements, XSS-Praevention, Argon2id
- **Qualitaet**: PHPStan Level 8, 132 PHPUnit Tests
- **CI/CD**: GitHub Actions fuer automatisierte Tests
- **Logging**: Zentrales Error-Handling und Security-Logging
- **Mehrsprachig**: Deutsch (Sie/Du), Englisch

---

## Voraussetzungen

| Komponente | Version | Hinweis |
|------------|---------|---------|
| PHP | 8.4+ | Mit strict_types |
| MySQL | 8.0+ | Oder MariaDB 10.3+ |
| PDO | - | pdo_mysql Extension |
| mbstring | - | PHP Extension |
| Composer | 2.0+ | Fuer Dependencies |

---

## Installation

### Option 1: Docker (Empfohlen)

```bash
# Repository klonen
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard

# Dependencies installieren
composer install

# Docker-Container starten
cd .docker
docker compose up -d --build
```

**Zugriff:**
| Service | URL | Beschreibung |
|---------|-----|--------------|
| Forum | http://localhost:8085 | Hauptanwendung |
| phpMyAdmin | http://localhost:8088 | Datenbank-Verwaltung |
| Mailpit | http://localhost:8032 | E-Mail-Test-Interface |

### Option 2: Manuelle Installation

```bash
# Repository klonen
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard

# Dependencies installieren
composer install

# Konfiguration erstellen
cp config.inc.php.example config.inc.php
# Datenbankzugangsdaten in config.inc.php eintragen

# Datenbank importieren
mysql -u root -p PowerPHPBoard < database/install.sql

# Entwicklungsserver starten
php -S localhost:8085
```

---

## Konfiguration

### Environment-Variablen

| Variable | Beschreibung | Standard |
|----------|--------------|----------|
| `PPB_DB_HOST` | Datenbank-Host | `localhost` |
| `PPB_DB_USER` | Datenbank-Benutzer | `root` |
| `PPB_DB_PASS` | Datenbank-Passwort | (leer) |
| `PPB_DB_NAME` | Datenbank-Name | `PowerPHPBoard_v2` |
| `PPB_DEBUG` | Debug-Modus | `false` |

### config.inc.php

```php
<?php
declare(strict_types=1);

$mysql = [
    'server'   => getenv('PPB_DB_HOST') ?: 'localhost',
    'user'     => getenv('PPB_DB_USER') ?: 'root',
    'password' => getenv('PPB_DB_PASS') ?: '',
    'database' => getenv('PPB_DB_NAME') ?: 'PowerPHPBoard_v2',
];

// Anwendungseinstellungen
define('PPB_VERSION', '2.0.0');
define('PPB_SESSION_LIFETIME', 3600);
define('PPB_CSRF_ENABLED', true);
define('PPB_DEBUG', (bool)(getenv('PPB_DEBUG') ?: false));
```

---

## Projektstruktur

```
PowerPHPBoard/
│
├── .docker/                    # Docker-Konfiguration
│   ├── Dockerfile              # PHP 8.4 Apache Image
│   ├── docker-compose.yml      # Container-Orchestrierung
│   └── apache.conf             # Apache-Konfiguration
│
├── .github/
│   └── workflows/
│       └── ci.yml              # GitHub Actions CI/CD
│
├── admin/                      # Admin-Panel
│   ├── header.inc.php          # Admin-Header
│   ├── index.php               # Admin-Dashboard
│   ├── addboard.php            # Board erstellen
│   ├── editboard.php           # Board bearbeiten
│   ├── addboardcategory.php    # Kategorie erstellen
│   ├── editboardcategory.php   # Kategorie bearbeiten
│   ├── adduser.php             # Benutzer erstellen
│   ├── edituser.php            # Benutzer bearbeiten
│   └── general.php             # Allgemeine Einstellungen
│
├── includes/                   # Core-Klassen (PSR-4)
│   ├── Database.php            # PDO Datenbank-Wrapper
│   ├── Session.php             # Session-Verwaltung
│   ├── Security.php            # Sicherheits-Utilities
│   ├── CSRF.php                # CSRF-Schutz
│   ├── TextFormatter.php       # BBCode/Smilies
│   └── ErrorHandler.php        # Error-Handling & Logging
│
├── tests/                      # PHPUnit Tests
│   ├── bootstrap.php           # Test-Bootstrap
│   ├── Unit/                   # Unit Tests
│   │   ├── SecurityTest.php    # 24 Tests
│   │   ├── CSRFTest.php        # 13 Tests
│   │   ├── SessionTest.php     # 14 Tests
│   │   └── TextFormatterTest.php # 26 Tests
│   └── Feature/                # Feature Tests
│       ├── FeatureTestCase.php # Basis-Testklasse
│       ├── LoginWorkflowTest.php    # 14 Tests
│       ├── RegisterWorkflowTest.php # 21 Tests
│       └── ProfileWorkflowTest.php  # 20 Tests
│
├── logs/                       # Log-Dateien
│   ├── php-error.log           # PHP-Fehler
│   └── security.log            # Security-Events
│
├── images/                     # Bilder und Smilies
│   └── *.gif                   # Smilie-Grafiken
│
├── todos/                      # Projekt-TODOs
│   └── workflow-absicherung-plan.md
│
├── config.inc.php              # Hauptkonfiguration
├── header.inc.php              # Seiten-Header
├── footer.inc.php              # Seiten-Footer
├── functions.inc.php           # Legacy-Hilfsfunktionen
│
├── index.php                   # Startseite
├── login.php                   # Login-Formular
├── logout.php                  # Logout
├── register.php                # Registrierung
├── profile.php                 # Benutzerprofil
├── board.php                   # Board-Ansicht
├── thread.php                  # Thread-Ansicht
├── newthread.php               # Neuer Thread
├── newpost.php                 # Neuer Post
├── editpost.php                # Post bearbeiten
├── sendmail.php                # E-Mail senden
├── sendpassword.php            # Passwort zuruecksetzen
├── faq.php                     # FAQ-Seite
├── memberlist.php              # Mitgliederliste
├── search.php                  # Suche
│
├── english.inc.php             # Englische Sprache
├── deutsch-sie.inc.php         # Deutsch (formell)
├── deutsch-du.inc.php          # Deutsch (informell)
│
├── composer.json               # Composer-Konfiguration
├── composer.lock               # Dependency-Lock
├── phpstan.neon                # PHPStan Level 8
├── phpunit.xml                 # PHPUnit-Konfiguration
├── rector.php                  # Rector-Konfiguration
├── LICENSE                     # MIT-Lizenz
└── README.md                   # Diese Dokumentation
```

---

## Core-Klassen

### Database

Singleton PDO-Wrapper mit Prepared Statement Support.

**Namespace:** `PowerPHPBoard\Database`

```php
use PowerPHPBoard\Database;

// Instanz holen (Singleton)
$db = Database::getInstance($mysql);

// Einzelne Zeile abrufen
$user = $db->fetchOne(
    "SELECT * FROM ppb_users WHERE id = ?",
    [$userId]
);

// Alle Zeilen abrufen
$posts = $db->fetchAll(
    "SELECT * FROM ppb_posts WHERE boardid = ? ORDER BY date DESC",
    [$boardId]
);

// Query ausfuehren (INSERT, UPDATE, DELETE)
$db->query(
    "UPDATE ppb_users SET username = ? WHERE id = ?",
    [$newName, $userId]
);

// Anzahl betroffener Zeilen
$count = $db->execute(
    "DELETE FROM ppb_posts WHERE userid = ?",
    [$userId]
);

// Letzte Insert-ID
$db->query("INSERT INTO ppb_users ...", [...]);
$newId = $db->lastInsertId();

// Transaktionen
$db->beginTransaction();
try {
    $db->query("INSERT ...", [...]);
    $db->query("UPDATE ...", [...]);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    throw $e;
}
```

**Methoden:**

| Methode | Parameter | Rueckgabe | Beschreibung |
|---------|-----------|-----------|--------------|
| `getInstance()` | `array $config` | `Database` | Singleton-Instanz |
| `query()` | `string $sql, array $params` | `PDOStatement` | Query ausfuehren |
| `fetchOne()` | `string $sql, array $params` | `?array` | Eine Zeile |
| `fetchAll()` | `string $sql, array $params` | `array` | Alle Zeilen |
| `execute()` | `string $sql, array $params` | `int` | Betroffene Zeilen |
| `lastInsertId()` | - | `string` | Letzte Insert-ID |
| `beginTransaction()` | - | `bool` | Transaktion starten |
| `commit()` | - | `bool` | Transaktion bestaetigen |
| `rollBack()` | - | `bool` | Transaktion zurueckrollen |

---

### Session

Sichere Session-Verwaltung mit Login/Logout-Funktionalitaet.

**Namespace:** `PowerPHPBoard\Session`

```php
use PowerPHPBoard\Session;

// Session starten (automatische Sicherheitskonfiguration)
Session::start();

// Benutzer einloggen
Session::login($userId);
// Setzt: $_SESSION['user_id'], $_SESSION['login_time']
// Regeneriert Session-ID

// Eingeloggt pruefen
if (Session::isLoggedIn()) {
    $userId = Session::getUserId();
    echo "Eingeloggt als User #$userId";
}

// Session-Werte setzen/lesen
Session::set('theme', 'dark');
$theme = Session::get('theme', 'light');  // 'light' ist Default

// Wert vorhanden?
if (Session::has('theme')) {
    // ...
}

// Wert entfernen
Session::remove('theme');

// Alle Session-Daten
$allData = Session::all();

// Ausloggen (Session zerstoeren)
Session::logout();
// Alias: Session::destroy();

// Session-ID regenerieren
Session::regenerate();
```

**Methoden:**

| Methode | Parameter | Rueckgabe | Beschreibung |
|---------|-----------|-----------|--------------|
| `start()` | - | `void` | Session starten |
| `login()` | `int $userId` | `void` | Benutzer einloggen |
| `logout()` | - | `void` | Benutzer ausloggen |
| `isLoggedIn()` | - | `bool` | Login-Status pruefen |
| `getUserId()` | - | `?int` | User-ID oder null |
| `get()` | `string $key, mixed $default` | `mixed` | Wert lesen |
| `set()` | `string $key, mixed $value` | `void` | Wert setzen |
| `has()` | `string $key` | `bool` | Wert vorhanden? |
| `remove()` | `string $key` | `void` | Wert entfernen |
| `all()` | - | `array` | Alle Session-Daten |
| `regenerate()` | - | `void` | Session-ID erneuern |
| `destroy()` | - | `void` | Session zerstoeren |

---

### Security

Sicherheits-Utilities fuer Eingabevalidierung und Passwort-Handling.

**Namespace:** `PowerPHPBoard\Security`

```php
use PowerPHPBoard\Security;

// === XSS-Praevention ===

// HTML-Entities escapen
echo Security::escape($userInput);
echo Security::e($userInput);  // Kurzform

// === Passwort-Handling ===

// Passwort hashen (Argon2id)
$hash = Security::hashPassword($password);
// Beispiel: $argon2id$v=19$m=65536,t=4,p=1$...

// Passwort verifizieren
if (Security::verifyPassword($password, $hash)) {
    echo "Passwort korrekt";
}

// Legacy-Passwort erkennen (Base64)
if (Security::isLegacyHash($hash)) {
    echo "Altes Format, sollte migriert werden";
}

// Rehash noetig?
if (Security::needsRehash($hash)) {
    $newHash = Security::hashPassword($password);
    // In Datenbank speichern
}

// === Eingabe-Handling ===

// Integer aus Request
$id = Security::getInt('id');                    // GET, default 0
$page = Security::getInt('page', 'GET', 1);      // GET, default 1
$userId = Security::getInt('user_id', 'POST');   // POST

// String aus Request (getrimmt)
$name = Security::getString('name');             // GET
$email = Security::getString('email', 'POST');   // POST
$search = Security::getString('q', 'GET', '');   // mit Default

// === Validierung ===

// E-Mail validieren
if (Security::isValidEmail($email)) {
    echo "Gueltige E-Mail";
}

// === Token-Generierung ===

// Zufaelliger Token (32 Bytes = 64 Hex-Zeichen)
$token = Security::generateToken();

// Kuerzerer Token
$shortToken = Security::generateToken(16);  // 32 Hex-Zeichen

// === Client-IP ===

$ip = Security::getClientIp();
// Beruecksichtigt X-Forwarded-For, X-Real-IP
```

**Methoden:**

| Methode | Parameter | Rueckgabe | Beschreibung |
|---------|-----------|-----------|--------------|
| `escape()` | `?string $str` | `string` | HTML-Entities escapen |
| `e()` | `?string $str` | `string` | Alias fuer escape() |
| `hashPassword()` | `string $password` | `string` | Argon2id Hash erstellen |
| `verifyPassword()` | `string $password, string $hash` | `bool` | Passwort pruefen |
| `isLegacyHash()` | `string $hash` | `bool` | Base64-Hash erkennen |
| `needsRehash()` | `string $hash` | `bool` | Rehash noetig? |
| `getInt()` | `string $key, string $method, int $default` | `int` | Integer aus Request |
| `getString()` | `string $key, string $method, string $default` | `string` | String aus Request |
| `isValidEmail()` | `string $email` | `bool` | E-Mail validieren |
| `generateToken()` | `int $length` | `string` | Zufalls-Token |
| `getClientIp()` | - | `string` | Client-IP-Adresse |

---

### CSRF

Cross-Site Request Forgery Schutz.

**Namespace:** `PowerPHPBoard\CSRF`

```php
use PowerPHPBoard\CSRF;

// === In Formularen ===

// Hidden-Feld ausgeben
echo '<form method="post">';
echo CSRF::getTokenField();
// Output: <input type="hidden" name="csrf_token" value="abc123...">
echo '<button type="submit">Senden</button>';
echo '</form>';

// Oder manuell
echo '<input type="hidden" name="' . CSRF::getTokenName() . '"
       value="' . CSRF::generateToken() . '">';

// === Bei Formular-Verarbeitung ===

// Methode 1: Boolean-Pruefung
if (!CSRF::validateFromPost()) {
    die('CSRF-Token ungueltig');
}

// Methode 2: Automatisches Die
CSRF::validateOrDie();
// Bei Fehler: HTTP 403 + Fehlermeldung

// Methode 3: Manueller Token
$token = $_GET['token'] ?? '';
if (!CSRF::validate($token)) {
    die('Ungueltig');
}

// === Nach erfolgreicher Aktion ===

// Token regenerieren (verhindert Replay-Attacken)
CSRF::regenerate();
```

**Methoden:**

| Methode | Parameter | Rueckgabe | Beschreibung |
|---------|-----------|-----------|--------------|
| `generateToken()` | - | `string` | Token generieren/abrufen |
| `getTokenField()` | - | `string` | Hidden-Input HTML |
| `getTokenName()` | - | `string` | Name des Token-Feldes |
| `validate()` | `?string $token` | `bool` | Token validieren |
| `validateFromPost()` | `bool $logFailure` | `bool` | POST-Token validieren |
| `validateOrDie()` | `?string $token` | `void` | Validieren oder 403 |
| `regenerate()` | - | `void` | Neuen Token generieren |

---

### TextFormatter

BBCode-Parsing und Smilie-Ersetzung.

**Namespace:** `PowerPHPBoard\TextFormatter`

```php
use PowerPHPBoard\TextFormatter;

// === Post formatieren ===

$html = TextFormatter::formatPost(
    $text,      // Eingabetext
    'ON',       // BBCode aktiviert (ON/OFF)
    'ON',       // Smilies aktiviert (ON/OFF)
    'OFF'       // HTML erlaubt (ON/OFF)
);

// === BBCode entfernen ===

$plainText = TextFormatter::stripBBCode($text);
// "[b]Hallo[/b] [url=...]Link[/url]" => "Hallo Link"

// === Smilies abrufen ===

$smilies = TextFormatter::getSmilies();
// [
//     ':)' => ['file' => 'smile.gif', 'width' => 19, 'height' => 19],
//     ':(' => ['file' => 'frown.gif', 'width' => 19, 'height' => 19],
//     ...
// ]
```

**Methoden:**

| Methode | Parameter | Rueckgabe | Beschreibung |
|---------|-----------|-----------|--------------|
| `formatPost()` | `string, string, string, string` | `string` | Text formatieren |
| `stripBBCode()` | `string $text` | `string` | BBCode entfernen |
| `getSmilies()` | - | `array` | Verfuegbare Smilies |

---

### ErrorHandler

Zentrales Error-Handling und Security-Logging.

**Namespace:** `PowerPHPBoard\ErrorHandler`

```php
use PowerPHPBoard\ErrorHandler;

// === Initialisierung (in config.inc.php) ===

ErrorHandler::init(
    __DIR__ . '/logs/php-error.log',  // Log-Pfad
    PPB_DEBUG                          // Debug-Modus
);

// === Security-Events loggen ===

// Login-Versuche
ErrorHandler::logFailedLogin($email, 'invalid_password');
ErrorHandler::logFailedLogin($email, 'user_not_found');
ErrorHandler::logFailedLogin($email, 'account_disabled');
ErrorHandler::logSuccessfulLogin($userId, $email);

// CSRF-Fehler
ErrorHandler::logCsrfFailure('/profile.php');

// Berechtigungsfehler
ErrorHandler::logPermissionDenied('edit_post', $userId);
ErrorHandler::logPermissionDenied('admin_access', $userId);

// Verdaechtige Aktivitaet
ErrorHandler::logSuspiciousActivity('SQL injection attempt', [
    'input' => $suspiciousInput,
    'page' => '/search.php'
]);

// Allgemeines Security-Event
ErrorHandler::logSecurityEvent('RATE_LIMIT_EXCEEDED', [
    'action' => 'login',
    'count' => 10
]);
```

**Log-Format:**

```
# logs/php-error.log
[2024-01-10 21:30:45] ERROR: Division by zero in /var/www/index.php on line 42
[2024-01-10 21:30:46] EXCEPTION: PDOException in /var/www/includes/Database.php on line 57

# logs/security.log
[2024-01-10 21:30:45] SECURITY [LOGIN_FAILED] User:guest IP:192.168.1.100 | {"email":"test@example.com","reason":"invalid_password"}
[2024-01-10 21:30:46] SECURITY [LOGIN_SUCCESS] User:42 IP:192.168.1.100 | {"user_id":42,"email":"user@example.com"}
[2024-01-10 21:30:47] SECURITY [CSRF_FAILURE] User:guest IP:192.168.1.100 | {"action":"/profile.php","request_uri":"/profile.php"}
```

**Methoden:**

| Methode | Parameter | Rueckgabe | Beschreibung |
|---------|-----------|-----------|--------------|
| `init()` | `string $logPath, bool $debug` | `void` | Handler initialisieren |
| `handleError()` | `int, string, string, int` | `bool` | PHP-Fehler behandeln |
| `handleException()` | `Throwable $e` | `void` | Exception behandeln |
| `handleShutdown()` | - | `void` | Fatale Fehler abfangen |
| `logSecurityEvent()` | `string $event, array $context` | `void` | Security-Event loggen |
| `logFailedLogin()` | `string $email, string $reason` | `void` | Fehlgeschlagener Login |
| `logSuccessfulLogin()` | `int $userId, string $email` | `void` | Erfolgreicher Login |
| `logCsrfFailure()` | `string $action` | `void` | CSRF-Fehler |
| `logPermissionDenied()` | `string $action, int $userId` | `void` | Berechtigungsfehler |
| `logSuspiciousActivity()` | `string $desc, array $data` | `void` | Verdaechtige Aktivitaet |

---

## Sicherheit

### Implementierte Schutzmassnahmen

| Bedrohung | Schutzmassnahme | Implementierung |
|-----------|-----------------|-----------------|
| SQL Injection | Prepared Statements | `Database::query($sql, $params)` |
| XSS | Output Escaping | `Security::escape($input)` |
| CSRF | Token-Validierung | `CSRF::validateFromPost()` |
| Session Hijacking | Session-Regeneration | `Session::regenerate()` |
| Passwort-Leaks | Argon2id Hashing | `Security::hashPassword()` |
| Brute Force | Security Logging | `ErrorHandler::logFailedLogin()` |

### OWASP Top 10 Abdeckung

| Risiko | Status | Details |
|--------|--------|---------|
| A01 Broken Access Control | Geschuetzt | Session-basierte Auth, Berechtigungspruefungen |
| A02 Cryptographic Failures | Geschuetzt | Argon2id, keine Klartext-Passwoerter |
| A03 Injection | Geschuetzt | PDO Prepared Statements |
| A07 XSS | Geschuetzt | htmlspecialchars() mit ENT_QUOTES |
| A08 Insecure Deserialization | N/A | Keine Serialisierung verwendet |

### Sicherheits-Checkliste

```
[x] Alle Formulare mit CSRF-Token
[x] Alle Datenbankabfragen mit Prepared Statements
[x] Alle Ausgaben mit Security::escape()
[x] Passwoerter mit Argon2id gehasht
[x] Session-ID nach Login regeneriert
[x] Sichere Cookie-Einstellungen
[x] Keine Passwoerter in Cookies
[x] Security-Events geloggt
[x] Fehler nicht im Produktionsmodus angezeigt
```

---

## BBCode-Referenz

### Textformatierung

| BBCode | Ergebnis | HTML |
|--------|----------|------|
| `[b]fett[/b]` | **fett** | `<b>fett</b>` |
| `[i]kursiv[/i]` | *kursiv* | `<i>kursiv</i>` |
| `[u]unterstrichen[/u]` | unterstrichen | `<u>unterstrichen</u>` |
| `[s]durchgestrichen[/s]` | ~~durchgestrichen~~ | `<s>durchgestrichen</s>` |

### Links und Medien

| BBCode | Beschreibung |
|--------|--------------|
| `[url]https://...[/url]` | Einfacher Link |
| `[url=https://...]Text[/url]` | Link mit Text |
| `[img]https://...jpg[/img]` | Bild einbetten |
| `[email]addr@domain.com[/email]` | E-Mail-Link |

### Bloecke

| BBCode | Beschreibung |
|--------|--------------|
| `[quote]Zitat[/quote]` | Zitat-Block |
| `[code]Code[/code]` | Code-Block |
| `[list][*]Item[/list]` | Aufzaehlung |

### Formatierung

| BBCode | Beschreibung |
|--------|--------------|
| `[color=red]Text[/color]` | Farbiger Text |
| `[size=4]Text[/size]` | Textgroesse (1-7) |
| `[center]Text[/center]` | Zentriert |

### Smilies

| Code | Bild | Beschreibung |
|------|------|--------------|
| `:)` | smile.gif | Laecheln |
| `;)` | wink.gif | Zwinkern |
| `:D` | biggrin.gif | Grinsen |
| `:(` | frown.gif | Traurig |
| `:o` | eek.gif | Ueberrascht |
| `:p` | tongue.gif | Zunge |
| `:mad:` | mad.gif | Wuetend |
| `:rolleyes:` | rolleyes.gif | Augenrollen |
| `:cool:` | cool.gif | Cool |

---

## Testing

### Testausfuehrung

```bash
# Alle Tests ausfuehren
composer test

# Nur Unit Tests
vendor/bin/phpunit --testsuite Unit

# Nur Feature Tests
vendor/bin/phpunit --testsuite Feature

# Mit Coverage-Report
composer test-coverage
# Oeffne coverage/index.html im Browser

# Einzelne Testdatei
vendor/bin/phpunit tests/Unit/SecurityTest.php

# Einzelner Test
vendor/bin/phpunit --filter testEscapePreventXSS
```

### Test-Uebersicht

| Suite | Datei | Tests | Beschreibung |
|-------|-------|-------|--------------|
| Unit | SecurityTest.php | 24 | Escape, Hash, Validate |
| Unit | CSRFTest.php | 13 | Token, Validation |
| Unit | SessionTest.php | 14 | Login, Logout, Get/Set |
| Unit | TextFormatterTest.php | 26 | BBCode, Smilies |
| Feature | LoginWorkflowTest.php | 14 | Login-Prozess |
| Feature | RegisterWorkflowTest.php | 21 | Registrierung |
| Feature | ProfileWorkflowTest.php | 20 | Profil-Bearbeitung |
| **Gesamt** | | **132** | |

### Test-Kategorien

**Unit Tests:** Testen isolierte Klassen und Methoden
- Keine Datenbank erforderlich
- Schnelle Ausfuehrung
- 77 Tests, 124 Assertions

**Feature Tests:** Testen komplette Workflows
- Simulieren HTTP-Requests
- Pruefen Validierung, CSRF, Session
- 55 Tests, 80 Assertions
- 8 Tests erfordern Datenbank (werden uebersprungen wenn nicht verfuegbar)

---

## Entwicklung

### Statische Analyse

```bash
# PHPStan (Level 8 - strikteste)
composer phpstan

# Oder direkt
vendor/bin/phpstan analyse --memory-limit=512M
```

### Code-Modernisierung

```bash
# Rector Vorschau
composer rector-dry

# Rector ausfuehren
composer rector
```

### Code-Stil

```bash
# PHP Syntax pruefen
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```

### Docker-Entwicklung

```bash
# Container starten
cd .docker && docker compose up -d

# Logs anzeigen
docker compose logs -f web

# In Container einloggen
docker compose exec web bash

# Container stoppen
docker compose down

# Neu bauen
docker compose up -d --build
```

### Git-Workflow

```bash
# Feature-Branch erstellen
git checkout -b feature/new-feature

# Aenderungen committen
git add .
git commit -m "Add new feature"

# Tests vor Push
composer test
composer phpstan

# Push
git push origin feature/new-feature
```

---

## API-Dokumentation

Siehe separate Dateien:
- [INSTALLATION.md](docs/INSTALLATION.md) - Detaillierte Installationsanleitung
- [SECURITY.md](SECURITY.md) - Sicherheitsrichtlinien
- [CONTRIBUTING.md](CONTRIBUTING.md) - Beitragsrichtlinien
- [docs/API.md](docs/API.md) - Vollstaendige API-Referenz

---

## Changelog

### Version 2.0.0 (2026)

**Komplette Modernisierung fuer PHP 8.4**

#### Neue Features
- PHPUnit Test-Suite (132 Tests)
- PHPStan Level 8 Compliance
- GitHub Actions CI/CD
- Zentrales Error-Handling
- Security-Event-Logging
- PSR-4 Autoloading

#### Sicherheit
- CSRF-Schutz fuer alle Formulare
- PDO Prepared Statements
- Argon2id Passwort-Hashing
- XSS-Praevention
- Session-Sicherheit

#### Code-Qualitaet
- Strict Types in allen Dateien
- Match Expressions
- Typed Properties
- Return Type Declarations
- Null-safe Operator

#### Entfernt
- `mysql_*` Funktionen
- `ereg*` Funktionen
- Base64 Passwort-Speicherung
- Cookie-basierte Authentifizierung

### Version 1.x (2001-2009)

- Urspruengliche Entwicklung
- PHP 4/5 kompatibel
- MySQL-Funktionen
- Basis-Forum-Funktionalitaet

---

## Lizenz

MIT License

Copyright (c) 2001-2009 Stefan 'BFG' Kramer (PowerScripts)
Copyright (c) 2026 Nico Schubert

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

## Autoren

- **Stefan 'BFG' Kramer** - Urspruengliche Entwicklung (2001-2009)
- **Nico Schubert** - PHP 8.4 Migration (2026)

---

## Support

- **Issues:** https://github.com/schubertnico/PowerPHPBoard/issues
- **Website:** https://powerscripts.org