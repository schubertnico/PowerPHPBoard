# Umfassender Plan zur Workflow-Absicherung

## Aktuelle Situation

| Bereich | Status | Bewertung |
|---------|--------|-----------|
| CSRF-Schutz | Alle Formulare geschützt | Exzellent |
| SQL-Injection | PDO Prepared Statements | Exzellent |
| XSS-Praevention | Konsequentes Escaping | Exzellent |
| Session-Management | Sichere Implementierung | Exzellent |
| Eingabevalidierung | Umfassend | Gut |
| Fehlerbehandlung | Generisch, kein Logging | Verbesserungswuerdig |
| Code-Konsistenz | Meist einheitlich | Verbesserungswuerdig |

---

## Phase 1: Kritische Korrekturen

### 1.1 Include-Reihenfolge in register.php
**Problem:** Header wird vor Sprachdatei geladen - Uebersetzungen fehlen
```
Zeile 16: include header.inc.php
Zeile 17: include lang.inc.php  <- zu spaet!
```
**Loesung:** Sprachdatei vor Header laden

### 1.2 E-Mail-Validierung vereinheitlichen
**Problem:** Inkonsistente Methoden
- `register.php`: `Security::isValidEmail()` (korrekt)
- `profile.php`: Regex-Pattern (Zeile 125) (veraltet)
- `editpost.php`: Keine Validierung (fehlt)

**Loesung:** Ueberall `Security::isValidEmail()` verwenden

### 1.3 Passwort-Mindestlaenge in profile.php
**Problem:** register.php erfordert 6 Zeichen, profile.php prueft nur auf leer
**Loesung:** Gleiche Validierung wie in register.php

---

## Phase 2: Feature Tests (Integration Tests)

### 2.1 Test-Struktur
```
tests/Feature/
├── LoginWorkflowTest.php      # Login-Prozess komplett
├── RegisterWorkflowTest.php   # Registrierung komplett
├── NewThreadWorkflowTest.php  # Thread erstellen
├── NewPostWorkflowTest.php    # Post erstellen
├── EditPostWorkflowTest.php   # Post bearbeiten
├── ProfileWorkflowTest.php    # Profil bearbeiten
└── AdminWorkflowTest.php      # Admin-Funktionen
```

### 2.2 Was Feature Tests pruefen

| Test | Prueft |
|------|--------|
| Erfolgreicher Login | Session gesetzt, Redirect, User-ID korrekt |
| Falsches Passwort | Fehlermeldung, keine Session |
| Fehlender CSRF-Token | Anfrage abgelehnt |
| Ungültiger CSRF-Token | Anfrage abgelehnt |
| Leere Pflichtfelder | Validierungsfehler |
| XSS in Eingaben | HTML wird escaped |
| SQL-Injection-Versuch | Query schlaegt nicht fehl, Daten sicher |

---

## Phase 3: Automatisierte Code-Analyse

### 3.1 PHPStan auf hoeheres Level
Aktuell: Level 5 (vermutlich)
Ziel: Level 8 (strikteste Pruefung)

```bash
# Schrittweise erhoehen
vendor/bin/phpstan analyse --level=6
vendor/bin/phpstan analyse --level=7
vendor/bin/phpstan analyse --level=8
```

### 3.2 Zusaetzliche Tools

| Tool | Zweck |
|------|-------|
| PHP-CS-Fixer | Einheitlicher Code-Style |
| Psalm | Strikte Typ-Analyse |
| PHPMD | Code-Qualitaetsmetriken |

---

## Phase 4: Sicherheits-Hardening

### 4.1 Zentraler Exception-Handler
```php
// In config.inc.php oder separater Datei
set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    // User-freundliche Fehlerseite anzeigen
});
```

### 4.2 Security-Event-Logging
Logging fuer:
- Fehlgeschlagene Logins
- CSRF-Fehler
- Berechtigungsfehler
- Ungueltige Eingaben

### 4.3 Rate-Limiting (Optional)
- Login-Versuche begrenzen
- Account-Sperre nach X Fehlversuchen

---

## Phase 5: Konsistenz-Checkliste

### 5.1 Jede PHP-Datei muss pruefen

| Pruefung | Methode |
|----------|---------|
| Session gestartet | `Session::start()` vor jeder Ausgabe |
| CSRF bei POST | `CSRF::validateFromPost()` |
| Eingaben validiert | `Security::getString()`, `Security::getInt()` |
| Ausgaben escaped | `Security::escape()` |
| SQL mit Parametern | `$db->query("... ?", [$param])` |
| Berechtigungen geprueft | `Session::isLoggedIn()`, Admin-Check |

### 5.2 Automatisierte Pruefung
Ein Script, das alle PHP-Dateien scannt:
- Prueft ob CSRF::validateFromPost() bei POST-Handling vorhanden
- Prueft ob Security::escape() fuer Ausgaben verwendet wird
- Prueft ob prepared statements verwendet werden

---

## Phase 6: Manuelle Test-Matrix

### 6.1 Formular-Test-Matrix

| Formular | CSRF | Leere Felder | Ungueltige Daten | XSS-Test | SQL-Test |
|----------|------|--------------|------------------|----------|----------|
| Login | [ ] | [ ] | [ ] | [ ] | [ ] |
| Register | [ ] | [ ] | [ ] | [ ] | [ ] |
| Profil | [ ] | [ ] | [ ] | [ ] | [ ] |
| Neuer Thread | [ ] | [ ] | [ ] | [ ] | [ ] |
| Neuer Post | [ ] | [ ] | [ ] | [ ] | [ ] |
| Post bearbeiten | [ ] | [ ] | [ ] | [ ] | [ ] |
| Mail senden | [ ] | [ ] | [ ] | [ ] | [ ] |
| Passwort vergessen | [ ] | [ ] | [ ] | [ ] | [ ] |
| Admin: Board | [ ] | [ ] | [ ] | [ ] | [ ] |
| Admin: User | [ ] | [ ] | [ ] | [ ] | [ ] |
| Admin: Kategorie | [ ] | [ ] | [ ] | [ ] | [ ] |

---

## Empfohlene Reihenfolge

### Sofort (Phase 1) - ERLEDIGT
1. [x] Include-Reihenfolge in register.php - bereits korrekt (header.inc.php laedt alles)
2. [x] E-Mail-Validierung in profile.php vereinheitlichen (Security::isValidEmail())
3. [x] Passwort-Mindestlaenge in profile.php hinzufuegen (strlen >= 6, minlength="6")

### Kurzfristig (Phase 2-3)
4. [x] Feature Tests fuer kritische Workflows schreiben (55 Tests erstellt)
5. [x] PHPStan Level erhoehen (Level 5 -> Level 8, 0 Fehler)
6. [x] CI/CD Pipeline einrichten (.github/workflows/ci.yml)

### Mittelfristig (Phase 4-5) - ERLEDIGT
7. [x] Zentraler Exception-Handler (ErrorHandler.php)
8. [x] Security-Event-Logging (login, CSRF, permissions)
9. [ ] Konsistenz-Pruefscript erstellen

### Langfristig (Phase 6)
10. [ ] Vollstaendige manuelle Test-Matrix durchfuehren
11. [ ] Penetration-Testing
12. [ ] Code-Review-Prozess etablieren

---

## Identifizierte Probleme (Details)

### Kritisch
1. **register.php Zeile 16-17**: Header vor Sprachdatei geladen
   - Impact: Uebersetzungen fehlen in Board-Regeln

### Mittel
2. **profile.php Zeile 125**: Regex statt Security::isValidEmail()
3. **profile.php Zeile 107**: Keine Passwort-Mindestlaenge
4. **editpost.php Zeile 362**: Leeres Passwort-Feld im Hidden-Input
5. **Kein Security-Event-Logging**: Fehlgeschlagene Logins nicht protokolliert

### Gering
6. **register.php Zeile 133**: goto-Statement (nicht modern)
7. **Keine Bestaetigung**: Loeschen von Threads/Posts ohne Dialog
8. **Kein Rate-Limiting**: Unbegrenzte Login-Versuche moeglich

---

## Dateien zur Bearbeitung

| Datei | Aenderung | Prioritaet |
|-------|-----------|------------|
| register.php | Include-Reihenfolge | Hoch |
| profile.php | E-Mail-Validierung, Passwort-Laenge | Hoch |
| editpost.php | Passwort-Feld entfernen | Mittel |
| includes/config.inc.php | Exception-Handler | Mittel |
| includes/Security.php | Logging-Methoden | Mittel |

---

Erstellt: 2026-01-10
Status: Geplant
