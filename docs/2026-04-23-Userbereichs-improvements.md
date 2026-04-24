# PowerPHPBoard - Userbereich Audit - Improvements

**Datum:** 2026-04-23
**Tester-Rolle:** Senior QA Engineer / Entwickler-Auditor
**Test-URL:** http://localhost:8085/
**Anwendung:** PowerPHPBoard 1.0 BETA

Diese Datei enthaelt Vorschlaege zur Verbesserung von **Workflow** und **UX**. Sie werden dokumentiert, aber **nicht umgesetzt**.

Format je Verbesserung:
- Bereich
- URL / Route
- Beobachtung
- Problem im Workflow
- Auswirkung
- Verbesserungsvorschlag
- Prioritaet (High / Medium / Low)

---

## IMPR-001: Form-Action im Register-Flow muss `?acception=1` enthalten

- **Bereich:** Registrierung
- **URL / Route:** `register.php:218`
- **Beobachtung:** Form `action="register.php"` ohne Query-String, aber Code liest `acception` aus $_GET. Siehe BUG-001.
- **Problem im Workflow:** Normaler Registrierungsflow ist komplett kaputt.
- **Auswirkung:** Critical - Registrierung unmoeglich.
- **Verbesserungsvorschlag:** Entweder (a) Form-Action auf `register.php?acception=1` setzen, oder (b) `Security::getInt('acception', 'REQUEST')` verwenden (konsistenter und robuster). Option (b) bevorzugt, weil auch andere Formulare den gleichen Fallstrick haben koennen.
- **Prioritaet:** High

---

## IMPR-002: Passwort-Anforderungen sind zu schwach (nur 6 Zeichen Mindestlaenge)

- **Bereich:** Registrierung / Profil
- **URL / Route:** `register.php:110`, `profile.php:143`
- **Beobachtung:** Mindestlaenge 6 Zeichen, keine Komplexitaetsanforderungen.
- **Problem im Workflow:** Passwoerter wie "123456", "qwerty", "abcdef" werden akzeptiert.
- **Auswirkung:** Brute-Force und Woerterbuch-Angriffe sehr einfach; kein NIST-Standard erfuellt (min. 8, besser 12).
- **Verbesserungsvorschlag:** Mindestens 8 Zeichen, Pruefung gegen bekannte schwache Passwoerter (have-i-been-pwned) oder Komplexitaetsregel (z. B. 3 von 4 Zeichenklassen).
- **Prioritaet:** High

---

## IMPR-003: Kein Rate-Limit / Captcha fuer Registrierung

- **Bereich:** Registrierung
- **URL / Route:** `register.php`
- **Beobachtung:** Formular erlaubt beliebig viele POSTs. Automatisierte Registrierung ist trivial.
- **Problem im Workflow:** Spam-Accounts koennen massenhaft erzeugt werden.
- **Auswirkung:** Spam in Threads, DB-Fuellstand, E-Mail-Bombing.
- **Verbesserungsvorschlag:** Captcha (hCaptcha/Turnstile) oder serverseitige IP-Rate-Limits mit Backoff.
- **Prioritaet:** Medium

---

## IMPR-004: Input-Length-Limits serverseitig synchronisieren mit DB-Schema

- **Bereich:** Registrierung / Profil
- **URL / Route:** `register.php`, `profile.php`
- **Beobachtung:** HTML `maxlength`-Attribute existieren, werden aber durch Fetch/curl umgangen. Serverseitig kein Laengencheck, nur DB-Error als Meldung (siehe BUG-004).
- **Problem im Workflow:** Generische Fehlermeldung bei legitimen Grenzfaellen.
- **Auswirkung:** Schlechte UX, unklar was zu korrigieren ist.
- **Verbesserungsvorschlag:** `Security::getString()` um `maxLength`-Parameter erweitern oder explizit pruefen (`strlen($username) <= 50`).
- **Prioritaet:** Medium

---

## IMPR-005: Dynamische Zurueck-Navigation per `javascript:history.back()`

- **Bereich:** Error-Pages (Registrierung, Login, Profil)
- **URL / Route:** `register.php`, `login.php`, `profile.php`
- **Beobachtung:** `default_error(..., 'javascript:history.back()', ...)` in mehreren Scripts.
- **Problem im Workflow:** Das Feedback bleibt nicht erhalten - Nutzer fuellt Formular erneut komplett aus.
- **Auswirkung:** Sehr unbequem, vor allem bei grossen Registrierungsformularen mit 10 Feldern.
- **Verbesserungsvorschlag:** Formular mit eingetragenen Werten erneut rendern (ausser password-Felder). Standard-Patterns: `name="x" value="<?= escape($old['x'] ?? '') ?>"`.
- **Prioritaet:** Medium

---

## IMPR-006: Fehlende Labels / Accessibility im Registrierungsformular

- **Bereich:** Registrierung
- **URL / Route:** `register.php`
- **Beobachtung:** Accessibility-Tree zeigt unbenannte Textfelder. Labels sind als `<b>`-Texte in `<td>` realisiert, nicht als `<label for>`.
- **Problem im Workflow:** Screenreader koennen Felder nicht eindeutig zuordnen.
- **Auswirkung:** Barrierefreiheit nicht gegeben (WCAG 2.1 AA 1.3.1 / 4.1.2 verletzt).
- **Verbesserungsvorschlag:** `<label for="email1">Email</label>` oder `aria-label` setzen.
- **Prioritaet:** Medium

---

## IMPR-007: Hinweis "Cookies sind aktiviert?" ist veraltet

- **Bereich:** Login
- **URL / Route:** `login.php:161`
- **Beobachtung:** Footer-Text "Please enable cookies" - wird bei modernen Browsern nicht mehr aktiv thematisiert.
- **Problem im Workflow:** Irrelevant und wirkt altbacken.
- **Auswirkung:** Low - professioneller Eindruck leidet.
- **Verbesserungsvorschlag:** Text entfernen oder durch "Benutzt nur notwendige Cookies fuer Session" ersetzen.
- **Prioritaet:** Low

---

## IMPR-008: Passwort-Reset auf Token-Flow umstellen

- **Bereich:** Passwort-Reset
- **URL / Route:** `sendpassword.php`
- **Beobachtung:** Generiert sofort ein neues Passwort in der DB.
- **Problem im Workflow:** Account-Lockout durch Dritte moeglich (siehe BUG-016).
- **Auswirkung:** Critical.
- **Verbesserungsvorschlag:** Token-basierte Reset-Kette: (1) POST sendpassword.php → Token in `ppb_pwreset` speichern, (2) Mail mit Link `resetpassword.php?token=...`, (3) Nutzer klickt Link, vergibt neues Passwort, Token verfaellt.
- **Prioritaet:** High

---

## IMPR-009: Vereinheitlichte Fehlertexte fuer Login / Reset (keine User-Enumeration)

- **Bereich:** Login, Passwort-Reset
- **URL / Route:** `login.php`, `sendpassword.php`
- **Beobachtung:** Unterschiedliche Fehlertexte pro Ursache.
- **Problem im Workflow:** Angreifer koennen Konten sammeln.
- **Auswirkung:** Medium.
- **Verbesserungsvorschlag:** Einheitlicher Text wie "Falls die Email registriert ist, erhaelt sie Anweisungen." / "Email oder Passwort ist falsch."
- **Prioritaet:** High

---

## IMPR-010: Rate-Limiting in zentralem Middleware/Guard implementieren

- **Bereich:** Login, Register, Sendpassword, Newpost, Newthread, Editpost
- **URL / Route:** global
- **Beobachtung:** Kein einziger Endpoint hat Rate-Limits.
- **Problem im Workflow:** Brute-Force, Spam, DoS.
- **Auswirkung:** High.
- **Verbesserungsvorschlag:** IP + Session basierte Zaehler in Redis oder DB-Table (z. B. `ppb_ratelimit`) mit Zeitfenster-Logik; nach Schwellenwert Captcha oder Sleep.
- **Prioritaet:** High

---

## IMPR-011: Profil-Edit ohne Pflicht-Passwort und mit Re-Auth fuer sensible Felder

- **Bereich:** Profil
- **URL / Route:** `profile.php`
- **Beobachtung:** Jedes Profil-Update verlangt Passwort (BUG-011), sensitive Felder ohne Re-Auth (BUG-012).
- **Problem im Workflow:** Umstaendlich bei Standardaenderungen, unsicher bei Email/Passwort.
- **Verbesserungsvorschlag:** (a) Passwort-Felder optional machen (nur wenn geaendert werden soll); (b) Bei Email- oder Passwort-Aenderung aktuelles Passwort zusaetzlich abfragen; (c) Bei Email-Wechsel Confirmation-Mail an alte UND neue Adresse.
- **Prioritaet:** High

---

## IMPR-012: Signaturen und Biographien whitelist-basiert filtern

- **Bereich:** Profil / Thread-Rendering
- **URL / Route:** `profile.php`, `register.php`, `showthread.php`, `showprofile.php`
- **Beobachtung:** Signaturen landen roh in DB. HTML wird in Threads direkt gerendert (BUG-010).
- **Problem im Workflow:** Stored XSS.
- **Verbesserungsvorschlag:** Entweder (a) `$settings['htmlcode']` standardmaessig auf `OFF` setzen, oder (b) HTMLPurifier integrieren mit Whitelist (`<b>`, `<i>`, `<a>`, `<img>`, `<br>` mit Attribut-Whitelist), oder (c) nur BBCode erlauben und HTML immer escapen.
- **Prioritaet:** High

---

## IMPR-013: ICQ-Feld und sonstige Legacy-Kontaktfelder entfernen

- **Bereich:** Profil / Registrierung
- **URL / Route:** `register.php`, `profile.php`, `showprofile.php`
- **Beobachtung:** ICQ-Pager-Feld, `icq@pager.icq.com` Mailto-Link, `http://`-Default-Homepage.
- **Problem im Workflow:** ICQ existiert nicht mehr (Dienst eingestellt 2024). Veraltete Felder verwirren Nutzer und blaehen Formulare auf.
- **Auswirkung:** Low.
- **Verbesserungsvorschlag:** ICQ-Feld entfernen oder durch generisches "Messenger/Profil-URL"-Feld ersetzen; Homepage Default `https://`.
- **Prioritaet:** Low

---

## IMPR-014: Post- und Signature-Laengen sauber begrenzen und melden

- **Bereich:** Posting
- **URL / Route:** `newpost.php`, `newthread.php`, `editpost.php`
- **Beobachtung:** Sehr lange Posts produzieren leere Fehlerseite (BUG-015).
- **Problem im Workflow:** Nutzer bekommt kein Feedback.
- **Verbesserungsvorschlag:** Serverseitige Pruefung `strlen($text) <= $maxLen` mit klarer Meldung. Max-Laenge synchron zu DB-Schema halten.
- **Prioritaet:** Medium

---

## IMPR-015: Logout als POST mit CSRF-Token

- **Bereich:** Logout
- **URL / Route:** `logout.php`
- **Beobachtung:** GET-Logout (BUG-008).
- **Verbesserungsvorschlag:** Bestaetigungsseite mit POST-Form + CSRF; Angriffsoberflaeche minimieren.
- **Prioritaet:** Low

---

## IMPR-016: Unique-Index auf username in ppb_users

- **Bereich:** DB-Schema / Registrierung
- **URL / Route:** `register.php`
- **Beobachtung:** Duplicate usernames moeglich (BUG-003).
- **Verbesserungsvorschlag:** DB-Index `UNIQUE KEY (username)`, serverseitige Pruefung.
- **Prioritaet:** High

---

## IMPR-017: "Remember login"-Option entfernen oder neu definieren

- **Bereich:** Login / Session
- **URL / Route:** `register.php:158`, `profile.php:195`, `login.php:73`
- **Beobachtung:** `logincookie`-Feld ist Legacy-Marker. Session basiert auf PHP-Session, nicht auf Cookie.
- **Problem im Workflow:** Verwirrendes Legacy-Feld, das keine echte Funktion mehr hat. Nutzer glaubt, er wird 360 Tage eingeloggt bleiben.
- **Verbesserungsvorschlag:** Feld entfernen oder echtes "Remember-Me"-Token implementieren (separates Cookie mit Laufzeit).
- **Prioritaet:** Medium

---

## IMPR-018: Navigations-Hinweis bei Erfolgsseiten sinnvoller platzieren

- **Bereich:** Success-Pages (Login, Registrierung, Passwort-Reset, Post erstellt)
- **URL / Route:** diverse
- **Beobachtung:** Erfolgsseiten haben oft nur den Link "Home" oder "Back to X Thread". Keine direkte Aktion.
- **Problem im Workflow:** Nutzer muss an mehreren Stellen klicken, um weiterzumachen.
- **Verbesserungsvorschlag:** Nach Login direkte Weiterleitung zum urspruenglichen Ziel (Return-URL). Nach "Post erstellt" direkter Sprung zum erstellten Post (Anchor).
- **Prioritaet:** Medium

---

## IMPR-019: Accessibility und HTML-Semantik durchgaengig modernisieren

- **Bereich:** Alle Seiten
- **URL / Route:** global
- **Beobachtung:** Tabellen-Layout, fehlende Labels, keine ARIA-Attribute, `<b>` statt `<strong>`, inline bgcolor="" in vielen Elementen.
- **Problem im Workflow:** Mangelhafte Barrierefreiheit, schlechte Maintainability, CSS-Modernisierung nicht moeglich.
- **Verbesserungsvorschlag:** Schrittweise auf semantisches HTML5 umsteigen (`<header>`, `<nav>`, `<main>`, `<article>`), Styling ins CSS auslagern.
- **Prioritaet:** Medium

---

## IMPR-020: Passwort-Felder nicht als required im Profilformular rendern

- **Bereich:** Profil
- **URL / Route:** `profile.php:245-253`
- **Beobachtung:** Passwortfelder sind HTML-`required` und auch serverseitig Pflicht.
- **Problem im Workflow:** Siehe BUG-011, direkter HTML-Hinweis verstaerkt den Eindruck.
- **Verbesserungsvorschlag:** Attribute entfernen und serverseitige Logik anpassen, sodass leere Passwortfelder einfach "kein neues Passwort" bedeuten.
- **Prioritaet:** High

---

