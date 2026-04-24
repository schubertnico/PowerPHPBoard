# Sicherheitsrichtlinien

## Unterstuetzte Versionen

| Version | Unterstuetzt |
|---------|--------------|
| 2.0.x   | Ja           |
| 1.x     | Nein         |

## Sicherheitsluecke melden

Wenn Sie eine Sicherheitsluecke entdecken, melden Sie diese bitte **nicht** oeffentlich ueber GitHub Issues.

### Meldeprozess

1. **E-Mail senden an:** security@powerscripts.org
2. **Betreff:** `[SECURITY] PowerPHPBoard - Kurzbeschreibung`
3. **Inhalt:**
   - Detaillierte Beschreibung der Schwachstelle
   - Schritte zur Reproduktion
   - Betroffene Version(en)
   - Moegliche Auswirkungen
   - Optional: Vorgeschlagene Loesung

### Antwortzeit

- **Bestaetigung:** Innerhalb von 48 Stunden
- **Erste Einschaetzung:** Innerhalb von 7 Tagen
- **Fix-Veroeffentlichung:** Abhaengig von Schweregrad

### Schweregrad-Klassifizierung

| Stufe | Beschreibung | Beispiele |
|-------|--------------|-----------|
| Kritisch | Remote Code Execution, SQL Injection | Unautorisierter DB-Zugriff |
| Hoch | Auth Bypass, XSS (stored) | Session Hijacking |
| Mittel | XSS (reflected), CSRF | Datenmanipulation |
| Niedrig | Information Disclosure | Versionsnummer-Leak |

---

## Implementierte Sicherheitsmassnahmen

### 1. SQL Injection Praevention

**Alle** Datenbankabfragen verwenden PDO Prepared Statements.

```php
// RICHTIG - Prepared Statement
$user = $db->fetchOne(
    "SELECT * FROM ppb_users WHERE email = ?",
    [$email]
);

// FALSCH - String-Konkatenation (NIEMALS!)
$user = $db->fetchOne(
    "SELECT * FROM ppb_users WHERE email = '$email'"
);
```

**Implementierung:** `includes/Database.php`

### 2. Cross-Site Scripting (XSS) Praevention

Alle Ausgaben werden mit `htmlspecialchars()` escaped.

```php
// RICHTIG - Escaped
echo Security::escape($userInput);
echo Security::e($userInput);  // Kurzform

// FALSCH - Unescaped (NIEMALS!)
echo $userInput;
```

**Flags:** `ENT_QUOTES | ENT_HTML5`
**Encoding:** `UTF-8`

**Implementierung:** `includes/Security.php`

### 3. Cross-Site Request Forgery (CSRF) Schutz

Alle Formulare sind mit CSRF-Tokens geschuetzt.

```php
// In Formularen
echo '<form method="post">';
echo CSRF::getTokenField();
// ...
echo '</form>';

// Bei Verarbeitung
if (!CSRF::validateFromPost()) {
    die('CSRF validation failed');
}
```

**Token-Laenge:** 64 Zeichen (32 Bytes Hex)
**Speicherort:** `$_SESSION['csrf_token']`
**Validierung:** `hash_equals()` (Timing-Attack-sicher)

**Implementierung:** `includes/CSRF.php`

### 4. Passwort-Sicherheit

Passwoerter werden mit Argon2id gehasht.

```php
// Hashen
$hash = Security::hashPassword($password);

// Verifizieren
if (Security::verifyPassword($password, $hash)) {
    // Login erfolgreich
}

// Legacy-Migration
if (Security::needsRehash($hash)) {
    $newHash = Security::hashPassword($password);
    // Neuen Hash speichern
}
```

**Algorithmus:** Argon2id (PHP 7.3+) oder bcrypt als Fallback
**Legacy-Unterstuetzung:** Base64-kodierte Passwoerter werden erkannt und bei Login migriert

**Implementierung:** `includes/Security.php`

### 5. Session-Sicherheit

Sichere Session-Konfiguration mit Regeneration.

```php
// Session starten
Session::start();

// Nach Login: Session-ID regenerieren
Session::login($userId);

// Sichere Cookie-Einstellungen
session_set_cookie_params([
    'lifetime' => PPB_SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

**Flags:**
- `httponly: true` - Kein JavaScript-Zugriff
- `secure: true` - Nur ueber HTTPS (wenn verfuegbar)
- `samesite: Lax` - CSRF-Schutz auf Cookie-Ebene

**Implementierung:** `includes/Session.php`

### 6. Eingabevalidierung

Alle Benutzereingaben werden validiert und sanitiert.

```php
// Integer sicher auslesen
$id = Security::getInt('id', 'GET', 0);

// String sicher auslesen (getrimmt)
$name = Security::getString('name', 'POST', '');

// E-Mail validieren
if (!Security::isValidEmail($email)) {
    die('Ungueltige E-Mail');
}
```

**Implementierung:** `includes/Security.php`

### 7. Security Event Logging

Sicherheitsrelevante Ereignisse werden geloggt.

```php
// Fehlgeschlagener Login
ErrorHandler::logFailedLogin($email, 'invalid_password');

// CSRF-Fehler
ErrorHandler::logCsrfFailure('/profile.php');

// Berechtigungsfehler
ErrorHandler::logPermissionDenied('edit_post', $userId);
```

**Log-Datei:** `logs/security.log`
**Format:** `[Datum] SECURITY [Event] User:ID IP:Adresse | {Details}`

**Implementierung:** `includes/ErrorHandler.php`

---

## Sicherheits-Checkliste fuer Entwickler

### Bei neuem Code

- [ ] Alle DB-Queries als Prepared Statements
- [ ] Alle Ausgaben mit `Security::escape()` escapen
- [ ] CSRF-Token in Formularen einbinden
- [ ] CSRF-Validierung bei POST-Verarbeitung
- [ ] Eingaben mit `Security::getInt()` / `Security::getString()` auslesen
- [ ] Berechtigungen pruefen (`Session::isLoggedIn()`, Admin-Check)
- [ ] Passwoerter mit `Security::hashPassword()` hashen
- [ ] Sensible Aktionen loggen

### Bei Code-Review

- [ ] Keine String-Konkatenation in SQL-Queries
- [ ] Keine direkten `echo $_GET/$_POST` Ausgaben
- [ ] Keine Verwendung von `eval()`, `exec()`, `system()`
- [ ] Keine `$_REQUEST` Verwendung (nutze `$_GET` oder `$_POST`)
- [ ] Keine direkten Datei-Includes mit Benutzereingaben
- [ ] Keine Speicherung von Passwoertern in Klartext oder Base64

---

## Bekannte Sicherheitsaspekte

### Legacy-Passwoerter

Das System unterstuetzt noch Base64-kodierte Passwoerter aus Version 1.x.
Diese werden automatisch bei erfolgreichem Login zu Argon2id migriert.

```php
if (Security::needsRehash($user['password'])) {
    $newHash = Security::hashPassword($password);
    $db->query("UPDATE ppb_users SET password = ? WHERE id = ?",
               [$newHash, $user['id']]);
}
```

### Board-Passwoerter

Private Boards verwenden noch Base64-Encoding fuer Passwoerter.
Dies ist kein Sicherheitsrisiko, da diese Passwoerter nicht fuer Authentifizierung verwendet werden.

### Rate-Limiting

Aktuell kein serverseitiges Rate-Limiting implementiert.
**Empfehlung:** Fail2ban oder aehnliche Tools auf Server-Ebene einsetzen.

---

## Sicherheits-Updates

### Update-Prozess

1. Repository aktualisieren: `git pull`
2. Dependencies aktualisieren: `composer update`
3. Tests ausfuehren: `composer test`
4. Cache leeren (falls vorhanden)

### Security Advisories

Abonnieren Sie GitHub Security Advisories fuer dieses Repository:
https://github.com/schubertnico/PowerPHPBoard/security/advisories

---

## Kontakt

- **Security-E-Mail:** security@powerscripts.org
- **Allgemein:** info@powerscripts.org
- **Website:** https://powerscripts.org