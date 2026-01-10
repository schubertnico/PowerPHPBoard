# PowerPHPBoard

Ein einfaches PHP-Forum-System, ursprünglich entwickelt 2001-2009, jetzt modernisiert für PHP 8.4.

**Repository:** https://github.com/schubertnico/PowerPHPBoard

## Features

- Foren mit Kategorien und Boards
- Benutzerregistrierung und -verwaltung
- BBCode und Smilies
- Moderatoren-System
- Admin-Panel
- Mehrsprachig (Deutsch, Englisch)

## Voraussetzungen

- PHP 8.4+
- MySQL 8.0+
- Docker & Docker Compose (empfohlen)

## Installation mit Docker

### 1. Repository klonen

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard
```

### 2. Container starten

```bash
cd .docker
docker compose up -d --build
```

### 3. Im Browser öffnen

- **Forum:** http://localhost:8085
- **phpMyAdmin:** http://localhost:8088
- **Mailpit (E-Mail-Test):** http://localhost:8032

### Docker-Ports

| Service    | Port  | Beschreibung           |
|------------|-------|------------------------|
| Web        | 8085  | Apache/PHP 8.4         |
| MySQL      | 3315  | Datenbank              |
| phpMyAdmin | 8088  | Datenbank-Verwaltung   |
| Mailpit    | 8032  | E-Mail-Test-Interface  |
| Mailpit    | 1032  | SMTP für E-Mails       |

## So prüfst du, ob es läuft

```bash
# Container-Status prüfen
docker compose ps

# Logs anzeigen
docker compose logs web

# PHP-Fehler-Log
tail -f logs/php-error.log
```

## Manuelle Installation

1. Dateien auf Webserver kopieren
2. `config.inc.php` anpassen (Datenbank-Zugangsdaten)
3. `install.sql` in MySQL importieren
4. Im Browser öffnen

## Entwicklung

### Composer-Installation

```bash
composer install
```

### PHPStan (Statische Analyse)

```bash
composer run phpstan
```

### Rector (Code-Modernisierung)

```bash
# Vorschau der Änderungen
composer run rector-dry

# Änderungen anwenden
composer run rector
```

## Lizenz

MIT License - siehe [LICENSE](LICENSE)

## Änderungen / Migration auf PHP 8.4

### Sicherheitsfixes

- **SQL Injection:** Alle Datenbankabfragen verwenden jetzt PDO mit Prepared Statements
- **XSS:** Alle Ausgaben werden mit `htmlspecialchars()` escaped
- **Passwort-Hashing:** Base64 ersetzt durch Argon2id (`password_hash()`)
- **Session-Sicherheit:** Keine Passwörter mehr in Cookies, sichere Session-Konfiguration
- **CSRF-Schutz:** Token-Validierung für alle Formulare
- **Credentials:** Datenbank-Zugangsdaten über Environment-Variablen

### PHP 8.4 Änderungen

- `declare(strict_types=1)` in allen Dateien
- `mysql_*` Funktionen → PDO mit Prepared Statements
- `eregi_replace()` → `preg_replace()` mit 'i' Modifier
- `eregi()` → `preg_match()` mit 'i' Modifier
- `split()` → `explode()`
- `match` Expression statt if/elseif-Ketten
- Typed Properties und Return Types

### Neue Dateien

- `.docker/` - Docker-Konfiguration für PHP 8.4
- `includes/Database.php` - PDO Datenbank-Klasse
- `includes/Session.php` - Sichere Session-Verwaltung
- `includes/CSRF.php` - CSRF-Token-Schutz
- `includes/Security.php` - Sicherheits-Hilfsfunktionen
- `includes/TextFormatter.php` - BBCode/Smilies-Verarbeitung
- `composer.json` - Composer-Konfiguration
- `phpstan.neon` - PHPStan-Konfiguration
- `rector.php` - Rector-Konfiguration
- `LICENSE` - MIT-Lizenz
- `README.md` - Diese Dokumentation
- `README.html` - HTML-Version der Dokumentation

### Migrierte Dateien

Alle PHP-Dateien wurden auf PHP 8.4 migriert:
- `config.inc.php` - Environment-Variablen, Autoload
- `header.inc.php` - PDO, Session, Security
- `footer.inc.php` - PDO Updates
- `functions.inc.php` - match Expression, PDO
- `login.php` - CSRF, Session, Password Verification
- `logout.php` - Session-basiertes Logout
- `register.php` - CSRF, Password Hashing, Prepared Statements
- `index.php` - PDO, Security Escaping
- Alle weiteren Dateien

## Ursprüngliche Autoren

- Stefan 'BFG' Kramer (PowerScripts, 2001-2009)
- Nico Schubert (PHP 8.4 Migration, 2024)