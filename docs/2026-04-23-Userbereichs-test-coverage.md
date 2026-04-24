# PowerPHPBoard - Userbereich Audit - Test Coverage

**Datum:** 2026-04-23
**Tester-Rolle:** Senior QA Engineer / Entwickler-Auditor
**Test-URL:** http://localhost:8085/
**Mail-Testserver:** http://localhost:8032 (Mailpit)
**Anwendung:** PowerPHPBoard 1.0 BETA

## Status-Legende
- GETESTET - Vollstaendig geprueft, Befunde dokumentiert
- TEILWEISE - Teilweise geprueft, weitere Abdeckung empfohlen
- BLOCKIERT - Test nicht moeglich (Vorbedingung fehlt)
- OFFEN - Noch nicht geprueft

## Testmatrix - Userbereich

| # | Bereich | Route | Status | Befund |
|---|---------|-------|--------|--------|
| 1 | Startseite / Index (nicht eingeloggt) | `index.php` | GETESTET | OK. Zeigt Boardlist, Login/Profile/Register/Statistics. |
| 2 | Startseite / Index (eingeloggt) | `index.php` | GETESTET | OK. Zeigt "Logged in as ...", Logout-Link. Session persistiert zwischen Navigation. |
| 3 | Registrierung - Boardregeln | `register.php?acception=0` | GETESTET | OK. I Agree / I Disagree sichtbar. |
| 4 | Registrierung - Formular sichtbar | `register.php?acception=1` | GETESTET | OK. Formular mit allen Feldern wird gerendert. |
| 5 | Registrierung - POST ohne `?acception=1` | `POST /register.php` | GETESTET | **BUG-001 Critical** - Form postet ohne Query-String → Board-Regeln statt Verarbeitung. |
| 6 | Registrierung - Workaround (`?acception=1` in URL) | `POST /register.php?acception=1` | GETESTET | Workflow funktioniert nur so. Benutzer in DB angelegt. |
| 7 | Registrierung - Duplicate Email | `POST /register.php?acception=1` | GETESTET | OK. Meldung "This eMail adress already exists in our database!". |
| 8 | Registrierung - Duplicate Username | `POST /register.php?acception=1` | GETESTET | **BUG-003 High** - Zweiter Nutzer mit gleichem Username wird akzeptiert. |
| 9 | Registrierung - Leeres Feld (Username) | `POST /register.php?acception=1` | GETESTET | OK. "Please insert values for all fields!". |
| 10 | Registrierung - Passwoerter unterschiedlich | `POST /register.php?acception=1` | GETESTET | OK. "Your both passwords are different!". |
| 11 | Registrierung - Emails unterschiedlich | `POST /register.php?acception=1` | GETESTET | OK. "Your both eMail adresses are different!". |
| 12 | Registrierung - Email invalide | `POST /register.php?acception=1` | GETESTET | OK. "Your eMail adresss is not correct!". |
| 13 | Registrierung - Passwort < 6 Zeichen | `POST /register.php?acception=1` | GETESTET | OK. "Password must be at least 6 characters". |
| 14 | Registrierung - CSRF ungueltig | `POST /register.php?acception=1` | GETESTET | OK. "Security token invalid". |
| 15 | Registrierung - XSS im Username | `POST /register.php?acception=1` | GETESTET | **BUG-005 Medium** - `strip_tags` entfernt Tags, hinterlaesst "alert(1)" als Username. |
| 16 | Registrierung - Felder > DB-Limit | `POST /register.php?acception=1` | GETESTET | **BUG-004 Medium** - Generische Fehlermeldung aus PDOException. |
| 17 | Registrierung - Willkommensmail via Mailpit | `/api/v1/messages` | GETESTET | **BUG-002 High** - Mailpit erhaelt keine Mail (silent `@mail()`). |
| 18 | Login - Formular | `login.php` | GETESTET | OK. Email + Password Felder, CSRF-Token, Link zu Register und sendpassword. |
| 19 | Login - gueltige Credentials | `POST /login.php` | GETESTET | OK. "Login successfull. Cookie was set for 360 days" (**BUG-009**). Header wechselt zu Logged-in Zustand. |
| 20 | Login - Falsches Passwort | `POST /login.php` | GETESTET | OK (aber BUG-006). "Your password is not correct!". |
| 21 | Login - Unbekannte Email | `POST /login.php` | GETESTET | **BUG-006 Medium** - "There is no user with this eMail in our database!" (User-Enumeration). |
| 22 | Login - Leere Email / Passwort | `POST /login.php` | GETESTET | OK. Feldspezifische Meldungen. |
| 23 | Login - CSRF ungueltig | `POST /login.php` | GETESTET | OK. "Security token invalid". |
| 24 | Login - SQL-Injection-Versuch | `POST /login.php` | GETESTET | OK. Prepared Statements wirken, kein Injection. |
| 25 | Login - 20x Brute-Force hintereinander | `POST /login.php` | GETESTET | **BUG-007 High** - Kein Rate-Limit, Account wird nicht gesperrt. |
| 26 | Logout - Bestaetigungsseite | `logout.php` | GETESTET | OK. Fragt "Do you really want to logout?". |
| 27 | Logout - GET `?logout=1` | `GET /logout.php?logout=1` | GETESTET | **BUG-008 Low** - GET-Logout ist CSRF-anfaellig. |
| 28 | Session Persistenz | Browser | GETESTET | OK. Cookie HttpOnly, bleibt ueber Navigation. |
| 29 | Profil - Ansicht eingeloggt | `GET /profile.php` | GETESTET | Formular wird gerendert, Felder vorbefuellt. **BUG-013 Low** - ICQ vorbefuellt mit `0`. |
| 30 | Profil - Ansicht ausgeloggt | `GET /profile.php` | GETESTET | OK. "Please log in first". |
| 31 | Profil - Daten bearbeiten ohne Passwort | `POST /profile.php?login=1&editprofile=1` | GETESTET | **BUG-011 High** - Update schlaegt fehl weil Passwort required. |
| 32 | Profil - Daten bearbeiten mit korrektem Wiederholungs-Passwort | `POST /profile.php?login=1&editprofile=1` | GETESTET | OK. "Profile updated successfully". |
| 33 | Profil - Passwort zu kurz | `POST /profile.php?login=1&editprofile=1` | GETESTET | OK. "Password must be at least 6 characters". |
| 34 | Profil - Passwoerter unterschiedlich | `POST /profile.php?login=1&editprofile=1` | GETESTET | OK. "Your both passwords are different!". |
| 35 | Profil - Emails unterschiedlich | `POST /profile.php?login=1&editprofile=1` | GETESTET | OK. "Your both eMail adresses are different!". |
| 36 | Profil - Email-Duplicate bei andere Nutzer | `POST /profile.php?login=1&editprofile=1` | TEILWEISE | Eigene Email wird erlaubt. Fremder Duplikat wurde nicht gezielt getestet, da nur ein aktiver Testnutzer. |
| 37 | Profil - CSRF ungueltig | `POST /profile.php?login=1&editprofile=1` | GETESTET | OK. "Security token invalid". |
| 38 | Profil - XSS in Biography | `POST /profile.php?login=1&editprofile=1` | GETESTET | OK in showprofile (nl2br + escape). Nur harmlose Textreste sichtbar. |
| 39 | Profil - XSS in Signatur (Rendering in Thread) | `GET /showthread.php?threadid=1` | GETESTET | **BUG-010 Critical** - Signatur wird als HTML gerendert, Stored-XSS. |
| 40 | Profil - Email aendern ohne Altpasswort-Abfrage | `POST /profile.php?login=1&editprofile=1` | GETESTET | **BUG-012 High** - Keine Re-Auth. |
| 41 | Passwort vergessen - Formular | `GET /sendpassword.php` | GETESTET | OK. Email-Feld + CSRF. |
| 42 | Passwort vergessen - gueltige Email | `POST /sendpassword.php?send=1` | GETESTET | **BUG-016 Critical** - Passwort wird sofort in DB ueberschrieben. Alter Login schlaegt fehl. |
| 43 | Passwort vergessen - unbekannte Email | `POST /sendpassword.php?send=1` | GETESTET | **BUG-017 Medium** - User-Enumeration via Fehlermeldung. |
| 44 | Passwort vergessen - Mail Zustellung | Mailpit | GETESTET | **BUG-002 High** - Keine Mail kommt an. |
| 45 | Passwort vergessen - ohne Rate-Limit | `POST /sendpassword.php?send=1` | GETESTET | **BUG-018 High** - Mass-Account-Lockout moeglich. |
| 46 | Board Index / Kategorien | `GET /index.php?catid=X` | GETESTET | OK. Zeigt Kategorien. |
| 47 | Board anzeigen | `GET /showboard.php?boardid=3` | GETESTET | OK. Zeigt Threads. |
| 48 | Thread anzeigen | `GET /showthread.php?threadid=1` | GETESTET | OK. Zeigt Posts + Signaturen (siehe BUG-010). |
| 49 | Neuer Thread - Formular | `GET /newthread.php?boardid=3` | GETESTET | OK. Form vorhanden, Title+Text+Icons. |
| 50 | Neuer Thread - POST valide | `POST /newthread.php?boardid=3&newthread=1` | GETESTET | OK. Thread erstellt, erscheint im Board. |
| 51 | Neuer Thread - POST leerer Titel | idem | GETESTET | OK. "Please insert values for all fields!". |
| 52 | Neuer Thread - POST leerer Text | idem | GETESTET | OK. "Please insert values for all fields!". |
| 53 | Neuer Thread - CSRF ungueltig | idem | GETESTET | OK. "Security token invalid". |
| 54 | Neuer Post - Formular | `GET /newpost.php?threadid=1` | GETESTET | OK. Textarea sichtbar. |
| 55 | Neuer Post - POST valide | `POST /newpost.php?threadid=1&newpost=1` | GETESTET | OK. Post erstellt, sichtbar in Thread. |
| 56 | Neuer Post - leerer Text | idem | GETESTET | OK. "Please insert values for all fields!". |
| 57 | Neuer Post - whitespace only | idem | GETESTET | OK. Geblockt. |
| 58 | Neuer Post - sehr langer Text | idem | GETESTET | **BUG-015 Medium** - Stumme Fehlerseite. |
| 59 | Neuer Post - CSRF ungueltig | idem | GETESTET | OK. "Security token invalid". |
| 60 | Neuer Post - BBCode | idem | GETESTET | OK. BBCode wird akzeptiert (Rendering nicht visuell verifiziert). |
| 61 | Neuer Post - Guest Posting ohne Session | idem | GETESTET | **BUG-014 High** - Email+Password im Post-Formular, parallele Authentifizierung. |
| 62 | Neuer Post - Guest ohne Credentials | idem | GETESTET | OK. Geblockt. |
| 63 | Post bearbeiten - Formular (eigenen Post) | `GET /editpost.php?postid=3` | GETESTET | OK. Formular sichtbar, Text vorbefuellt. |
| 64 | Post bearbeiten - POST eigener Post | `POST /editpost.php?postid=3&login=1` | GETESTET | OK. "You edited the posting successfully!". |
| 65 | Post bearbeiten - GET fremder Post | `GET /editpost.php?postid=1` | GETESTET | OK. "You are not allowed to edit this post!". |
| 66 | Post bearbeiten - POST mit gueltigem CSRF auf fremden Post | `POST /editpost.php?postid=1&login=1` | GETESTET | OK. Server verweigert. Access Control wirkt. |
| 67 | Post bearbeiten - leerer Text | idem | TEILWEISE | Pfad existiert (`$lang_inserttext`), nicht separat reproduziert. |
| 68 | BBCode Referenz | `GET /bbcode.php` | GETESTET | OK. Seite laedt. |
| 69 | Smilies Referenz | `GET /smilies.php` | GETESTET | OK. Seite laedt. |
| 70 | Statistiken | `GET /statistics.php` | GETESTET | OK. Zeigt 5 Nutzer, 3 Threads, 7 Posts. Sehr minimal. |
| 71 | Fremdes Profil anzeigen (valide ID) | `GET /showprofile.php?userid=3` | GETESTET | OK. Zeigt Daten escaped, Biography mit nl2br. |
| 72 | Fremdes Profil (userid=0) | `GET /showprofile.php?userid=0` | GETESTET | OK. "Please choose a user". |
| 73 | Fremdes Profil (userid nicht vorhanden) | `GET /showprofile.php?userid=99999` | GETESTET | OK. "No user with this ID". |
| 74 | Fremdes Profil (userid negativ) | `GET /showprofile.php?userid=-1` | GETESTET | OK. "No user with this ID". |
| 75 | Fremdes Profil (userid nicht-numerisch) | `GET /showprofile.php?userid=abc` | GETESTET | OK. `Security::getInt` gibt 0 → "Please choose a user". |
| 76 | IP anzeigen (nicht-Admin, nicht-Mod) | `GET /showip.php?postid=3&threadid=1` | GETESTET | OK. "Only administrators and moderators can view IP addresses" (eingeloggt als Civilian). |
| 77 | IP anzeigen ohne threadid | `GET /showip.php?postid=3` | GETESTET | OK. "Please choose a post" (beide IDs erforderlich - UX-verwirrend). |
| 78 | Sendmail-Funktion ohne Login | `GET /sendmail.php?userid=3` | GETESTET | OK. "You have to log in first!". |
| 79 | Sendmail-Funktion eingeloggt | `GET /sendmail.php?userid=3` | TEILWEISE | Durchgetestet bis Formular, Mailversand nicht verifiziert (BUG-002). |
| 80 | Header / Footer Navigation | global | GETESTET | OK. Je nach Login-Status unterschiedlich (Login/Logout). |

## Audit-Fortschritt

- Gesamt: 80 Testfaelle
- GETESTET: 77
- TEILWEISE: 3
- BLOCKIERT: 0
- OFFEN: 0

---

## Abschlussbericht

### Zusammenfassung der Schwere

| Schweregrad | Anzahl Bugs |
|-------------|-------------|
| **Critical** | 3 (BUG-001 Registrierung, BUG-010 Stored XSS, BUG-016 Password-Reset-Lockout) |
| **High** | 6 (BUG-002 Mail, BUG-003 Duplicate Username, BUG-007 Rate-Limit, BUG-011 Profil-Password-Pflicht, BUG-012 Email ohne Re-Auth, BUG-014 parallele Auth-Pfade, BUG-018 Mass-Lockout) |
| **Medium** | 6 (BUG-004 Generic Error, BUG-005 strip_tags Artefakt, BUG-006 User-Enumeration Login, BUG-015 Silent Long Post, BUG-017 User-Enumeration Reset) |
| **Low** | 3 (BUG-008 Logout CSRF, BUG-009 Legacy-Text "360 days", BUG-013 ICQ=0) |

Gesamt: **18 Bugs** in `2026-04-23-Userbereichs-bugs.md`.

### Kritischer Pfad

Mehrere kritische Bugs haengen zusammen und ergeben zusammen einen besonders gravierenden Angriffspfad:

1. **BUG-001** blockt normale Registrierung komplett - ohne Workaround gibt es keine neuen Nutzer.
2. **BUG-016** + **BUG-017** + **BUG-018** zusammen: Angreifer kann jeden Account per bekannter E-Mail-Adresse dauerhaft sperren.
3. **BUG-002** blockiert die einzige Benachrichtigungskette - Nutzer erhalten weder Willkommens- noch Reset-Mail.
4. **BUG-010** (Stored XSS via Signatur) ermoeglicht Account-Takeover anderer Nutzer in Kombination mit **BUG-012** (keine Re-Auth bei Email-Wechsel).

### Positive Feststellungen

- PDO mit Prepared Statements durchgaengig eingesetzt - kein SQL-Injection-Vektor gefunden.
- Passwoerter mit `password_hash` + `needsRehash` Migration von Legacy-Base64.
- Session-Cookies korrekt als HttpOnly gesetzt.
- CSRF-Schutz in allen kritischen POST-Endpoints (Login, Register, Profile, Newpost, Newthread, Editpost, Sendpassword).
- Access-Control bei `editpost.php`, `showip.php`, `sendmail.php` greift.
- `showprofile.php` escaped Biography und Username korrekt (kein Reflected XSS dort).

### Test-Coverage-Luecken

Folgende Bereiche wurden nur teilweise getestet und koennten eigene Folgeaudits erhalten:
- Moderator-spezifische Funktionen (Thread close/open/delete).
- Private Boards mit Board-Password (`board.status = 'Private'`, POST `boardpassword`).
- Admin-Panel (`/admin/*`) - war nicht Teil dieses User-Audits.
- BBCode-Rendering-Tests (Injection in BBCode-Tags).
- Mailversand-Funktionalitaet bei korrekt konfigurierter Umgebung (ausserhalb Scope).
- Reply/Quote-Workflow (newpost.php?postid=X).

### Erledigte Verbesserungsvorschlaege

20 Vorschlaege in `2026-04-23-Userbereichs-improvements.md` dokumentiert, priorisiert nach Einfluss auf Sicherheit und UX.

### Audit-Abschluss

Alle erreichbaren Bereiche des Userbereichs wurden geprueft und dokumentiert. Keine Bugs behoben, keine Features umgebaut.


### Fix-Pass 2026-04-23 abends

Alle 18 Bugs wurden in einer Folge-Session behoben. Siehe `docs/2026-04-23-Userbereichs-bugs.md` Abschnitt "Fix-Zusammenfassung" sowie die Commits ab `fda6b8a` im Git-Log. Fuer neu eingefuehrte Helper-Klassen (Validator, RateLimiter, Mailer) existieren Unit-Tests (ValidatorTest, RateLimiterTest, MailerTest). Gesamt: 137 Unit-Tests / 192 Tests insgesamt gruen.

AUDIT_COMPLETE
