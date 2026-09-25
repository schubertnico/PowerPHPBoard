# Installationsanleitung

Diese Anleitung beschreibt die vollständige Installation von **PowerPHPBoard 2.3.0**
(mit Bootstrap-5-Frontend).
Für eine Kurzfassung siehe [README.md](README.md) Abschnitt "Schnellstart".

---

## Inhaltsverzeichnis

1. [Systemanforderungen](#systemanforderungen)
2. [Schnellinstallation](#schnellinstallation)
3. [Installation mit dem Web-Installer](#installation-mit-dem-web-installer)
4. [Docker Installation](#docker-installation)
5. [Manuelle Installation auf Live-Server](#manuelle-installation-auf-live-server)
6. [Webserver-Konfiguration](#webserver-konfiguration)
7. [Datenbank einrichten](#datenbank-einrichten)
8. [Konfiguration](#konfiguration)
9. [E-Mail-Versand (SMTP)](#e-mail-versand-smtp)
10. [Erste Schritte](#erste-schritte)
11. [Upgrade](#upgrade)
12. [Sicherheits-Checkliste](#sicherheits-checkliste)
13. [Fehlerbehebung](#fehlerbehebung)

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
openssl       Verschlüsselter Mailversand (STARTTLS, SSL/TLS)
session       Session-Verwaltung (Standard)
filter        Input-Validierung (Standard)
```

### Optional

```text
opcache       Performance-Optimierung (dringend empfohlen für Produktion)
gd            Spätere Avatar-Verarbeitung
curl          HTTP-Anfragen (z. B. Webhooks)
zip           Zip-Archive
intl          Internationalisierung
```

### Empfohlene PHP-Einstellungen (`php.ini`)

```ini
; Ausführung
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

Für erfahrene Nutzer mit Docker:

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard
composer install
cd .docker && docker compose up -d --build && cd ..
# http://localhost:8085
```

Für Produktion ohne Docker siehe [Manuelle Installation auf Live-Server](#manuelle-installation-auf-live-server).

Für Shared Hosting und alle Server ohne Docker: Dateien hochladen, leere Datenbank
anlegen, `/install/` im Browser aufrufen – siehe
[Installation mit dem Web-Installer](#installation-mit-dem-web-installer).

---

## Installation mit dem Web-Installer

Der empfohlene Weg für jeden Server ohne Docker – gerade auch für Shared Hosting
ohne Shell-Zugang. Der Installer legt die Tabellen aus `install.sql` an, erstellt
den ersten Administrator und schreibt die Zugangsdaten in `config.local.php`.
Er ist seit Version 2.3.0 enthalten und ersetzt den früheren Standard-Administrator
„Gott“ mit öffentlich bekanntem Passwort.

### Voraussetzungen

- PHP 8.4 oder neuer mit den Erweiterungen `pdo_mysql` und `mbstring`; für
  verschlüsselten Mailversand zusätzlich `openssl` (bei fast allen Hostern aktiv)
- MySQL 8.0+ oder MariaDB 10.5+ mit einer **leeren** Datenbank
- Zugangsdaten eines E-Mail-Postfachs für den Versand (Postausgangsserver,
  Benutzername, Passwort) – stehen im Kundenmenü des Hosters
- Schreibrechte für `logs/`; damit der Installer `config.local.php` selbst anlegen
  kann, auch für das Forumverzeichnis (sonst laden Sie die Datei von Hand hoch)
- Empfohlen: HTTPS, damit die Zugangsdaten verschlüsselt übertragen werden

### Schritt für Schritt (Shared Hosting)

1. **Dateien hochladen.** Den Inhalt des Release-Archivs per FTP/SFTP in das
   Zielverzeichnis kopieren, z. B. `/forum/`. Auch die versteckten
   `.htaccess`-Dateien übertragen (im FTP-Programm „versteckte Dateien anzeigen“
   einschalten) – sie schützen `config.local.php`, `includes/`, `logs/` und Co.
2. **Rechte setzen.** `logs/` muss für PHP beschreibbar sein (je nach Hoster
   `755`, `775` oder `777`). Ist auch das Forumverzeichnis beschreibbar, legt der
   Installer `config.local.php` selbst an.
3. **Datenbank anlegen.** Im Kundenmenü des Hosters eine neue MySQL-/MariaDB-Datenbank
   erstellen (Zeichensatz `utf8mb4`, falls wählbar). Server, Port, Datenbankname,
   Benutzer und Passwort notieren.
4. **Installer aufrufen.** `https://ihre-domain.de/forum/install/` öffnen. Wer die
   Startseite des noch nicht eingerichteten Forums aufruft, landet automatisch dort.
5. **Assistent durchlaufen:**

   | Schritt | Inhalt |
   |---------|--------|
   | 1 Systemprüfung | PHP-Version, Erweiterungen, Schreibrechte, Hinweis auf HTTPS. Rote Punkte müssen behoben werden, gelbe sind Hinweise. |
   | 2 Datenbank | Server (meist `localhost`), Port (`3306`), Name der Datenbank, Benutzer, Passwort. Die Verbindung wird sofort geprüft. Enthält die Datenbank schon `ppb_`-Tabellen, bricht der Installer ab, statt etwas zu überschreiben. |
   | 3 Forum | Name, Adresse (URL, aus der aktuellen Anfrage vorbelegt – bitte prüfen, sie steht in allen Links der Forum-Mails), E-Mail-Adresse, Sprache (English, Deutsch Sie-Form, Deutsch Du-Form). HTML in Beiträgen bleibt aus Sicherheitsgründen ausgeschaltet. Dazu optional der E-Mail-Versand: SMTP-Server, Port, Verschlüsselung (Keine, STARTTLS, SSL/TLS), Benutzername und Passwort des Postfachs sowie Absender – Details unter [E-Mail-Versand (SMTP)](#e-mail-versand-smtp). „Test-Mail senden“ prüft die Angaben sofort. |
   | 4 Administrator | Benutzername (2–50 Zeichen: Buchstaben, Ziffern sowie `. _ -`), E-Mail-Adresse, Passwort (mindestens 8 Zeichen) mit Wiederholung – dieselben Regeln wie bei der Registrierung. |
   | 5 Abschluss | Zusammenfassung mit „Ändern“-Links. „Jetzt installieren“ legt Tabellen und Administrator an, schreibt `config.local.php` und sperrt den Installer. |

6. **`config.local.php` prüfen.** Hat der Installer die Datei geschrieben, versucht er,
   die Rechte auf `640` zu setzen – im FTP-Programm kontrollieren. Konnte er sie nicht
   schreiben, bietet die Abschlussseite „config.local.php herunterladen“ an (der Inhalt
   steht zusätzlich zum Kopieren darunter). Die Datei per FTP in das Forumverzeichnis
   neben `config.inc.php` legen, Rechte `640` setzen und die Abschlussseite neu laden.
7. **`install/` löschen.** Der Installer ist nach dem Abschluss gesperrt, gehört aber
   nicht auf ein laufendes Forum.
8. **Anmelden** mit E-Mail-Adresse und Passwort des neuen Administrators, unter
   `/admin/` das erste Board anlegen (siehe [Erste Schritte](#erste-schritte)).

### Sperre

Der Installer verweigert jeden Aufruf mit HTTP 403 und dem Hinweis, `install/` zu
löschen, sobald eine dieser Bedingungen gilt:

- `install/.installed` existiert (schreibt der Installer am Ende),
- `config.local.php` existiert,
- die konfigurierte Datenbank enthält bereits eine Zeile in `ppb_config`,
- es ist eine Datenbank per Umgebungsvariablen oder angepasster `config.inc.php`
  eingerichtet, die gerade nicht erreichbar ist – ein Datenbankausfall öffnet den
  Installer also nicht wieder.

Die Startseite leitet nur dann auf `install/` weiter, wenn der Installer vorhanden
und nicht gesperrt ist; sonst verhält sie sich wie bisher (bei einem Ausfall der
Datenbank erscheint die Meldung „Datenbankfehler“).

**Wirklich neu installieren:** Datensicherung anlegen, `config.local.php` und
`install/.installed` löschen, eine leere Datenbank verwenden (oder die `ppb_`-Tabellen
selbst entfernen) und `install/` erneut hochladen.

### Sicherheitshinweise

- Solange das Forum nicht eingerichtet ist, kann jeder den Installer aufrufen, der
  die Adresse kennt. Deshalb: hochladen, installieren, `install/` löschen – am besten
  in einem Zug.
- Nach Möglichkeit über HTTPS installieren.
- Zugangsdaten stehen nur in `config.local.php`. Der Installer schreibt sie weder in
  Logs noch in Fehlermeldungen (im Security-Log stehen nur Fehlercodes); das
  Administrator-Passwort liegt auch in der Session nur als Argon2id-Hash vor.

### Ohne Web-Installer (Kommandozeile)

```bash
mysql -u ppb_user -p forum_db < install.sql
# config.local.php von Hand anlegen (Vorlage unter „Konfiguration“)
php bin/create-admin.php --user=Admin --email=admin@example.com
# danach im Adminbereich unter „Allgemein“ Board-URL und E-Mail-Adresse eintragen
```

---

## Docker Installation

### Voraussetzungen

- Docker 20.10+
- Docker Compose v2 (`docker compose ...`, nicht mehr `docker-compose`)
- Composer 2.0+ (lokal, für Dev-Dependencies)
- Git

### Schritte

**1. Repository klonen:**

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard
```

**2. Dev-Dependencies installieren (für Tests, Analyse):**

```bash
composer install
```

**3. Container starten:**

```bash
cd .docker
docker compose up -d --build
```

**4. Container-Status prüfen:**

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

### Verfügbare Services

| Service     | URL                            | Zweck                         |
|-------------|--------------------------------|-------------------------------|
| Forum       | http://localhost:8085          | Hauptanwendung                |
| phpMyAdmin  | http://localhost:8088          | DB-Verwaltung                 |
| Mailpit UI  | http://localhost:8032          | E-Mails testen                |
| SMTP intern | `mailpit:1025` (Docker-Netz)   | SMTP-Ziel der App             |
| MySQL       | `localhost:3315`               | Direkter DB-Zugriff (Dev)     |

### Nützliche Docker-Befehle

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

# Neu bauen nach Änderungen an Dockerfile/php.ini
docker compose up -d --build

# Komplett zurücksetzen (löscht auch alle Daten!)
docker compose down -v
```

Beim ersten Start (leeres Datenbank-Volume) lädt MySQL zuerst `install.sql` und danach
`.docker/dev-seed.sql`. Der Dev-Seed legt **nur für die lokale Entwicklung** zwei
Testkonten an (Anmeldung per E-Mail-Adresse):

| Benutzer     | Rolle         | E-Mail                   | Passwort    |
|--------------|---------------|--------------------------|-------------|
| `RalphAdmin` | Administrator | `ralphadmin@example.com` | `Test1234!` |
| `RalphUser`  | Normal user   | `ralphuser@example.com`  | `Test1234!` |

`dev-seed.sql` liegt unter `.docker/` und ist damit per `.gitattributes` aus dem
Release-Paket ausgeschlossen. Im Docker-Stack kommen die Zugangsdaten aus den
Umgebungsvariablen in `docker-compose.yml`; der Web-Installer ist dort gesperrt,
weil die Datenbank bereits eingerichtet ist.

---

## Manuelle Installation auf Live-Server

### 1. Dateien bereitstellen

**Option A: Sauberes Deploy-Paket via `git archive`** (empfohlen, schließt Dev-Artefakte aus):

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git /tmp/ppb-src
cd /tmp/ppb-src
git archive --format=tar.gz --prefix=powerphpboard/ HEAD > /tmp/deploy.tar.gz

# Auf dem Live-Server entpacken
tar -xzf /tmp/deploy.tar.gz -C /var/www
mv /var/www/powerphpboard /var/www/forum   # oder Ziel nach Wahl
```

Das Archiv enthält **nur** Live-relevante Dateien - Tests, Docker-Configs, Docs und
Analyse-Configs sind via `.gitattributes export-ignore` ausgeschlossen.

**Option B: Git-Clone + manuelles Aufräumen:**

```bash
cd /var/www
git clone https://github.com/schubertnico/PowerPHPBoard.git forum
cd forum
rm -rf tests docs todos .docker .github phpunit.xml phpstan.neon psalm.xml \
       phpmd.xml rector.php infection.json5 .php-cs-fixer.php \
       install_bugfix_*.sql
```

`install/` bleibt bis zum Ende der Einrichtung liegen (siehe Schritt 5) und wird
danach gelöscht.

### 2. Produktions-Dependencies installieren

```bash
cd /var/www/forum
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

Ergebnis: `vendor/`-Verzeichnis nur mit Produktions-Paketen.

### 3. Berechtigungen

```bash
# Ownership für Apache/Nginx (Debian/Ubuntu: www-data)
chown -R www-data:www-data /var/www/forum

# Verzeichnisse: 750, Dateien: 640
find /var/www/forum -type d -exec chmod 750 {} \;
find /var/www/forum -type f -exec chmod 640 {} \;

# Log-Verzeichnis muss beschreibbar sein
chmod 770 /var/www/forum/logs

# Nur für den Web-Installer: Forumverzeichnis vorübergehend beschreibbar,
# damit er config.local.php anlegen kann (danach wieder 750)
chmod 770 /var/www/forum

# Nach der Installation: Zugangsdaten nur für den Webserver lesbar
chmod 640 /var/www/forum/config.local.php
```

### 4. `.htaccess` nicht entfernen

Das Repository enthält **neun** `.htaccess`-Dateien (Root, includes/, inc/, logs/,
docs/, todos/, tests/, bin/, install/templates/). Die Root-`.htaccess` ist essenziell für:

- Blockieren sensibler Dateien (config.inc.php, config.local.php, *.sql, includes/, logs/, bin/ ...)
- Security-Header (X-Frame-Options, Referrer-Policy, ...)
- Directory-Listing aus

Achte darauf, dass dein FTP/Deploy-Tool versteckte Dateien (dotfiles) überträgt!

### 5. Web-Installer aufrufen

`https://forum.example.com/install/` öffnen und die fünf Schritte durchlaufen – siehe
[Installation mit dem Web-Installer](#installation-mit-dem-web-installer). Danach
`install/` löschen und die Rechte des Forumverzeichnisses wieder auf `750` setzen:

```bash
rm -rf /var/www/forum/install
chmod 750 /var/www/forum
```

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

Nginx respektiert keine `.htaccess`-Dateien. Die äquivalenten Regeln müssen
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

    # Sensible Verzeichnisse (inkl. CLI-Werkzeuge und Installer-Vorlagen)
    location ~ ^/(includes|inc|tests|docs|todos|logs|vendor|node_modules|bin|install/templates)/ {
        deny all; return 404;
    }

    # Nach der Installation: Web-Installer komplett sperren (bzw. install/ löschen)
    # location ^~ /install/ { deny all; return 404; }

    # Sensible Dateien (Root-Ebene)
    location ~ ^/(config\.inc\.php|config\.local\.php|config\.inc\.local\.php|header\.inc\.php|footer\.inc\.php|functions\.inc\.php|english\.inc\.php|deutsch-(du|sie)\.inc\.php|install\.sql|install_bugfix.*\.sql|create-admin\.php|composer\.(json|lock)|phpstan\.neon|psalm\.xml|phpmd\.xml|phpunit\.xml|rector\.php|infection\.json5|\.php-cs-fixer\.php|README\.(md|html)|CONTRIBUTING\.md|SECURITY\.md|INSTALLATION\.md|LICENSE|Dockerfile|docker-compose\.yml)$ {
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

    # Max. Upload-Größe
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

Das übernimmt normalerweise der [Web-Installer](#installation-mit-dem-web-installer) –
er liest dieselbe Datei `install.sql` ein. Von Hand:

```bash
mysql -u ppb_user -p PowerPHPBoard_v2 < install.sql
```

Damit sind alle Tabellen inklusive Rate-Limit- und Password-Reset-Tokens angelegt,
außerdem die Zeile mit den Forum-Einstellungen (`ppb_config`, `id = 1`) mit sicheren
Vorgaben: HTML in Beiträgen aus, BBCode und Smilies an.

### 3. Schema-Übersicht

| Tabelle                | Beschreibung                                       |
|------------------------|----------------------------------------------------|
| `ppb_users`            | Benutzerkonten (username UNIQUE, status enum)      |
| `ppb_boards`           | Foren, Kategorien, Moderatoren, Board-Passwort     |
| `ppb_posts`            | Threads und Beiträge (Spalte `type`)              |
| `ppb_config`           | Board-Konfiguration (Title, Farben, Sprache, ...)  |
| `ppb_visits`           | Session-/Private-Board-Besuchsdaten                |
| `ppb_password_resets`  | Einmal-Tokens für Passwort-Reset (SHA256 gehasht) |
| `ppb_rate_limits`      | Brute-Force-Zähler für Login und Reset           |

**Wichtige Constraints:**
- `ppb_users.username` ist `UNIQUE` (seit 2.1.0).
- `ppb_rate_limits(action, identifier)` ist `UNIQUE` (Upsert-Logik).

### 4. Admin-Konto

`install.sql` legt seit 2.3.0 **keinen** Administrator mehr an (früher gab es den
Standard-Administrator „Gott“ mit öffentlich bekanntem Passwort). Den ersten
Administrator erstellt:

- der **Web-Installer** in Schritt 4, oder
- das **CLI-Werkzeug** auf der Kommandozeile (das Passwort wird abgefragt, unter Linux/macOS verdeckt):

  ```bash
  php bin/create-admin.php --user=Admin --email=admin@example.com
  ```

  Mit `--update` statt `--user` wird ein bestehendes Konto (per E-Mail gefunden)
  zum Administrator und bekommt ein neues Passwort – der Notfallweg, wenn niemand
  mehr Zugang zum Adminbereich hat. Für Skripte liest `--password-stdin` das
  Passwort aus der Standardeingabe. Über das Web ist das Skript gesperrt
  (`.htaccess` und Prüfung auf `PHP_SAPI`).

Ältere Installationen, die das Konto „Gott“ noch haben: Passwort ändern oder das
Konto löschen.

---

## Konfiguration

### Rangfolge der Zugangsdaten

`config.inc.php` enthält seit 2.3.0 keine Zugangsdaten mehr und wird bei jedem Update
überschrieben. Datenbank- und Mail-Einstellungen werden in dieser Reihenfolge gelesen
(höchste zuerst):

1. **`config.local.php`** – schreibt der Web-Installer, kann auch von Hand angelegt werden
2. **Umgebungsvariablen** `PPB_DB_HOST`, `PPB_DB_PORT`, `PPB_DB_USER`, `PPB_DB_PASS`,
   `PPB_DB_NAME`, `PPB_MAIL_HOST`, `PPB_MAIL_PORT`, `PPB_MAIL_FROM`, `PPB_MAIL_USER`,
   `PPB_MAIL_PASS`, `PPB_MAIL_ENCRYPTION`
3. **Vorgaben**: `localhost:3306`, Benutzer `root` ohne Passwort, Datenbank
   `PowerPHPBoard_v2`, Mail über `mailpit:1025` ohne Anmeldung und ohne Verschlüsselung

`config.local.php` gewinnt, weil sie die ausdrückliche Einstellung genau dieser
Installation ist. Im Docker-Stack gibt es die Datei nicht – dort greifen die
Umgebungsvariablen. Fehlt in `config.local.php` ein Schlüssel (z. B. der ganze
`mail`-Block), gilt der Wert der nächsten Stufe.

#### Variante A: `config.local.php` (empfohlen, legt der Web-Installer an)

```php
<?php

declare(strict_types=1);

return [
    'mysql' => [
        'server' => 'localhost',
        'port' => 3306,
        'user' => 'forum_user',
        'password' => 'GEHEIMES_PASSWORT',
        'database' => 'forum_prod',
    ],
    'mail' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'from' => 'forum@example.com',
        'user' => 'forum@example.com',
        'password' => 'PASSWORT_DES_POSTFACHS',
        'encryption' => 'starttls', // none, starttls oder ssl
    ],
];
```

- Liegt neben `config.inc.php` im Forumverzeichnis, ist per `.gitignore` von der
  Versionierung ausgeschlossen und per `.htaccess` vor direktem Abruf geschützt.
- Rechte `640` (bzw. `600`), damit andere Konten auf dem Server sie nicht lesen.
- Die Datei gibt nur ein Array zurück. Sonderzeichen in Passwörtern bitte in
  einfachen Anführungszeichen schreiben und `'` sowie `\` mit `\` maskieren – oder die
  Datei vom Installer erzeugen lassen, der das automatisch richtig macht.

#### Variante B: Environment-Variablen im Webserver

**Apache (`.htaccess` oder VirtualHost):**
```apache
SetEnv PPB_DB_HOST db.internal
SetEnv PPB_DB_USER forum_user
SetEnv PPB_DB_PASS "GEHEIMES_PASSWORT"
SetEnv PPB_DB_NAME forum_prod
SetEnv PPB_MAIL_HOST smtp.example.com
SetEnv PPB_MAIL_PORT 587
SetEnv PPB_MAIL_FROM forum@example.com
SetEnv PPB_MAIL_USER forum@example.com
SetEnv PPB_MAIL_PASS "PASSWORT_DES_POSTFACHS"
SetEnv PPB_MAIL_ENCRYPTION starttls
```

**Nginx (Umgebung über PHP-FPM-Pool-Config):**

In `/etc/php/8.4/fpm/pool.d/forum.conf`:
```ini
env[PPB_DB_HOST]  = db.internal
env[PPB_DB_USER]  = forum_user
env[PPB_DB_PASS]  = GEHEIMES_PASSWORT
env[PPB_DB_NAME]  = forum_prod
env[PPB_MAIL_HOST] = smtp.example.com
env[PPB_MAIL_PORT] = 587
env[PPB_MAIL_FROM] = forum@example.com
env[PPB_MAIL_USER] = forum@example.com
env[PPB_MAIL_PASS] = PASSWORT_DES_POSTFACHS
env[PPB_MAIL_ENCRYPTION] = starttls
```

Ein abweichender Datenbank-Port wird mit `PPB_DB_PORT` gesetzt (Standard `3306`).

### Board-Einstellungen

Nach der Installation im Admin-Panel unter "Allgemein" setzen:

- **Boardtitel**: Name des Forums (Default: `PowerPHPBoard 2.3.0`, der Web-Installer fragt ihn ab)
- **Board-URL**: Vollständige URL (für Links in E-Mails)
- **Admin-E-Mail**: Kontaktadresse des Forums – Absender aller Mails, solange kein
  eigener Absender (`from` bzw. `PPB_MAIL_FROM`) eingestellt ist; sonst geht sie als
  Antwortadresse (`Reply-To`) mit
- **Sprache**: `English`, `Deutsch-Sie` oder `Deutsch-Du` (Default ab 2.2.0: `Deutsch-Du`)
- **HTML in Beiträgen**: `an` oder `aus` – seit 2.3.0 standardmäßig `aus`; `an`
  erlaubt HTML-Code in Beiträgen und damit Cross-Site-Scripting (nicht empfohlen)
- **BBCode in Beiträgen**, **Smilies in Beiträgen**: `an`/`aus`

---

## E-Mail-Versand (SMTP)

PowerPHPBoard sendet E-Mails bei:

- Registrierung (Willkommens-Mail)
- Passwort-Reset (Token-Link)
- User-zu-User (Sendmail-Formular)

Die Versand-Klasse ist `PowerPHPBoard\Mailer` – ein schlanker SMTP-Client ohne
externe Abhängigkeiten (kein PHP-`mail()`). Er beherrscht:

- **Verschlüsselung** `none`, `starttls` (Verbindung beginnt unverschlüsselt und
  wechselt nach `EHLO` per STARTTLS, meist Port 587) oder `ssl` (von Beginn an
  verschlüsselt, meist Port 465); nur TLS 1.2 und 1.3
- **Zertifikatsprüfung** immer eingeschaltet – als SMTP-Server deshalb den Namen
  eintragen, auf den das Zertifikat ausgestellt ist (z. B. `smtp.ihr-hoster.de`,
  nicht `localhost`)
- **Anmeldung** per `AUTH PLAIN` oder `AUTH LOGIN`, je nachdem, was der Server
  anbietet – nur wenn ein Benutzer eingetragen ist. Über eine unverschlüsselte
  Verbindung meldet er sich nur an, wenn ausdrücklich `none` eingestellt ist; bietet
  ein Server bei `starttls` kein STARTTLS an, bricht der Versand ab, statt das
  Passwort im Klartext zu senden
- **Fehlerprotokoll** mit Arbeitsschritt, Antwortcode und Kurztext des Servers in
  `logs/php-error.log` bzw. dem PHP-Fehlerprotokoll – Passwort und Anmeldezeilen
  erscheinen dort nie

### Dev: Mailpit (Docker-Setup)

Bereits vorkonfiguriert in `docker-compose.yml`. Mails landen in der Mailpit-UI
auf http://localhost:8032 und werden nicht wirklich versendet. Mailpit braucht
weder Anmeldung noch Verschlüsselung (`encryption` bleibt `none`).

### Produktion: Postfach beim Hoster

Fast alle Hoster verlangen für den Versand eine Anmeldung mit einem E-Mail-Postfach.
Die Angaben finden Sie im Kundenmenü Ihres Hosters beim E-Mail-Postfach
(„Postausgangsserver“, „SMTP“). Typische Werte:

| Einstellung      | Wert                                                           |
|------------------|----------------------------------------------------------------|
| SMTP-Server      | Postausgangsserver des Hosters, z. B. `smtp.ihr-hoster.de`     |
| Verschlüsselung  | `starttls` mit Port `587` – oder `ssl` mit Port `465`          |
| Benutzername     | meist die vollständige E-Mail-Adresse des Postfachs            |
| Passwort         | Passwort des Postfachs                                         |
| Absender         | dieselbe Adresse wie das Postfach (siehe Hinweis unten)        |

Im Web-Installer (Schritt 3) tragen Sie die Werte direkt ein und prüfen sie mit
„Test-Mail senden“. Später gehören sie in `config.local.php` (siehe
[Konfiguration](#konfiguration)) oder in die Umgebungsvariablen:

```text
PPB_MAIL_HOST       = smtp.ihr-hoster.de
PPB_MAIL_PORT       = 587
PPB_MAIL_ENCRYPTION = starttls
PPB_MAIL_USER       = forum@ihre-domain.de
PPB_MAIL_PASS       = PASSWORT_DES_POSTFACHS
PPB_MAIL_FROM       = forum@ihre-domain.de
```

Hinweise:

- **Absender und Antwortadresse:** Ist ein Absender eingestellt (`from` in
  `config.local.php`, `PPB_MAIL_FROM` oder im Installer die „Absenderadresse“), steht
  er im From aller Mails und ist auch der Absender gegenüber dem SMTP-Server
  (`MAIL FROM`). Die Admin-E-Mail-Adresse aus den Einstellungen (Adminbereich
  „Allgemein“) geht dann als Antwortadresse (`Reply-To`) mit; bei Mails von Mitglied
  zu Mitglied ist es die Adresse des schreibenden Mitglieds. Ohne eingestellten
  Absender ist die Admin-E-Mail-Adresse der Absender. Viele Mailserver nehmen nur
  Mails an, deren Absender zum angemeldeten Postfach gehört, und SPF/DMARC prüfen die
  Domain des Absenders – tragen Sie deshalb die Adresse des Postfachs als Absender
  ein. Die Test-Mail im Installer verwendet dieselben Adressen.
- **App-Passwort:** Große Freemail-Anbieter lassen die Anmeldung per SMTP oft nur mit
  einem eigenen App-Passwort zu, sobald für das Konto die Zwei-Faktor-Anmeldung aktiv
  ist. Das normale Passwort wird dann mit `535` abgelehnt; das App-Passwort erzeugen
  Sie in den Sicherheitseinstellungen des Kontos und tragen es statt des normalen
  Passworts ein. Für ein Forum ist ein Postfach bei Ihrem Webhoster meist die
  einfachere Wahl.
- **Ohne Anmeldung:** Ein interner Relay mit Freigabe per IP-Adresse (z. B. `localhost`
  Port `25` beim eigenen Server) funktioniert weiterhin mit leerem Benutzer und
  `encryption` = `none`.

### Fehlermeldungen beim Versand

Die Test-Mail im Installer zeigt den Grund direkt an; im Betrieb steht er im
Fehlerprotokoll in einer Zeile wie
`[Mailer] Versand über smtp.ihr-hoster.de:587 (Verschlüsselung starttls, mit Anmeldung) fehlgeschlagen – …`.

| Meldung (Auszug) | Ursache und Abhilfe |
|------------------|---------------------|
| `Anmeldung (AUTH …): Server antwortet 535 …` | Benutzername oder Passwort falsch – bei Freemail-Konten mit Zwei-Faktor-Anmeldung App-Passwort verwenden. |
| `STARTTLS: TLS-Aushandlung fehlgeschlagen: … certificate verify failed` | Das Zertifikat passt nicht zum Servernamen oder ist nicht vertrauenswürdig. Den Servernamen aus dem Zertifikat eintragen (nicht `localhost` oder eine IP-Adresse). |
| `STARTTLS: Der Server bietet keine Verschlüsselung per STARTTLS an` | Port und Verschlüsselung passen nicht zusammen, z. B. Port 465 mit `starttls` – dort `ssl` wählen. |
| `SSL/TLS-Verbindung fehlgeschlagen: … wrong version number` | Der Port spricht kein SSL/TLS von Beginn an, z. B. Port 587 mit `ssl` – dort `starttls` wählen. |
| `Server antwortet 530 … STARTTLS` bzw. `… Authentication required` | Der Server verlangt Verschlüsselung bzw. Anmeldung: `starttls` wählen bzw. Benutzer und Passwort eintragen. |
| `Anmeldung: Der Server bietet keine Anmeldung (AUTH) an` | Viele Server bieten die Anmeldung erst nach STARTTLS an – `starttls` wählen. |
| `Verbindungsaufbau fehlgeschlagen` / `Zeitüberschreitung` | Servername oder Port falsch, oder der Hoster sperrt ausgehende Verbindungen zu fremden Mailservern – dann seinen eigenen Postausgangsserver verwenden. |
| `Unbekannte Verschlüsselung „…“` | Nur `none`, `starttls` und `ssl` sind erlaubt; unbekannte Werte werden nie stillschweigend unverschlüsselt versendet. |
| `Für verschlüsselten Versand fehlt die PHP-Erweiterung openssl` | `openssl` im Kundenmenü bzw. in der `php.ini` aktivieren. |

### Kein SMTP verfügbar?

Ist der Mailversand nicht eingerichtet, schlägt der `Mailer` fehl und schreibt einen
Eintrag ins Fehlerprotokoll. Registrierung und Passwort-Reset funktionieren weiterhin,
die Mitglieder bekommen aber keine Mail.

---

## Erste Schritte

### 1. Installation prüfen

Browser öffnen:
```text
http://localhost:8085/          (Docker)
http://forum.example.com/       (Produktion)
```

Du solltest die leere Boardlist mit dem Standard-Theme sehen.

### 2. Als Admin einloggen

Mit E-Mail-Adresse und Passwort des Administrators anmelden, den Sie im Web-Installer
(Schritt 4) bzw. mit `bin/create-admin.php` angelegt haben. Im Docker-Stack gibt es
die Testkonten aus `.docker/dev-seed.sql` (siehe [Docker Installation](#docker-installation)).

### 3. Erstes Board erstellen

1. Als Admin eingeloggt auf `/admin/` gehen.
2. "Board hinzufügen" auswählen.
3. Kategorie, Name, Beschreibung eingeben.
4. Speichern.

### 4. Konfiguration prüfen

Im Admin unter "General Settings":

- `boardurl` auf die tatsächliche URL setzen (wichtig für Passwort-Reset-Links!)
- `adminemail` auf eine existierende Adresse setzen.
- `language` auf bevorzugte Sprache setzen.

### 5. Testregistrierung

1. Ausloggen.
2. Auf "Register" klicken, Boardregeln akzeptieren.
3. Formular ausfüllen, abschicken.
4. Mailpit (Docker) bzw. echten Posteingang prüfen - Willkommensmail muss eintreffen.
5. Login testen.

---

## Upgrade

### Von 2.2.x auf 2.3.0 (Web-Installer)

Für ein Update ist der Web-Installer **nicht** nötig: Es gibt keine Datenbank-Migration,
Datenbank und Zugangsdaten bleiben gültig.

1. **Backup** von Datenbank und Dateien anlegen (siehe unten bei 2.1.0).
2. **Zugangsdaten sichern.** Die neue `config.inc.php` enthält keine Zugangsdaten mehr
   und ersetzt die alte.
   - Wer Umgebungsvariablen nutzt (`SetEnv`, PHP-FPM, Docker): nichts zu tun.
   - Wer Zugangsdaten direkt in `config.inc.php` eingetragen hatte: **vor** dem Hochladen
     eine `config.local.php` mit diesen Werten anlegen (Vorlage unter
     [Konfiguration](#konfiguration)).
   - Verlangt der Mailserver eine Anmeldung oder Verschlüsselung (bei den meisten
     Hostern der Fall), im `mail`-Block zusätzlich `user`, `password` und
     `encryption` eintragen bzw. `PPB_MAIL_USER`, `PPB_MAIL_PASS` und
     `PPB_MAIL_ENCRYPTION` setzen – siehe [E-Mail-Versand (SMTP)](#e-mail-versand-smtp).
     Ohne diese Angaben versendet das Forum wie bisher ohne Anmeldung.
   - **Absender prüfen:** Ist `from` bzw. `PPB_MAIL_FROM` gesetzt, steht diese Adresse
     ab 2.3.0 im From aller Mails (bisher immer die Admin-E-Mail); die Admin-E-Mail
     geht dann als Antwortadresse mit. Tragen Sie dort die Adresse des Postfachs ein,
     über das versendet wird – oder lassen Sie den Wert leer, dann bleibt die
     Admin-E-Mail der Absender.
3. **Dateien aktualisieren** (`git pull` bzw. Upload per FTP). Das Verzeichnis `install/`
   dabei weglassen oder direkt danach löschen.
4. Ein altes `create-admin.php` im Forumverzeichnis löschen – es ist durch
   `bin/create-admin.php` (nur Kommandozeile) ersetzt.
5. **Empfehlungen** im Adminbereich unter „Allgemein“: „HTML in Beiträgen“ auf `aus`
   stellen und die Board-URL eintragen (sie steht in den Links der Forum-Mails).
   Existiert noch das frühere Standardkonto „Gott“, dessen Passwort ändern oder das
   Konto löschen.
6. **Alte Board-Passwortkopien entfernen** (empfohlen, z. B. in phpMyAdmin). Bis 2.2.x
   stand für angemeldete Mitglieder eine Base64-Kopie des Board-Passworts in
   `ppb_visits`. 2.3.0 speichert dort nur noch einen Zugangsnachweis und stellt die
   Board-Passwörter selbst beim ersten richtigen Aufruf auf Argon2id um. Die alten
   Kopien löscht diese Anweisung; Mitglieder privater Boards geben das Passwort danach
   einmal neu ein:

   ```sql
   UPDATE ppb_visits SET password = '' WHERE type = 'Board';
   ```

Leitet die Startseite nach dem Update auf `install/` weiter, findet das Forum seine
Zugangsdaten nicht (z. B. weil die alte `config.inc.php` überschrieben wurde):
`config.local.php` anlegen und `install/` löschen. Der Installer selbst würde die
vorhandenen Tabellen nicht überschreiben, sondern mit einem Hinweis abbrechen.

### Von v2.1.x nach 2.2.0 (Bootstrap-5-Frontend)

Reines Frontend-Refactor, **keine** DB-Migration nötig.

```bash
cd /var/www/forum
git pull origin main
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

Sprache umstellen (falls vorher noch English):

```sql
UPDATE ppb_config SET language = 'Deutsch-Du' WHERE id = 1;
```

Eigene Anpassungen in `inc/header.ppb`, `inc/footer.ppb`, `header.inc.php`,
`footer.inc.php` oder `ppb.css` müssen nach dem Pull manuell mit den neuen
Bootstrap-Templates abgeglichen werden – das Layout hat sich grundlegend
geändert (Cards statt verschachtelter Tabellen, Bootstrap-CDN statt
Inline-Styles).

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
- `ppb_password_resets` (Tokens für Reset-Flow)
- `ppb_rate_limits` (Brute-Force-Zähler)

**Nach dem Upgrade:**

- Alle aktiven Sessions bleiben gültig.
- Nutzer mit alten Base64-Passwörtern werden beim ersten Login automatisch auf Argon2id migriert.
- Mindestpasswortlänge ist jetzt 8 Zeichen (vorher 6) - bestehende Passwörter sind nicht betroffen.

### Von v1.x auf 2.2.0 (großer Sprung)

**Wichtig:** Zwischen v1 und v2 wurde das DB-Schema teilweise umgebaut.
Empfohlen: Daten exportieren, Version 2 **frisch** installieren, dann Daten
selektiv reimportieren. Die alte `upgrade_v1_to_v2.sql` ist **nicht Bestandteil**
des 2.2.0-Releases – kontaktiere bei Bedarf [Support](#support).

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
- [ ] `.htaccess`-Dateien vollständig übertragen (Root, includes/, inc/, logs/, docs/, todos/, tests/, bin/, install/templates/)
- [ ] Verzeichnis `install/` nach der Installation **gelöscht**
- [ ] `config.local.php` hat die Rechte `640` (bzw. `600`)
- [ ] Test: `curl -I https://forum.example.com/config.local.php` → HTTP 403
- [ ] Test: `curl -I https://forum.example.com/install/` → HTTP 404 (gelöscht) bzw. 403 (gesperrt)
- [ ] Test: `curl -I https://forum.example.com/config.inc.php` → HTTP 403
- [ ] Test: `curl -I https://forum.example.com/includes/Security.php` → HTTP 403
- [ ] Test: `curl -I https://forum.example.com/.git/HEAD` → HTTP 403
- [ ] Test: `curl -I https://forum.example.com/install.sql` → HTTP 403
- [ ] Test: Security-Header via `curl -I https://.../` sichtbar (X-Frame-Options, X-Content-Type-Options, Referrer-Policy)
- [ ] Kein Konto mit bekanntem Standardpasswort (früheres „Gott“-Konto geändert oder gelöscht)
- [ ] „HTML in Beiträgen“ im Adminbereich ausgeschaltet, Board-URL eingetragen
- [ ] Admin-Email auf existierende Adresse gesetzt
- [ ] SMTP getestet (Passwort-Reset-Mail kommt an)
- [ ] Rate-Limit getestet (10x falsches Login löst Lock aus)
- [ ] DB-Backup-Strategie eingerichtet (mindestens täglich)
- [ ] Altes `create-admin.php` im Hauptverzeichnis und `install_bugfix_*.sql` **gelöscht** oder unerreichbar
- [ ] Test: `curl -I https://forum.example.com/bin/create-admin.php` → HTTP 403
- [ ] `logs/`-Verzeichnis ist außerhalb des DocumentRoot oder per `.htaccess` gesperrt
- [ ] Composer installiert nur Prod-Dependencies (`--no-dev`)
- [ ] `vendor/`, `tests/`, `docs/`, `.docker/`, `.github/` sind per `.htaccess` und/oder Webserver-Config gesperrt

---

## Fehlerbehebung

### Web-Installer: „Der Installer ist gesperrt“

Das ist Absicht, sobald das Forum eingerichtet ist (siehe
[Sperre](#sperre)). Die Seite nennt den Grund: Sperrdatei `install/.installed`,
vorhandene `config.local.php`, eingerichtete Datenbank oder eine konfigurierte, aber
gerade nicht erreichbare Datenbank. Im letzten Fall zuerst die Datenbank bzw. die
Zugangsdaten prüfen. Für eine bewusste Neuinstallation `config.local.php` und
`install/.installed` löschen und eine leere Datenbank verwenden.

### Web-Installer: „Diese Datenbank enthält bereits PowerPHPBoard-Tabellen“

Der Installer überschreibt keine Daten. Für ein Update wird er nicht gebraucht
(siehe [Upgrade](#von-22x-auf-230-web-installer)); für eine Neuinstallation eine leere
Datenbank wählen oder die `ppb_`-Tabellen nach einer Datensicherung selbst löschen.
Schlägt eine Installation mittendrin fehl, entfernt der Installer die in diesem Lauf
angelegten Tabellen automatisch wieder.

### Web-Installer: Verbindung zur Datenbank schlägt fehl

Die Meldung nennt die Ursache ohne Zugangsdaten, z. B. „Benutzername oder Passwort ist
falsch“ (MySQL-Fehler 1045), „Die Datenbank existiert nicht“ (1049), „keine
Berechtigung“ (1044) oder „Server nicht erreichbar“ (2002). Server, Port und
Datenbankname stehen im Kundenmenü des Hosters; bei vielen Hostern ist der Server
**nicht** `localhost`. Im Security-Log (`logs/security.log`) steht nur der Fehlercode.

### "Class not found" Fehler

```bash
composer dump-autoload
# bzw.
composer install --no-dev --optimize-autoloader
```

Falls nur eine der neuen Klassen fehlt (`Validator`, `RateLimiter`, `Mailer`):
`config.inc.php` muss sie laden - prüfen, ob die `require_once`-Zeilen im
oberen Abschnitt stehen.

### `.htaccess` wirkt nicht

Apache: `AllowOverride None` ist der Default! Setze in der VHost-Config:

```apache
<Directory /var/www/forum>
    AllowOverride All
</Directory>
```

und lade Apache neu. Ohne `AllowOverride All` sind alle `.htaccess`-Schutzregeln
wirkungslos und sensible Dateien **wären öffentlich erreichbar**.

Nginx: `.htaccess` wird generell nicht unterstützt. Äquivalente Regeln müssen
in `server { ... }` (siehe [Webserver-Konfiguration](#webserver-konfiguration)).

### "Permission denied" bei Logs

```bash
chmod 770 /var/www/forum/logs
chown www-data:www-data /var/www/forum/logs
ls -la /var/www/forum/logs
```

### "Connection refused" zur Datenbank

1. MySQL-Service läuft?
   ```bash
   systemctl status mysql
   ```
2. `PPB_DB_HOST`/`-USER`/`-PASS`/`-NAME` korrekt gesetzt?
3. Rechte prüfen:
   ```sql
   SHOW GRANTS FOR 'ppb_user'@'localhost';
   ```

### Weiße Seite / HTTP 500

1. PHP-Error-Log prüfen:
   ```bash
   tail -f /var/www/forum/logs/php-error.log
   tail -f /var/log/apache2/error.log
   ```
2. Temporär `PPB_DEBUG=true` setzen, neu laden, Ausgabe analysieren, danach
   zurück auf `false`.

### Rate-Limit schießt zu früh / Accounts werden gesperrt

```sql
-- Manuell Zähler für eine IP zurücksetzen
DELETE FROM ppb_rate_limits WHERE identifier = '1.2.3.4';

-- Board-Passwörter zählen je IP und Board (Kennung „IP|board:ID“);
-- das richtige Board-Passwort setzt den Zähler selbst zurück
DELETE FROM ppb_rate_limits WHERE action = 'boardpwd' AND identifier LIKE '1.2.3.4|board:%';

-- Alle Zähler löschen
TRUNCATE TABLE ppb_rate_limits;
```

Limits anpassen direkt im Code (`login.php`, `sendpassword.php`) via
`RateLimiter(new DatabaseRateLimitStorage($db), maxAttempts: ..., windowSeconds: ..., lockSeconds: ...)`.

### Passwort-Reset-Mail kommt nicht an

1. Mailpit (Dev) oder SMTP-Log (Prod) prüfen – ist die Verbindung zum SMTP-Host hergestellt?
2. `logs/php-error.log` nach `[Mailer]`-Zeilen durchsuchen; Arbeitsschritt, Antwortcode
   und Abhilfe stehen unter [Fehlermeldungen beim Versand](#fehlermeldungen-beim-versand).
3. `boardurl` in `ppb_config` muss gesetzt sein, sonst wird eine lokale URL aus
   `$_SERVER['HTTP_HOST']` gebaut (funktioniert hinter Reverse-Proxies evtl. nicht).
4. Token-Gültigkeit ist 1 Stunde - danach Status "Invalid or expired".

### Session-Probleme

1. Cookie im Browser vorhanden? (`PHPSESSID` oder konfigurierter Name)
2. `session.save_path` beschreibbar?
   ```bash
   php -r "echo session_save_path() . PHP_EOL;"
   ```
3. Bei HTTPS und `session.cookie_secure=On` werden Cookies über HTTP nicht gesendet.

### Diagnose-Tools

**DB-Verbindung testen** (temporär!):

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

**SMTP testen** (mit den wirksamen Einstellungen aus `config.local.php` bzw. den
Umgebungsvariablen; gibt bei einem Fehler den Grund aus):

```bash
docker compose exec web php -r "
require '/var/www/html/config.inc.php';
\$m = PowerPHPBoard\Mailer::fromConfig(\$mail);
var_dump(\$m->send('test@example.com', PowerPHPBoard\Mailer::senderAddress([], \$mail), 'Test', 'Hallo'));
echo \$m->lastError(), PHP_EOL;
"
```

**Erweiterungen prüfen**:

```bash
php -m | grep -iE "pdo_mysql|mbstring|openssl|session|filter"
```

---

## Support

- **GitHub Issues:** [https://github.com/schubertnico/PowerPHPBoard/issues](https://github.com/schubertnico/PowerPHPBoard/issues)
- **Projekt-Website:** [https://www.powerscripts.org](https://www.powerscripts.org)
- **E-Mail:** info@schubertmedia.de
- **README:** [README.md](README.md) für Schnellstart und Architekturüberblick
- **Security-Policy:** [SECURITY.md](SECURITY.md)

---

**Stand:** 2026-09-25
**Version:** 2.3.0 (Web-Installer, SMTP-Anmeldung, Sicherheitskorrekturen)
