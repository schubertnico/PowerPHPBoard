# Installationsanleitung

Diese Anleitung beschreibt die vollstaendige Installation von **PowerPHPBoard 2.1.0**.
Fuer eine Kurzfassung siehe [README.md](README.md) Abschnitt "Schnellstart".

---

## Inhaltsverzeichnis

1. [Systemanforderungen](#systemanforderungen)
2. [Schnellinstallation](#schnellinstallation)
3. [Docker Installation](#docker-installation)
4. [Manuelle Installation auf Live-Server](#manuelle-installation-auf-live-server)
5. [Webserver-Konfiguration](#webserver-konfiguration)
6. [Datenbank einrichten](#datenbank-einrichten)
7. [Konfiguration](#konfiguration)
8. [E-Mail-Versand (SMTP)](#e-mail-versand-smtp)
9. [Erste Schritte](#erste-schritte)
10. [Upgrade](#upgrade)
11. [Sicherheits-Checkliste](#sicherheits-checkliste)
12. [Fehlerbehebung](#fehlerbehebung)

---

## Systemanforderungen

### Minimum

| Komponente | Version |
|-----------|---------|
| PHP       | 8.4+    |
| MySQL     | 8.0+ oder MariaDB 10.5+ |
| Speicher  | 128 MB RAM |
| Festplatte | 100 MB + Datenbank |

### Empfohlen

| Komponente | Version |
|-----------|---------|
| PHP       | 8.4 neueste Minor-Version |
| MySQL     | 8.0+ oder MariaDB 10.6+ |
| Speicher  | 256 MB RAM |
| Festplatte | 500 MB + Datenbank |
| Webserver | Apache 2.4 mit mod_rewrite, mod_headers, mod_expires, mod_deflate |

### Erforderliche PHP-Erweiterungen

```text
pdo           Datenbank-Abstraktion
pdo_mysql     MySQL-Treiber
mbstring      Multibyte-Strings (UTF-8)
json          JSON (Standard in PHP 8+)
openssl       Fuer password_hash + CSRF
session       Session-Verwaltung (Standard)
filter        Input-Validierung (Standard)
```

### Optional

```text
opcache       Performance-Optimierung (dringend empfohlen fuer Produktion)
gd            Spaetere Avatar-Verarbeitung
curl          HTTP-Anfragen (z. B. Webhooks)
zip           Zip-Archive
intl          Internationalisierung
```

### Empfohlene PHP-Einstellungen (`php.ini`)

```ini
; Ausfuehrung
memory_limit = 128M
max_execution_time = 30
post_max_size = 12M
upload_max_filesize = 10M

; Sicherheit
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
display_errors = Off           ; Off in Produktion, On nur in Dev
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/php-error.log

; Sessions
session.cookie_httponly = 1
session.cookie_secure = 1      ; nur bei HTTPS!
session.use_strict_mode = 1
session.cookie_samesite = Strict

; Zeitzone
date.timezone = Europe/Berlin

; Encoding
default_charset = "UTF-8"
```

---

## Schnellinstallation

Fuer erfahrene Nutzer mit Docker:

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard
composer install
cd .docker && docker compose up -d --build && cd ..
# http://localhost:8085
```

Fuer Produktion ohne Docker siehe [Manuelle Installation auf Live-Server](#manuelle-installation-auf-live-server).

---

## Docker Installation

### Voraussetzungen

- Docker 20.10+
- Docker Compose v2 (`docker compose ...`, nicht mehr `docker-compose`)
- Composer 2.0+ (lokal, fuer Dev-Dependencies)
- Git

### Schritte

**1. Repository klonen:**

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard
```

**2. Dev-Dependencies installieren (fuer Tests, Analyse):**

```bash
composer install
```

**3. Container starten:**

```bash
cd .docker
docker compose up -d --build
```

**4. Container-Status pruefen:**

```bash
docker compose ps
```

Erwartete Container:

```text
powerphpboard_web         Up (healthy)   0.0.0.0:8085->80/tcp
powerphpboard_db          Up (healthy)   0.0.0.0:3315->3306/tcp
powerphpboard_mailpit     Up             0.0.0.0:1032->1025/tcp, 0.0.0.0:8032->8025/tcp
powerphpboard_phpmyadmin  Up             0.0.0.0:8088->80/tcp
```

### Verfuegbare Services

| Service     | URL                            | Zweck                         |
|-------------|--------------------------------|-------------------------------|
| Forum       | http://localhost:8085          | Hauptanwendung                |
| phpMyAdmin  | http://localhost:8088          | DB-Verwaltung                 |
| Mailpit UI  | http://localhost:8032          | E-Mails testen                |
| SMTP intern | `mailpit:1025` (Docker-Netz)   | SMTP-Ziel der App             |
| MySQL       | `localhost:3315`               | Direkter DB-Zugriff (Dev)     |

### Nuetzliche Docker-Befehle

```bash
cd .docker

# starten / stoppen
docker compose up -d
docker compose down

# Logs verfolgen
docker compose logs -f web

# In Container einloggen
docker compose exec web bash

# MySQL-Konsole
docker compose exec db mysql -u powerphpboard -ppowerphpboard_secret powerphpboard

# Neu bauen nach Aenderungen an Dockerfile/php.ini
docker compose up -d --build

# Komplett zuruecksetzen (loescht auch alle Daten!)
docker compose down -v
```

Beim ersten Start wird `install.sql` automatisch in die DB geladen und ein Admin-Account
angelegt (siehe [Erste Schritte](#erste-schritte)).

---

## Manuelle Installation auf Live-Server

### 1. Dateien bereitstellen

**Option A: Sauberes Deploy-Paket via `git archive`** (empfohlen, schliesst Dev-Artefakte aus):

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git /tmp/ppb-src
cd /tmp/ppb-src
git archive --format=tar.gz --prefix=powerphpboard/ HEAD > /tmp/deploy.tar.gz

# Auf dem Live-Server entpacken
tar -xzf /tmp/deploy.tar.gz -C /var/www
mv /var/www/powerphpboard /var/www/forum   # oder Ziel nach Wahl
```

Das Archiv enthaelt **nur** Live-relevante Dateien - Tests, Docker-Configs, Docs und
Analyse-Configs sind via `.gitattributes export-ignore` ausgeschlossen.

**Option B: Git-Clone + manuelles Aufraeumen:**

```bash
cd /var/www
git clone https://github.com/schubertnico/PowerPHPBoard.git forum
cd forum
rm -rf tests docs todos .docker .github phpunit.xml phpstan.neon psalm.xml \
       phpmd.xml rector.php infection.json5 .php-cs-fixer.php \
       create-admin.php install_bugfix_*.sql
```

### 2. Produktions-Dependencies installieren

```bash
cd /var/www/forum
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

Ergebnis: `vendor/`-Verzeichnis nur mit Produktions-Paketen.

### 3. Berechtigungen

```bash
# Ownership fuer Apache/Nginx (Debian/Ubuntu: www-data)
chown -R www-data:www-data /var/www/forum

# Verzeichnisse: 750, Dateien: 640
find /var/www/forum -type d -exec chmod 750 {} \;
find /var/www/forum -type f -exec chmod 640 {} \;

# Log-Verzeichnis muss beschreibbar sein
chmod 770 /var/www/forum/logs

# Nur Webserver darf config.inc.php lesen
chmod 640 /var/www/forum/config.inc.php
```

### 4. `.htaccess` nicht entfernen

Das Repository enthaelt **sieben** `.htaccess`-Dateien (Root, includes/, inc/, logs/,
docs/, todos/, tests/). Die Root-`.htaccess` ist essenziell fuer:

- Blockieren sensibler Dateien (config.inc.php, *.sql, includes/, logs/ ...)
- Security-Header (X-Frame-Options, Referrer-Policy, ...)
- Directory-Listing aus

Achte darauf, dass dein FTP/Deploy-Tool versteckte Dateien (dotfiles) uebertraegt!

---

## Webserver-Konfiguration

### Apache (empfohlen)

**Virtual Host:**

```apache
<VirtualHost *:80>
    ServerName forum.example.com
    DocumentRoot /var/www/forum

    <Directory /var/www/forum>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/forum_error.log
    CustomLog ${APACHE_LOG_DIR}/forum_access.log combined

    # Server-Version nicht preisgeben
    ServerSignature Off
</VirtualHost>
```

**Wichtig:** `AllowOverride All` ist Pflicht, sonst werden die mitgelieferten
`.htaccess`-Dateien ignoriert und der Schutz greift nicht!

**Module aktivieren:**

```bash
a2enmod rewrite headers expires deflate
systemctl restart apache2
```

**Empfohlen:** `ServerTokens Prod` in `/etc/apache2/conf-enabled/security.conf`.

### Nginx

Nginx respektiert keine `.htaccess`-Dateien. Die aequivalenten Regeln muessen
direkt in die `server {}`-Sektion:

```nginx
server {
    listen 80;
    server_name forum.example.com;
    root /var/www/forum;
    index index.php;

    access_log /var/log/nginx/forum_access.log;
    error_log  /var/log/nginx/forum_error.log;

    charset utf-8;

    # Directory-Listing aus
    autoindex off;

    # Security-Header
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=()" always;
    add_header X-Permitted-Cross-Domain-Policies "none" always;

    # Dotfiles komplett blockieren
    location ~ /\. { deny all; return 404; }

    # Sensible Verzeichnisse
    location ~ ^/(includes|inc|tests|docs|todos|logs|vendor|node_modules)/ {
        deny all; return 404;
    }

    # Sensible Dateien (Root-Ebene)
    location ~ ^/(config\.inc\.php|config\.inc\.local\.php|header\.inc\.php|footer\.inc\.php|functions\.inc\.php|english\.inc\.php|deutsch-(du|sie)\.inc\.php|install\.sql|install_bugfix.*\.sql|create-admin\.php|composer\.(json|lock)|phpstan\.neon|psalm\.xml|phpmd\.xml|phpunit\.xml|rector\.php|infection\.json5|\.php-cs-fixer\.php|README\.(md|html)|CONTRIBUTING\.md|SECURITY\.md|INSTALLATION\.md|LICENSE|Dockerfile|docker-compose\.yml)$ {
        deny all; return 404;
    }

    # Backup-/Editor-Endungen
    location ~ \.(bak|backup|orig|tmp|swp|old|save|log|sql|md|neon|yml|yaml|ini|dist|phar|conf)$ {
        deny all; return 404;
    }

    # Statische Assets cachen
    location ~* \.(css|js|gif|png|jpg|jpeg|ico|svg|woff2?)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # PHP-Handling (PHP-FPM)
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60;
    }

    # Max. Upload-Groesse
    client_max_body_size 12m;
}
```

### HTTPS via Let's Encrypt

```bash
# Debian/Ubuntu
apt install certbot python3-certbot-apache    # oder -nginx
certbot --apache -d forum.example.com         # oder --nginx
certbot renew --dry-run
```

Nach HTTPS-Umstellung: In `.htaccess` den HTTPS-Redirect-Block aktivieren
(auskommentierter Abschnitt "HTTPS erzwingen") und `session.cookie_secure = On`
in `php.ini` setzen.

---

## Datenbank einrichten

### 1. Datenbank + Nutzer anlegen

```sql
CREATE DATABASE PowerPHPBoard_v2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'ppb_user'@'localhost' IDENTIFIED BY 'BITTE_SICHERES_PASSWORT';
GRANT ALL PRIVILEGES ON PowerPHPBoard_v2.* TO 'ppb_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Schema importieren

```bash
mysql -u ppb_user -p PowerPHPBoard_v2 < install.sql
```

Damit sind alle Tabellen inklusive Rate-Limit- und Password-Reset-Tokens angelegt.

### 3. Schema-Uebersicht

| Tabelle                | Beschreibung                                       |
|------------------------|----------------------------------------------------|
| `ppb_users`            | Benutzerkonten (username UNIQUE, status enum)      |
| `ppb_boards`           | Foren, Kategorien, Moderatoren, Board-Passwort     |
| `ppb_posts`            | Threads und Beitraege (Spalte `type`)              |
| `ppb_config`           | Board-Konfiguration (Title, Farben, Sprache, ...)  |
| `ppb_visits`           | Session-/Private-Board-Besuchsdaten                |
| `ppb_password_resets`  | Einmal-Tokens fuer Passwort-Reset (SHA256 gehasht) |
| `ppb_rate_limits`      | Brute-Force-Zaehler fuer Login und Reset           |

**Wichtige Constraints:**
- `ppb_users.username` ist `UNIQUE` (seit 2.1.0).
- `ppb_rate_limits(action, identifier)` ist `UNIQUE` (Upsert-Logik).

### 4. Admin-Konto

`install.sql` legt automatisch den Admin-User **"Gott"** mit Legacy-Passwort-Hash an.
Dieser wird beim ersten Login automatisch auf Argon2id migriert.

**Sofort nach Installation:**

1. Mit "Gott" + Initial-Passwort einloggen.
2. Profil oeffnen, Passwort **und** E-Mail aendern.
3. Alternativ einen eigenen Admin anlegen und "Gott" ueber phpMyAdmin loeschen.

---

## Konfiguration

### `config.inc.php`

Die `config.inc.php` ist im Repository bereits vorhanden und wird via Environment-
Variablen parametriert. Struktur:

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

Zur Konfiguration stehen zwei Wege offen:

#### Variante A: Environment-Variablen im Webserver

**Apache (`.htaccess` oder VirtualHost):**
```apache
SetEnv PPB_DB_HOST db.internal
SetEnv PPB_DB_USER forum_user
SetEnv PPB_DB_PASS "GEHEIMES_PASSWORT"
SetEnv PPB_DB_NAME forum_prod
SetEnv PPB_MAIL_HOST smtp.example.com
SetEnv PPB_MAIL_PORT 587
SetEnv PPB_MAIL_FROM noreply@example.com
```

**Nginx (Umgebung ueber PHP-FPM-Pool-Config):**

In `/etc/php/8.4/fpm/pool.d/forum.conf`:
```ini
env[PPB_DB_HOST]  = db.internal
env[PPB_DB_USER]  = forum_user
env[PPB_DB_PASS]  = GEHEIMES_PASSWORT
env[PPB_DB_NAME]  = forum_prod
env[PPB_MAIL_HOST] = smtp.example.com
env[PPB_MAIL_PORT] = 587
env[PPB_MAIL_FROM] = noreply@example.com
```

#### Variante B: `config.local.inc.php` nebenbei (nicht versioniert)

Lege eine `config.local.inc.php` im Projekt-Root an, die NICHT committed wird
(siehe `.gitignore`):

```php
<?php
putenv('PPB_DB_HOST=db.internal');
putenv('PPB_DB_USER=forum_user');
putenv('PPB_DB_PASS=GEHEIMES_PASSWORT');
putenv('PPB_DB_NAME=forum_prod');
```

Und binde sie am Anfang der `config.inc.php` ein:

```php
if (file_exists(__DIR__ . '/config.local.inc.php')) {
    require_once __DIR__ . '/config.local.inc.php';
}
```

### Board-Einstellungen

Nach der Installation im Admin-Panel unter "General Settings" setzen:

- **boardtitle**: Name des Forums
- **boardurl**: Vollstaendige URL (fuer Links in E-Mails)
- **adminemail**: Absender-Adresse (Fallback: `$mail['from']`)
- **language**: `English`, `Deutsch-Sie` oder `Deutsch-Du`
- **htmlcode**: `ON` oder `OFF` (Achtung: auf `OFF` ist sicherer)
- **bbcode**, **smilies**: `ON`/`OFF`

---

## E-Mail-Versand (SMTP)

PowerPHPBoard sendet E-Mails bei:

- Registrierung (Willkommens-Mail)
- Passwort-Reset (Token-Link)
- User-zu-User (Sendmail-Formular)

Die Versand-Klasse ist `PowerPHPBoard\Mailer` - ein minimalistischer SMTP-Client
(kein PHP `mail()`!). Entsprechend muessen SMTP-Zugangsdaten konfiguriert sein.

### Dev: Mailpit (Docker-Setup)

Bereits vorkonfiguriert in `docker-compose.yml`. Mails landen in der Mailpit-UI
auf http://localhost:8032 und werden nicht wirklich versendet.

### Produktion: echter SMTP-Relay

Beispiel: SMTP ohne Auth (interner Relay):
```text
PPB_MAIL_HOST = smtp.example.com
PPB_MAIL_PORT = 25
PPB_MAIL_FROM = noreply@example.com
```

Der mitgelieferte `Mailer` unterstuetzt **Plain-SMTP** ohne TLS/AUTH. Bei
Relay-Systemen mit IP-Whitelist reicht das. Fuer oeffentliche SMTP-Server
(z. B. SendGrid, Mailgun, Amazon SES) musst du entweder:

1. einen lokalen Relay (Postfix als Smart-Host) vorschalten, oder
2. den `Mailer` um STARTTLS+AUTH erweitern.

### Kein SMTP verfuegbar?

Wenn Mailversand nicht konfiguriert ist, schlaegt der `Mailer` intern stumm fehl
(Log-Eintrag). Registrierung und Passwort-Reset funktionieren weiterhin, aber
der Nutzer bekommt keine Mail.

---

## Erste Schritte

### 1. Installation pruefen

Browser oeffnen:
```text
http://localhost:8085/          (Docker)
http://forum.example.com/       (Produktion)
```

Du solltest die leere Boardlist mit dem Standard-Theme sehen.

### 2. Als Admin einloggen

Der initial angelegte Admin heisst **"Gott"**. Login-Email und Initial-Passwort
findest du in `install.sql` (Zeile mit `INSERT INTO ppb_users`). **Passwort sofort
aendern!**

### 3. Erstes Board erstellen

1. Als Admin eingeloggt auf `/admin/` gehen.
2. "Board hinzufuegen" auswaehlen.
3. Kategorie, Name, Beschreibung eingeben.
4. Speichern.

### 4. Konfiguration pruefen

Im Admin unter "General Settings":

- `boardurl` auf die tatsaechliche URL setzen (wichtig fuer Passwort-Reset-Links!)
- `adminemail` auf eine existierende Adresse setzen.
- `language` auf bevorzugte Sprache setzen.

### 5. Testregistrierung

1. Ausloggen.
2. Auf "Register" klicken, Boardregeln akzeptieren.
3. Formular ausfuellen, abschicken.
4. Mailpit (Docker) bzw. echten Posteingang pruefen - Willkommensmail muss eintreffen.
5. Login testen.

---

## Upgrade

### Von v2.0.x nach 2.1.0 (Bugfix-Release)

**Backup erstellen:**

```bash
# Datenbank
mysqldump -u ppb_user -p PowerPHPBoard_v2 > backup_$(date +%Y%m%d).sql

# Dateien
tar -czf backup_files_$(date +%Y%m%d).tar.gz /var/www/forum
```

**Dateien aktualisieren:**

```bash
cd /var/www/forum
git pull origin main
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

**DB-Migration einspielen:**

```bash
mysql -u ppb_user -p PowerPHPBoard_v2 < install_bugfix_2026-04-23.sql
```

Die Migration legt an:

- `UNIQUE INDEX idx_users_username_unique` auf `ppb_users(username)`
  - Falls **doppelte Usernames** existieren: vor der Migration bereinigen
    (siehe Kommentar in `install_bugfix_2026-04-23.sql`)
- `ppb_password_resets` (Tokens fuer Reset-Flow)
- `ppb_rate_limits` (Brute-Force-Zaehler)

**Nach dem Upgrade:**

- Alle aktiven Sessions bleiben gueltig.
- Nutzer mit alten Base64-Passwoertern werden beim ersten Login automatisch auf Argon2id migriert.
- Mindestpasswortlaenge ist jetzt 8 Zeichen (vorher 6) - bestehende Passwoerter sind nicht betroffen.

### Von v1.x auf 2.1.0 (grosser Sprung)

**Wichtig:** Zwischen v1 und v2 wurde das DB-Schema teilweise umgebaut.
Empfohlen: Daten exportieren, Version 2 **frisch** installieren, dann Daten
selektiv reimportieren. Die alte `upgrade_v1_to_v2.sql` ist **nicht Bestandteil**
des 2.1.0-Releases - kontaktiere bei Bedarf [Support](#support).

---

## Sicherheits-Checkliste

Vor dem Go-Live abhaken:

- [ ] `PPB_DEBUG=false` bzw. nicht gesetzt
- [ ] `display_errors = Off` in `php.ini`
- [ ] `expose_php = Off`
- [ ] HTTPS aktiv, HTTP redirectet 301 zu HTTPS
- [ ] `session.cookie_secure = On` (nur bei HTTPS)
- [ ] `ServerTokens Prod`, `ServerSignature Off`
- [ ] `AllowOverride All` in Apache, damit `.htaccess` greift
- [ ] `.htaccess`-Dateien vollstaendig uebertragen (Root, includes/, inc/, logs/, docs/, todos/, tests/)
- [ ] Test: `curl -I https://forum.example.com/config.inc.php` → HTTP 403
- [ ] Test: `curl -I https://forum.example.com/includes/Security.php` → HTTP 403
- [ ] Test: `curl -I https://forum.example.com/.git/HEAD` → HTTP 403
- [ ] Test: `curl -I https://forum.example.com/install.sql` → HTTP 403
- [ ] Test: Security-Header via `curl -I https://.../` sichtbar (X-Frame-Options, X-Content-Type-Options, Referrer-Policy)
- [ ] Admin-Passwort des Initial-Accounts **geaendert**
- [ ] Admin-Email auf existierende Adresse gesetzt
- [ ] SMTP getestet (Passwort-Reset-Mail kommt an)
- [ ] Rate-Limit getestet (10x falsches Login loest Lock aus)
- [ ] DB-Backup-Strategie eingerichtet (mindestens taeglich)
- [ ] `create-admin.php` und `install_bugfix_*.sql` **geloescht** oder unerreichbar
- [ ] `logs/`-Verzeichnis ist ausserhalb des DocumentRoot oder per `.htaccess` gesperrt
- [ ] Composer installiert nur Prod-Dependencies (`--no-dev`)
- [ ] `vendor/`, `tests/`, `docs/`, `.docker/`, `.github/` sind per `.htaccess` und/oder Webserver-Config gesperrt

---

## Fehlerbehebung

### "Class not found" Fehler

```bash
composer dump-autoload
# bzw.
composer install --no-dev --optimize-autoloader
```

Falls nur eine der neuen Klassen fehlt (`Validator`, `RateLimiter`, `Mailer`):
`config.inc.php` muss sie laden - pruefen, ob die `require_once`-Zeilen im
oberen Abschnitt stehen.

### `.htaccess` wirkt nicht

Apache: `AllowOverride None` ist der Default! Setze in der VHost-Config:

```apache
<Directory /var/www/forum>
    AllowOverride All
</Directory>
```

und lade Apache neu. Ohne `AllowOverride All` sind alle `.htaccess`-Schutzregeln
wirkungslos und sensible Dateien **waeren oeffentlich erreichbar**.

Nginx: `.htaccess` wird generell nicht unterstuetzt. Aequivalente Regeln muessen
in `server { ... }` (siehe [Webserver-Konfiguration](#webserver-konfiguration)).

### "Permission denied" bei Logs

```bash
chmod 770 /var/www/forum/logs
chown www-data:www-data /var/www/forum/logs
ls -la /var/www/forum/logs
```

### "Connection refused" zur Datenbank

1. MySQL-Service laeuft?
   ```bash
   systemctl status mysql
   ```
2. `PPB_DB_HOST`/`-USER`/`-PASS`/`-NAME` korrekt gesetzt?
3. Rechte pruefen:
   ```sql
   SHOW GRANTS FOR 'ppb_user'@'localhost';
   ```

### Weisse Seite / HTTP 500

1. PHP-Error-Log pruefen:
   ```bash
   tail -f /var/www/forum/logs/php-error.log
   tail -f /var/log/apache2/error.log
   ```
2. Temporaer `PPB_DEBUG=true` setzen, neu laden, Ausgabe analysieren, danach
   zurueck auf `false`.

### Rate-Limit schiesst zu frueh / Accounts werden gesperrt

```sql
-- Manuell Zaehler fuer eine IP zuruecksetzen
DELETE FROM ppb_rate_limits WHERE identifier = '1.2.3.4';

-- Alle Zaehler loeschen
TRUNCATE TABLE ppb_rate_limits;
```

Limits anpassen direkt im Code (`login.php`, `sendpassword.php`) via
`RateLimiter(new DatabaseRateLimitStorage($db), maxAttempts: ..., windowSeconds: ..., lockSeconds: ...)`.

### Passwort-Reset-Mail kommt nicht an

1. Mailpit (Dev) oder SMTP-Log (Prod) pruefen - ist die Verbindung zum SMTP-Host hergestellt?
2. `logs/php-error.log` nach `[Mailer]`-Zeilen durchsuchen.
3. `boardurl` in `ppb_config` muss gesetzt sein, sonst wird eine lokale URL aus
   `$_SERVER['HTTP_HOST']` gebaut (funktioniert hinter Reverse-Proxies evtl. nicht).
4. Token-Gueltigkeit ist 1 Stunde - danach Status "Invalid or expired".

### Session-Probleme

1. Cookie im Browser vorhanden? (`PHPSESSID` oder konfigurierter Name)
2. `session.save_path` beschreibbar?
   ```bash
   php -r "echo session_save_path() . PHP_EOL;"
   ```
3. Bei HTTPS und `session.cookie_secure=On` werden Cookies ueber HTTP nicht gesendet.

### Diagnose-Tools

**DB-Verbindung testen** (temporaer!):

```bash
docker compose exec web php -r "
require '/var/www/html/config.inc.php';
try {
    \$pdo = new PDO(
        'mysql:host=' . \$mysql['server'] . ';dbname=' . \$mysql['database'],
        \$mysql['user'], \$mysql['password']
    );
    echo 'DB OK' . PHP_EOL;
} catch (PDOException \$e) {
    echo 'DB FAIL: ' . \$e->getMessage() . PHP_EOL;
}
"
```

**SMTP testen**:

```bash
docker compose exec web php -r "
require '/var/www/html/includes/Security.php';
require '/var/www/html/includes/Mailer.php';
\$m = new PowerPHPBoard\Mailer('mailpit', 1025);
var_dump(\$m->send('test@example.com', 'noreply@local.test', 'Test', 'Hallo'));
"
```

**Erweiterungen pruefen**:

```bash
php -m | grep -iE "pdo_mysql|mbstring|openssl|session|filter"
```

---

## Support

- **GitHub Issues:** [https://github.com/schubertnico/PowerPHPBoard/issues](https://github.com/schubertnico/PowerPHPBoard/issues)
- **Projekt-Website:** [https://www.powerscripts.org](https://www.powerscripts.org)
- **E-Mail:** info@schubertmedia.de
- **README:** [README.md](README.md) fuer Schnellstart und Architekturueberblick
- **Security-Policy:** [SECURITY.md](SECURITY.md)

---

**Stand:** 2026-04-24
**Version:** 2.1.0
