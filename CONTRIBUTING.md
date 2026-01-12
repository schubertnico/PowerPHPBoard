# Beitragen zu PowerPHPBoard

Vielen Dank fuer Ihr Interesse an PowerPHPBoard! Dieses Dokument erklaert, wie Sie zum Projekt beitragen koennen.

---

## Inhaltsverzeichnis

1. [Verhaltenskodex](#verhaltenskodex)
2. [Wie kann ich beitragen?](#wie-kann-ich-beitragen)
3. [Entwicklungsumgebung einrichten](#entwicklungsumgebung-einrichten)
4. [Code-Standards](#code-standards)
5. [Commit-Richtlinien](#commit-richtlinien)
6. [Pull Request Prozess](#pull-request-prozess)
7. [Sicherheitsluecken melden](#sicherheitsluecken-melden)
8. [Dokumentation](#dokumentation)

---

## Verhaltenskodex

### Unsere Standards

- Respektvoller und professioneller Umgang
- Konstruktive Kritik und Feedback
- Fokus auf das Beste fuer die Community
- Empathie gegenueber anderen Mitwirkenden

### Nicht akzeptabel

- Beleidigende oder herabsetzende Kommentare
- Persoenliche Angriffe
- Veroeffentlichung privater Informationen
- Trolling oder absichtliche Stoerungen

---

## Wie kann ich beitragen?

### Bugs melden

1. **Suche zuerst** nach bestehenden Issues
2. Erstelle ein neues Issue mit:
   - Klarer Beschreibung des Problems
   - Schritten zur Reproduktion
   - Erwartetes vs. tatsaechliches Verhalten
   - PHP-Version und Umgebungsdetails
   - Screenshots (falls relevant)

**Issue-Template:**
```markdown
## Beschreibung
[Kurze Beschreibung des Bugs]

## Schritte zur Reproduktion
1. Gehe zu '...'
2. Klicke auf '...'
3. Scrolle zu '...'
4. Fehler erscheint

## Erwartetes Verhalten
[Was sollte passieren?]

## Tatsaechliches Verhalten
[Was passiert stattdessen?]

## Umgebung
- PHP-Version: 8.x
- MySQL-Version: 8.x
- Browser: Chrome/Firefox/Safari
- Betriebssystem: Windows/Linux/macOS
```

### Features vorschlagen

1. Oeffne ein Issue mit dem Label `enhancement`
2. Beschreibe:
   - Das Problem, das geloest werden soll
   - Deine vorgeschlagene Loesung
   - Moegliche Alternativen
   - Zusaetzlicher Kontext

### Code beitragen

1. Forke das Repository
2. Erstelle einen Feature-Branch
3. Schreibe Code und Tests
4. Stelle einen Pull Request

---

## Entwicklungsumgebung einrichten

### Voraussetzungen

- PHP 8.3 oder hoeher
- MySQL 8.0 oder hoeher (oder MariaDB 10.6+)
- Composer 2.x
- Git

### Installation

```bash
# Repository klonen
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard

# Abhaengigkeiten installieren
composer install

# Konfiguration erstellen
cp config.inc.php.example config.inc.php
# config.inc.php anpassen

# Datenbank einrichten
mysql -u root -p < install/install.sql
```

### Mit Docker (empfohlen)

```bash
# Container starten
docker-compose up -d

# Logs anzeigen
docker-compose logs -f

# Container stoppen
docker-compose down
```

### Tests ausfuehren

```bash
# Alle Tests
composer test

# Nur Unit Tests
vendor/bin/phpunit --testsuite Unit

# Mit Coverage
composer test-coverage

# Statische Analyse
composer phpstan
```

---

## Code-Standards

### PHP-Version

- **Minimum:** PHP 8.3
- **Empfohlen:** PHP 8.4
- Nutze moderne PHP-Features: Typed Properties, Match Expressions, Named Arguments

### Coding Style

Wir folgen PSR-12 mit einigen Erweiterungen:

```php
<?php

declare(strict_types=1);

namespace PowerPHPBoard;

/**
 * Beschreibung der Klasse
 */
class BeispielKlasse
{
    private string $eigenschaft;

    /**
     * Beschreibung der Methode
     */
    public function methode(string $param): string
    {
        return match ($param) {
            'a' => 'Alpha',
            'b' => 'Beta',
            default => 'Unbekannt',
        };
    }
}
```

### Allgemeine Regeln

| Regel | Beschreibung |
|-------|-------------|
| Strict Types | `declare(strict_types=1);` in jeder Datei |
| Type Hints | Parameter und Rueckgabewerte typisieren |
| PHPDoc | Nur wenn Type Hints nicht ausreichen |
| Einrueckung | 4 Leerzeichen (keine Tabs) |
| Zeilenlaenge | Max. 120 Zeichen |
| Klammern | Oeffnende Klammer auf neuer Zeile bei Klassen/Methoden |
| Namespaces | PSR-4 Autoloading |

### Namenskonventionen

| Element | Konvention | Beispiel |
|---------|-----------|----------|
| Klassen | PascalCase | `UserController` |
| Methoden | camelCase | `getUserById()` |
| Variablen | camelCase | `$userName` |
| Konstanten | UPPER_SNAKE | `MAX_LOGIN_ATTEMPTS` |
| Interfaces | PascalCase + Interface | `CacheInterface` |
| Traits | PascalCase + Trait | `LoggableTrait` |

### Sicherheitsrichtlinien

**MUSS beachtet werden:**

```php
// SQL: Immer Prepared Statements
$user = $db->fetchOne(
    "SELECT * FROM ppb_users WHERE id = ?",
    [$userId]
);

// XSS: Immer escapen bei Ausgabe
echo Security::escape($userInput);

// CSRF: Token in Formularen
echo CSRF::getTokenField();

// CSRF: Validierung bei POST
if (!CSRF::validateFromPost()) {
    die('CSRF validation failed');
}

// Eingaben: Immer validieren
$id = Security::getInt('id', 'GET', 0);
$name = Security::getString('name', 'POST', '');

// Passwoerter: Argon2id hashen
$hash = Security::hashPassword($password);
```

**NIEMALS:**

```php
// NIEMALS: String-Konkatenation in SQL
$db->query("SELECT * FROM users WHERE id = $id");

// NIEMALS: Unescapte Ausgabe
echo $_GET['name'];

// NIEMALS: eval() oder aehnliches
eval($userCode);

// NIEMALS: Klartext-Passwoerter
$db->query("INSERT INTO users (password) VALUES ('$password')");
```

---

## Commit-Richtlinien

### Commit-Nachricht Format

```
<typ>(<bereich>): <beschreibung>

[optionaler body]

[optionaler footer]
```

### Typen

| Typ | Beschreibung |
|-----|-------------|
| `feat` | Neues Feature |
| `fix` | Bugfix |
| `docs` | Dokumentation |
| `style` | Formatierung (kein Code-Aenderung) |
| `refactor` | Code-Umstrukturierung |
| `test` | Tests hinzufuegen/aendern |
| `chore` | Maintenance-Tasks |
| `security` | Sicherheitsverbesserungen |

### Beispiele

```bash
# Feature
feat(auth): add password reset functionality

# Bugfix
fix(session): prevent session fixation attack

# Dokumentation
docs(readme): add installation instructions

# Refactoring
refactor(database): use singleton pattern

# Tests
test(security): add XSS prevention tests

# Sicherheit
security(csrf): implement timing-safe comparison
```

### Gute vs. Schlechte Commits

**Gut:**
```
fix(login): prevent timing attack on password verification

Use hash_equals() instead of === for password comparison
to prevent timing-based side-channel attacks.

Fixes #42
```

**Schlecht:**
```
fixed stuff
```

---

## Pull Request Prozess

### Vor dem PR

1. **Branch aktualisieren:**
   ```bash
   git fetch origin
   git rebase origin/main
   ```

2. **Tests ausfuehren:**
   ```bash
   composer test
   composer phpstan
   ```

3. **Code formatieren:**
   ```bash
   composer cs-fix  # falls konfiguriert
   ```

### PR erstellen

1. **Titel:** Klarer, beschreibender Titel
2. **Beschreibung:** Was, Warum, Wie
3. **Checkliste:**
   - [ ] Tests geschrieben und bestanden
   - [ ] PHPStan ohne Fehler
   - [ ] Dokumentation aktualisiert
   - [ ] Keine Breaking Changes (oder dokumentiert)

### PR-Template

```markdown
## Beschreibung
[Was wurde geaendert und warum?]

## Art der Aenderung
- [ ] Bugfix (nicht-brechende Aenderung, die ein Problem behebt)
- [ ] Neues Feature (nicht-brechende Aenderung, die Funktionalitaet hinzufuegt)
- [ ] Breaking Change (Aenderung, die bestehende Funktionalitaet beeintraechtigt)
- [ ] Dokumentation

## Wie wurde getestet?
[Beschreibe die Tests, die du durchgefuehrt hast]

## Checkliste
- [ ] Mein Code folgt den Projektrichtlinien
- [ ] Ich habe meinen Code selbst reviewed
- [ ] Ich habe Kommentare an schwer verstaendlichen Stellen hinzugefuegt
- [ ] Ich habe die Dokumentation aktualisiert
- [ ] Meine Aenderungen erzeugen keine neuen Warnungen
- [ ] Ich habe Tests hinzugefuegt, die beweisen, dass mein Fix/Feature funktioniert
- [ ] Neue und bestehende Unit Tests bestehen lokal
- [ ] PHPStan Level 8 zeigt keine Fehler
```

### Review-Prozess

1. Mindestens ein Reviewer muss genehmigen
2. Alle CI-Checks muessen bestehen
3. Merge-Konflikte muessen geloest sein
4. Squash & Merge wird bevorzugt

---

## Sicherheitsluecken melden

**WICHTIG:** Sicherheitsluecken NICHT ueber GitHub Issues melden!

### Meldeprozess

1. E-Mail an: security@powerscripts.org
2. Betreff: `[SECURITY] PowerPHPBoard - Kurzbeschreibung`
3. Inhalt:
   - Detaillierte Beschreibung
   - Schritte zur Reproduktion
   - Betroffene Versionen
   - Moegliche Auswirkungen

### Antwortzeiten

| Phase | Zeit |
|-------|------|
| Bestaetigung | 48 Stunden |
| Erste Einschaetzung | 7 Tage |
| Fix (kritisch) | So schnell wie moeglich |
| Fix (mittel/niedrig) | Im naechsten Release |

Siehe [SECURITY.md](SECURITY.md) fuer vollstaendige Details.

---

## Dokumentation

### Arten von Dokumentation

1. **Code-Kommentare:** Erklaere das "Warum", nicht das "Was"
2. **PHPDoc:** Fuer oeffentliche APIs
3. **README:** Schnellstart und Uebersicht
4. **Markdown-Dateien:** Detaillierte Anleitungen

### PHPDoc-Format

```php
/**
 * Validiert eine E-Mail-Adresse
 *
 * Verwendet filter_var mit FILTER_VALIDATE_EMAIL.
 * Prueft zusaetzlich auf gueltiges MX-Record (optional).
 *
 * @param string $email Die zu validierende E-Mail-Adresse
 * @param bool $checkMx Ob MX-Record geprueft werden soll
 * @return bool True wenn gueltig, false sonst
 *
 * @example
 * if (Security::isValidEmail('user@example.com')) {
 *     // E-Mail ist gueltig
 * }
 */
public static function isValidEmail(string $email, bool $checkMx = false): bool
```

### Dokumentation uebersetzen

- Primaere Sprache: Deutsch
- Code-Kommentare: Englisch
- Variablennamen: Englisch

---

## Entwickler-Ressourcen

### Nuetzliche Links

- [PHP 8.4 Dokumentation](https://www.php.net/docs.php)
- [PHPUnit 11 Dokumentation](https://docs.phpunit.de/en/11.0/)
- [PHPStan Dokumentation](https://phpstan.org/user-guide/getting-started)
- [PSR-12 Standard](https://www.php-fig.org/psr/psr-12/)

### Projekt-Struktur

```
PowerPHPBoard/
├── includes/           # Core-Klassen
│   ├── Database.php    # PDO-Wrapper
│   ├── Session.php     # Session-Management
│   ├── CSRF.php        # CSRF-Schutz
│   ├── Security.php    # Sicherheitsfunktionen
│   ├── TextFormatter.php # BBCode-Parser
│   └── ErrorHandler.php  # Fehlerbehandlung
├── admin/              # Admin-Bereich
├── templates/          # HTML-Templates
├── lang/               # Sprachdateien
├── tests/              # PHPUnit Tests
│   ├── Unit/           # Unit Tests
│   └── Feature/        # Feature Tests
├── logs/               # Log-Dateien
├── docs/               # Dokumentation
└── .github/            # GitHub-Konfiguration
    └── workflows/      # CI/CD Pipelines
```

### Schnellreferenz: Befehle

```bash
# Tests
composer test                    # Alle Tests
vendor/bin/phpunit --filter Name # Einzelner Test
composer test-coverage           # Mit Coverage

# Analyse
composer phpstan                 # Statische Analyse
vendor/bin/phpstan --level=max   # Maximales Level

# Entwicklung
php -S localhost:8085            # Built-in Server
cd .docker && docker-compose up -d  # Docker starten
docker-compose logs -f web       # Logs folgen
```

---

## Fragen?

- **GitHub Discussions:** Fuer allgemeine Fragen
- **GitHub Issues:** Fuer Bugs und Features
- **E-Mail:** info@powerscripts.org

---

Vielen Dank fuer Ihre Beitraege! Jeder Beitrag, ob gross oder klein, wird geschaetzt.