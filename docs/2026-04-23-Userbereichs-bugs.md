# PowerPHPBoard - Userbereich Audit - Bugs

**Datum:** 2026-04-23
**Tester-Rolle:** Senior QA Engineer / Entwickler-Auditor
**Test-URL:** http://localhost:8085/
**Mail-Testserver:** http://localhost:8032 (Mailpit)
**Anwendung:** PowerPHPBoard 1.0 BETA

Diese Datei enthaelt ausschliesslich gefundene **Bugs**. Sie werden dokumentiert, aber **nicht behoben**.

Format je Bug:
- Bereich
- URL / Route
- Reproduktionsschritte
- Erwartet
- Tatsaechlich
- Fehlerart
- Schweregrad (Critical / High / Medium / Low)
- Konsole / Stacktrace
- Netzwerkhinweise
- Status: Offen
- Nicht beheben

---

## BUG-001: Registrierung schlaegt still fehl - Nutzer kann sich nicht registrieren

- **Bereich:** Registrierung / User-Onboarding
- **URL / Route:** `POST /register.php` (Formular `<form action="register.php" method="post">` in `register.php:218`)
- **Reproduktionsschritte:**
  1. Navigiere zu `http://localhost:8085/register.php?catid=0&boardid=0`
  2. Klicke auf "I Agree" (Board Rules akzeptieren) - landet auf `register.php?acception=1&catid=0&boardid=0`
  3. Fuelle das Registrierungsformular mit gueltigen Daten aus (Username, Email, Password, Passwort-Wiederholung)
  4. Sende das Formular ab
- **Erwartet:** Nutzer wird angelegt, Erfolgsmeldung "Registration successful!" erscheint und Willkommens-E-Mail wird an Mailpit zugestellt.
- **Tatsaechlich:** Formular-POST geht an `register.php` (ohne Query-String). Der Server zeigt erneut die Board-Regeln-Seite an. Es wird KEIN Nutzer angelegt. Die Formular-Daten gehen verloren. Keine Fehlermeldung, kein Status.
- **Fehlerart:** Funktional / Logik-Fehler (Parameter-Quelle falsch gewaehlt)
- **Schweregrad:** Critical (Registrierung komplett blockiert)
- **Technische Ursache (zur Doku, nicht beheben):**
  - `register.php:22` liest `$acception = Security::getInt('acception');` - defaultet auf GET-Source.
  - Das Formular auf `register.php:218` setzt `<form action="register.php" method="post">` ohne `?acception=1` in der URL.
  - Der POST sendet `acception=1` nur via $_POST, das Skript liest aber $_GET.
  - Folge: `$acception === 0` ist immer wahr nach einem POST auf das Formular, und Board Rules werden erneut angezeigt.
- **Konsole / Stacktrace:** Keine JS-Konsolenfehler, keine PHP-Fehler im Browser. Server antwortet mit HTTP 200.
- **Netzwerkhinweise:** `POST http://localhost:8085/register.php` → 200 OK. Response-Body enthaelt die Board-Regeln-Seite statt der Success-Meldung oder Fehlermeldung.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-003: Doppelte Benutzernamen werden akzeptiert

- **Bereich:** Registrierung / Datenintegritaet
- **URL / Route:** `POST /register.php?acception=1`
- **Reproduktionsschritte:**
  1. Nutzer mit Username "testuser_qa1" registrieren.
  2. Zweite Registrierung mit unterschiedlicher E-Mail, aber demselben Username "testuser_qa1" ausfuehren.
- **Erwartet:** Fehlermeldung, dass der Benutzername bereits vergeben ist.
- **Tatsaechlich:** Zweiter Datensatz wird angelegt. Die DB enthaelt nun zwei Nutzer mit identischem Username (UID 3 und UID 5). Beim Besuch von `showprofile.php` sind sie nicht voneinander zu unterscheiden.
- **Fehlerart:** Validierung fehlt (Duplicate-Check nur auf `email`, nicht auf `username`)
- **Schweregrad:** High (Account-Verwechslung, Impersonation, Audit-/Moderations-Probleme)
- **Technische Ursache (Doku):** `register.php:138` prueft nur `SELECT id FROM ppb_users WHERE email = ?`. Keine aequivalente Pruefung fuer `username`.
- **Konsole / Stacktrace:** Kein Fehler.
- **Netzwerkhinweise:** POST 200 OK, Response "Your registration was successfull."
- **Status:** Offen
- **Nicht beheben**

---

## BUG-004: Generische Fehlermeldung bei zu langen Eingabefeldern (keine Input-Length-Validierung)

- **Bereich:** Registrierung / Input-Validierung
- **URL / Route:** `POST /register.php?acception=1`
- **Reproduktionsschritte:**
  1. Formular mit Username (200 Zeichen), biography (10000 Zeichen) und signature (5000 Zeichen) absenden.
- **Erwartet:** Klare Fehlermeldung ueber Maximal-Laenge oder serverseitige Truncation mit Warnung.
- **Tatsaechlich:** Generische Meldung "There was an error while registration. Please try again!". Der Nutzer weiss nicht, welches Feld zu lang war. (Ursache: DB-Spaltenlaenge, z. B. `username VARCHAR(50)`, wird ueberschritten - PDO wirft Exception, wird im Catch mit generischer Meldung gefangen.)
- **Fehlerart:** UX / fehlende Input-Laengen-Validierung
- **Schweregrad:** Medium
- **Konsole / Stacktrace:** PDOException im Backend (wird unterdrueckt).
- **Netzwerkhinweise:** POST 200 OK mit generischer Error-Seite.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-005: strip_tags entfernt Script-Tags, hinterlaesst aber sinnlose Artefakte im Username

- **Bereich:** Registrierung / Sanitizing
- **URL / Route:** `POST /register.php?acception=1`
- **Reproduktionsschritte:**
  1. Registrieren mit `username = "<script>alert(1)</script>"`.
  2. Profil unter `showprofile.php?userid=<id>` aufrufen.
- **Erwartet:** Entweder Ablehnung mit Fehler "Ungueltige Zeichen im Username" oder saubere Normalisierung (leere Strings werden abgelehnt).
- **Tatsaechlich:** `strip_tags()` entfernt die Tags, es verbleibt "alert(1)" als gespeicherter Username. Registrierung ist erfolgreich, Profil zeigt "alert(1)" an - irrefuehrend und unprofessionell.
- **Fehlerart:** Sanitizing-Strategie inkonsistent
- **Schweregrad:** Medium (kein XSS, aber Datenqualitaet leidet; Auth-Umgehung bei Namen-Kollisionen denkbar)
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** 200 OK, Registrierung erfolgreich.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-006: User-Enumeration moeglich ueber unterschiedliche Fehlermeldungen beim Login

- **Bereich:** Login / Sicherheit
- **URL / Route:** `POST /login.php` (`login.php:66`, `login.php:92`)
- **Reproduktionsschritte:**
  1. Login mit unbekannter E-Mail `foo@example.com` + beliebigem Passwort → Meldung "There is no user with this eMail in our database!"
  2. Login mit bekannter E-Mail `testuser_qa1@example.com` + falschem Passwort → Meldung "Your password is not correct!"
- **Erwartet:** Einheitliche, neutrale Meldung wie "Email oder Passwort ist falsch".
- **Tatsaechlich:** Angreifer kann durch Vergleich der Meldungen jede E-Mail-Adresse auf Existenz pruefen (Account Harvesting).
- **Fehlerart:** Information Disclosure / Security Best Practice
- **Schweregrad:** Medium (OWASP A07:2021 - Identification and Authentication Failures)
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** Jeweils 200 OK mit unterschiedlichen Responsebodies, Antwortzeiten ~160-190 ms.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-007: Kein Rate-Limit / Account-Lockout bei Brute-Force-Login

- **Bereich:** Login / Sicherheit
- **URL / Route:** `POST /login.php`
- **Reproduktionsschritte:**
  1. 20 aufeinanderfolgende POSTs mit falschem Passwort gegen `testuser_qa1@example.com` abfeuern.
- **Erwartet:** Account-Lockout oder exponentielles Backoff / Captcha nach wenigen fehlgeschlagenen Versuchen.
- **Tatsaechlich:** Alle 20 Requests werden identisch verarbeitet. Konstante Antwortzeit ~170 ms (bcrypt-bedingt), keine Blockierung.
- **Fehlerart:** Fehlender Schutzmechanismus
- **Schweregrad:** High (OWASP A07:2021)
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** 20x 200 OK, jeweils "Your password is not correct!" zurueck.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-008: Logout per GET - CSRF-anfaellig und nicht idempotent geschuetzt

- **Bereich:** Logout / CSRF
- **URL / Route:** `GET /logout.php?logout=1` (`logout.php:23-26`, `logout.php:55`)
- **Reproduktionsschritte:**
  1. Eingeloggter Nutzer oeffnet Seite mit `<img src="http://localhost:8085/logout.php?logout=1">` oder ein Angreifer setzt diesen Link.
  2. Nutzer wird ungewollt ausgeloggt.
- **Erwartet:** Logout per POST mit CSRF-Token, GET-Anfragen werden abgelehnt oder nur als Bestaetigungsseite verwendet.
- **Tatsaechlich:** GET `logout.php?logout=1` zerstoert die Session sofort. Keine CSRF-Pruefung.
- **Fehlerart:** CSRF / unsichere HTTP-Methode
- **Schweregrad:** Low (Auswirkung begrenzt, reiner Annoyance-Faktor - aber OWASP A01)
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** GET-Request fuehrt zu Session::logout().
- **Status:** Offen
- **Nicht beheben**

---

## BUG-009: Login-Meldung "The cookie was set for 360 days" ist irrefuehrend und veraltet

- **Bereich:** Login / UX / Kommunikation
- **URL / Route:** `POST /login.php` (Erfolgspfad)
- **Reproduktionsschritte:**
  1. Gueltigen Login durchfuehren.
  2. Erfolgsbildschirm beobachten.
- **Erwartet:** Aktuelle, korrekte Aussage ueber Session-Dauer und Mechanismus.
- **Tatsaechlich:** Meldung "Your login was successfull. The cookie was set for 360 days." - tatsaechlich wird eine PHP-Session (`Session::login`) verwendet, kein "360-Tage-Cookie". Der Text entstammt dem Legacy-Sprachfile und passt nicht zur aktuellen Implementierung.
- **Fehlerart:** Irrefuehrende / falsche Nutzerinformation
- **Schweregrad:** Low
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** Text kommt aus `deutsch-du.inc.php` / `deutsch-sie.inc.php` / `english.inc.php`.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-010: CRITICAL - Stored XSS durch Nutzer-Signatur (HTML ist global aktiv)

- **Bereich:** Profil / Thread-Rendering / Cross-Site-Scripting
- **URL / Route:** Payload via `POST /profile.php?login=1&editprofile=1` gespeichert; ausgeloest beim Rendern in `showthread.php`
- **Reproduktionsschritte:**
  1. Als Nutzer einloggen (`testuser_qa1`).
  2. Profil bearbeiten: Signatur auf `<script>alert('XSS')</script>` oder `<img src=x onerror=alert(1)>` setzen.
  3. Beliebigen Post in einem Thread erstellen.
  4. Thread unter `showthread.php?threadid=1` aufrufen.
- **Erwartet:** Signatur wird entweder mit `htmlspecialchars` escaped oder HTML strikt gefiltert (nur Whitelist-Tags).
- **Tatsaechlich:** Signatur-Inhalt `<script>var x=1;</script><b>bold</b>mySig` erscheint im Response 1:1. Bestaetigt durch rohen HTML-Output in `showthread.php`. Ein beliebiger Nutzer fuehrt Code bei jedem anderen Nutzer aus, der den Thread ansieht.
- **Fehlerart:** Stored XSS (OWASP A03:2021 - Injection)
- **Schweregrad:** **Critical**
- **Technische Ursache (Doku):**
  - `profile.php:193` und `register.php:168` speichern `$signature` ohne Sanitizing (weder `strip_tags` noch `htmlspecialchars`).
  - Einstellung `ppb_config.htmlcode` ist `ON`. In `TextFormatter::formatPost` wird der Escaping-Pfad (`htmlspecialchars`) uebersprungen, wenn `htmlcode === 'ON'`.
  - `showthread.php:314` ruft `TextFormatter::formatPost($author['signature'], ...)` mit `$settings['htmlcode'] ?? 'ON'`.
- **Konsole / Stacktrace:** JS-Ausfuehrung abhaengig vom Payload. Bei `<script>alert(1)</script>` erscheint Alert-Dialog.
- **Netzwerkhinweise:** Keine Anomalie. Response enthaelt rohen Payload.
- **Session-Cookie:** Mit `HttpOnly` gesetzt - mildert, aber verhindert keine anderen XSS-Angriffe (CSRF-Request-Injection, Formular-Hijack, Defacement).
- **Status:** Offen
- **Nicht beheben**

---

## BUG-011: Profil-Update erzwingt Passwort-Eingabe bei JEDEM Edit

- **Bereich:** Profil / Workflow
- **URL / Route:** `POST /profile.php?login=1&editprofile=1` (`profile.php:107`)
- **Reproduktionsschritte:**
  1. Als Nutzer einloggen.
  2. Profil-Formular aufrufen - `password1` und `password2` sind `required` und leer.
  3. Nur ein unkritisches Feld (z. B. Biography oder Signatur) aendern und speichern.
- **Erwartet:** Nutzer kann Biography/Signatur/ICQ/Homepage aendern, ohne erneut sein Passwort einzugeben. Passwort-Aenderung nur, wenn Felder ausgefuellt wurden.
- **Tatsaechlich:** Formular verlangt `password1 === password2 && strlen >= 6` bei jedem Submit. Ohne Passwort-Eingabe schlaegt jedes Update mit "Please insert values for all fields!" fehl. Zusaetzlich wird das Passwort bei jedem Update mit `password_hash` neu gehasht und ersetzt - was bedeutet, der Nutzer muss sein Passwort mindestens erneut kennen und tippen.
- **Fehlerart:** UX / Workflow-Design
- **Schweregrad:** High (stoert alle Profil-Workflows)
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** Leeres Passwort → 200 OK mit Error-Seite.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-012: Profil-Edit fragt kein aktuelles Passwort vor kritischen Aenderungen ab

- **Bereich:** Profil / Sicherheit
- **URL / Route:** `POST /profile.php?login=1&editprofile=1`
- **Reproduktionsschritte:**
  1. Als Nutzer einloggen.
  2. Im Profil-Formular: Passwort-Felder mit neuem Wert fuellen, E-Mail auf neue Adresse aendern.
  3. Formular absenden.
- **Erwartet:** Vor kritischen Aenderungen (E-Mail, Passwort) wird das aktuelle Passwort abgefragt (Re-Authentifizierung).
- **Tatsaechlich:** Nur das neue Passwort reicht. Ein Angreifer, der eine offene Session uebernimmt (z. B. via XSS - siehe BUG-010), kann E-Mail und Passwort aendern und den Account uebernehmen, ohne das alte Passwort zu kennen.
- **Fehlerart:** Fehlende Re-Authentifizierung fuer sensible Aenderungen (OWASP A04:2021)
- **Schweregrad:** High
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** POST 200 OK, Update erfolgreich.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-013: ICQ-Feld wird mit "0" vorgefuellt, obwohl es leer sein sollte

- **Bereich:** Profil / UX
- **URL / Route:** `GET /profile.php`
- **Reproduktionsschritte:**
  1. Neuen Nutzer ohne ICQ registrieren.
  2. `/profile.php` aufrufen.
- **Erwartet:** ICQ-Feld leer.
- **Tatsaechlich:** Feldwert `0` wird angezeigt. Wenn Nutzer einfach speichert, bleibt `0` drin. Irritierend.
- **Fehlerart:** UX / Darstellung
- **Schweregrad:** Low
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** Keine.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-014: Posting-Formulare akzeptieren Email+Password als alternative Authentifizierung (parallele Auth-Wege)

- **Bereich:** Posting / Authentifizierung
- **URL / Route:** `POST /newpost.php`, `POST /newthread.php`, `POST /editpost.php`
- **Reproduktionsschritte:**
  1. Ohne eingeloggte Session direkt an `/newpost.php?threadid=1&newpost=1&current=0` posten mit `email=testuser_qa1@example.com&password=TestPass123!`.
  2. Post wird erfolgreich erstellt und im Thread angezeigt (als Autor korrekt erkannt).
- **Erwartet:** Posting erfordert entweder nur eine aktive Session (empfohlen) oder vereinheitlichten Login-Flow. Keine Passwort-Eingabe in Posting-Formularen.
- **Tatsaechlich:** Posting-Formulare akzeptieren Email+Password-Paare ohne Rate-Limit und ohne CSRF-Bindung an Login. Somit sind diese Endpoints als zusaetzliche Brute-Force-Vektoren nutzbar (neben dem Login-Formular).
- **Fehlerart:** Sicherheit / Design
- **Schweregrad:** High
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** 200 OK, Post erscheint in Thread.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-015: Sehr lange Post-Texte schlagen stumm fehl (keine Error-Anzeige)

- **Bereich:** Posting / Input-Validierung
- **URL / Route:** `POST /newpost.php`
- **Reproduktionsschritte:**
  1. `POST /newpost.php` mit `text` von 200.000 Zeichen abschicken.
- **Erwartet:** Klare Fehlermeldung "Post zu lang" oder serverseitige Truncation mit Warnung.
- **Tatsaechlich:** Response enthaelt weder "successfully" noch "Error message" - Nutzer sieht eine leere Formularseite, weiss nicht, was passiert ist. Wahrscheinlich DB-Field-Overflow ohne handled Exception.
- **Fehlerart:** Silent Failure / fehlende Groessenpruefung
- **Schweregrad:** Medium
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** 200 OK mit leerem/Fallback-Response.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-016: CRITICAL - sendpassword.php ermoeglicht Account-Lockout/Takeover jedes Accounts

- **Bereich:** Passwort-Reset / Sicherheit
- **URL / Route:** `POST /sendpassword.php?send=1`
- **Reproduktionsschritte:**
  1. Als **anonymer** Nutzer (nicht eingeloggt) das Reset-Formular auf `/sendpassword.php` aufrufen.
  2. Beliebige bekannte Nutzer-Email (z. B. `testuser_qa1@example.com`) eingeben und abschicken.
  3. Erfolgsmeldung "The login information was sent to testuser_qa1@example.com" erscheint.
  4. Danach mit altem Passwort `TestPass123!` einloggen - scheitert mit "Your password is not correct!".
- **Erwartet:** Passwort-Reset muss ueber einen zeitlich begrenzten, einmaligen Token-Link laufen. Erst beim Klick auf den Link wird das Passwort geaendert. Das aktuelle Passwort bleibt bis zur Bestaetigung gueltig.
- **Tatsaechlich:**
  - `sendpassword.php:89-93` generiert sofort ein neues Passwort (`bin2hex(random_bytes(8))`) und ueberschreibt das bestehende per `UPDATE ppb_users SET password = ? WHERE id = ?`.
  - Das neue Passwort wird nur per E-Mail verschickt (die aktuell nicht zustellbar ist, siehe BUG-002).
  - Kein Token, keine Bestaetigung, keine Expiry.
  - Folge: Jeder, der eine E-Mail-Adresse eines Nutzers kennt, kann diesen Account permanent aussperren (Account-Denial-of-Service). Bei funktionierender Mail + MITM/Logging-Endpoint des Angreifers sogar Account-Takeover.
- **Fehlerart:** Security-Design (OWASP A07 - Authentication)
- **Schweregrad:** **Critical**
- **Konsole / Stacktrace:** Keine.
- **Netzwerkhinweise:** POST 200 OK, Success-Seite.
- **Verifikation:** DB-Aenderung nachweisbar ueber misslungenen Login mit altem Passwort direkt nach Reset.
- **Status:** Offen
- **Nicht beheben**

---

## BUG-017: sendpassword.php betreibt User-Enumeration

- **Bereich:** Passwort-Reset / Information Disclosure
- **URL / Route:** `POST /sendpassword.php?send=1`
- **Reproduktionsschritte:**
  1. Reset-Formular mit bekannter Email abschicken → "The login information was sent to ..."
  2. Reset-Formular mit unbekannter Email abschicken → "There is no user with this eMail in our database!"
- **Erwartet:** Gleiche, neutrale Meldung in beiden Faellen (z. B. "Wenn die Email existiert, erhaelt sie Anweisungen").
- **Tatsaechlich:** Unterschiedliche Meldungen erlauben Account-Harvesting.
- **Fehlerart:** Information Disclosure
- **Schweregrad:** Medium (kombiniert mit BUG-016 sehr gefaehrlich)
- **Status:** Offen
- **Nicht beheben**

---

## BUG-018: sendpassword.php ohne Rate-Limit - Mass-Account-Lockout moeglich

- **Bereich:** Passwort-Reset / Rate-Limit
- **URL / Route:** `POST /sendpassword.php?send=1`
- **Reproduktionsschritte:**
  1. Sammle Liste aller registrierten E-Mail-Adressen (siehe BUG-017 fuer Enumeration oder BUG-022 fuer showprofile).
  2. POST Reset-Requests in Serie gegen alle Accounts.
- **Erwartet:** Rate-Limit oder Captcha oder Backoff.
- **Tatsaechlich:** Keine Beschraenkung. Alle Nutzer-Accounts lassen sich in wenigen Sekunden zuruecksetzen.
- **Fehlerart:** Fehlender Schutzmechanismus
- **Schweregrad:** High
- **Status:** Offen
- **Nicht beheben**

---

## BUG-002: Willkommens-E-Mail erreicht Mailpit nicht (Mail wird stumm verschluckt)

- **Bereich:** Registrierung / E-Mail-Versand
- **URL / Route:** `POST /register.php?acception=1` (Erfolgreicher Registrierungspfad, `register.php:182`)
- **Reproduktionsschritte:**
  1. Registrierung via Workaround erfolgreich durchfuehren (acception=1 in URL)
  2. Seite zeigt "Your registration was successfull" an → Registrierung steht in DB
  3. Mailpit-UI auf http://localhost:8032 oeffnen
  4. API `GET http://localhost:8032/api/v1/messages?limit=5` aufrufen
- **Erwartet:** Willkommens-Mail an `testuser_qa1@example.com` im Mailpit-Posteingang sichtbar.
- **Tatsaechlich:** Mailpit liefert `{"total":0,"unread":0,"count":0,"messages":[]}`. Keine Mail zugestellt.
- **Fehlerart:** Integrations-/Konfigurationsfehler
- **Schweregrad:** High (Nutzer erhaelt keine Bestaetigung; bei Passwort-Reset kritisch)
- **Technische Ursache (zur Doku, nicht beheben):**
  - `register.php:182` nutzt `@mail(...)` - der `@`-Operator unterdrueckt Fehler.
  - Kein Fallback auf PHPMailer/SMTP-Konfiguration sichtbar. PHP `mail()` bedarf einer funktionierenden sendmail- oder SMTP-Bruecke zu Mailpit (Standardport 1025).
  - Gleiches Muster in `sendpassword.php:106`, `sendmail.php:121`, `admin/adduser.php:136`.
- **Konsole / Stacktrace:** Keine JS-Fehler. Kein PHP-Error im Browser. Keine Log-Ausgabe.
- **Netzwerkhinweise:** Kein ausgehender SMTP-Traffic an Mailpit erkennbar. Mailpit-API antwortet mit 0 Messages.
- **Status:** Offen
- **Nicht beheben**

---

