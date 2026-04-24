# Installationsanleitung

Diese Anleitung beschreibt die vollstaendige Installation von PowerPHPBoard.

---

## Inhaltsverzeichnis

1. [Systemanforderungen](#systemanforderungen)
2. [Schnellinstallation](#schnellinstallation)
3. [Docker Installation](#docker-installation)
4. [Manuelle Installation](#manuelle-installation)
5. [Webserver-Konfiguration](#webserver-konfiguration)
6. [Datenbank einrichten](#datenbank-einrichten)
7. [Konfiguration](#konfiguration)
8. [Erste Schritte](#erste-schritte)
9. [Upgrade von v1.x](#upgrade-von-v1x)
10. [Fehlerbehebung](#fehlerbehebung)

---

## Systemanforderungen

### Minimum

| Komponente | Version |
|-----------|---------|
| PHP | 8.3+ |
| MySQL | 8.0+ |
| Speicher | 64 MB RAM |
| Festplatte | 50 MB |

### Empfohlen

| Komponente | Version |
|-----------|---------|
| PHP | 8.4 |
| MySQL | 8.0+ oder MariaDB 10.6+ |
| Speicher | 256 MB RAM |
| Festplatte | 100 MB |

### Erforderliche PHP-Erweiterungen

```
php-pdo          # Datenbank-Abstraktion
php-pdo_mysql    # MySQL-Treiber
php-mbstring     # Multibyte-Strings
php-json         # JSON-Verarbeitung (Standard in PHP 8)
php-session      # Session-Verwaltung (Standard)
```

### Optionale PHP-Erweiterungen

```
php-gd           # Bildverarbeitung (Avatare)
php-curl         # HTTP-Anfragen
php-intl         # Internationalisierung
php-opcache      # Performance-Optimierung
```

### PHP-Einstellungen (php.ini)

```ini
; Empfohlene Einstellungen
memory_limit = 128M
max_execution_time = 30
post_max_size = 16M
upload_max_filesize = 8M
session.cookie_httponly = 1
session.cookie_secure = 1       ; Bei HTTPS
session.cookie_samesite = Lax
```

---

## Schnellinstallation

Fuer erfahrene Benutzer:

```bash
# 1. Repository klonen
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard

# 2. Abhaengigkeiten installieren
composer install --no-dev

# 3. Konfiguration erstellen
cp config.inc.php.example config.inc.php
# --> config.inc.php bearbeiten

# 4. Datenbank importieren
mysql -u root -p powerphpboard < install/install.sql

# 5. Berechtigungen setzen (Linux/macOS)
chmod 755 logs/
chmod 644 config.inc.php

# 6. Im Browser oeffnen
# http://localhost/PowerPHPBoard/
```

---

## Docker Installation

### Voraussetzungen

- Docker 20.10+
- Docker Compose 2.0+

### Schritte

**1. Repository klonen:**

```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard
```

**2. Docker-Umgebung starten:**

```bash
docker-compose up -d
```

**3. Container pruefen:**

```bash
docker-compose ps
```

Erwartete Ausgabe:
```
NAME                    STATUS
powerphpboard-php       Up
powerphpboard-mysql     Up
powerphpboard-nginx     Up (optional)
```

**4. Im Browser oeffnen:**

```
http://localhost:8085
```

### Docker-Compose Konfiguration

Die Datei `docker-compose.yml` befindet sich in `.docker/`:

```yaml
name: powerphpboard

services:
  web:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8085:80"
    volumes:
      - ..:/var/www/html
    environment:
      - PPB_DB_HOST=db
      - PPB_DB_USER=powerphpboard
      - PPB_DB_PASS=powerphpboard_secret
      - PPB_DB_NAME=powerphpboard
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:8.0
    ports:
      - "3315:3306"
    environment:
      MYSQL_ROOT_PASSWORD: root_secret
      MYSQL_DATABASE: powerphpboard
      MYSQL_USER: powerphpboard
      MYSQL_PASSWORD: powerphpboard_secret
    volumes:
      - powerphpboard_mysql_data:/var/lib/mysql
      - ../install.sql:/docker-entrypoint-initdb.d/init.sql:ro

  phpmyadmin:
    image: phpmyadmin:latest
    ports:
      - "8088:80"
    environment:
      PMA_HOST: db
      PMA_USER: root
      PMA_PASSWORD: root_secret

  mailpit:
    image: axllent/mailpit:latest
    ports:
      - "1032:1025"
      - "8032:8025"

volumes:
  powerphpboard_mysql_data:
```

### Verfuegbare Services

| Service | Port | URL |
|---------|------|-----|
| Forum | 8085 | http://localhost:8085 |
| phpMyAdmin | 8088 | http://localhost:8088 |
| Mailpit UI | 8032 | http://localhost:8032 |
| MySQL | 3315 | localhost:3315 |

### Nuetzliche Docker-Befehle

```bash
# In .docker Verzeichnis wechseln
cd .docker

# Container starten
docker-compose up -d

# Container stoppen
docker-compose down

# Logs anzeigen
docker-compose logs -f web

# In Web-Container einloggen
docker-compose exec web bash

# MySQL-Konsole
docker-compose exec db mysql -u powerphpboard -ppowerphpboard_secret powerphpboard

# Neustart nach Aenderungen
docker-compose restart web
```

---

## Manuelle Installation

### 1. Dateien herunterladen

**Option A: Git Clone**
```bash
git clone https://github.com/schubertnico/PowerPHPBoard.git
cd PowerPHPBoard
```

**Option B: ZIP-Download**
```bash
wget https://github.com/schubertnico/PowerPHPBoard/archive/main.zip
unzip main.zip
mv PowerPHPBoard-main PowerPHPBoard
cd PowerPHPBoard
```

### 2. Composer-Abhaengigkeiten

```bash
# Fuer Produktion (ohne Dev-Tools)
composer install --no-dev --optimize-autoloader

# Fuer Entwicklung (mit Tests und Analyse-Tools)
composer install
```

### 3. Verzeichnisstruktur pruefen

Nach der Installation sollte folgende Struktur vorhanden sein:

```
PowerPHPBoard/
├── admin/                 # Admin-Bereich
├── images/                # Bilder und Icons
│   ├── avatars/           # Benutzer-Avatare
│   ├── smilies/           # Smilie-Grafiken
│   └── icons/             # Thread-Icons
├── includes/              # Core-Klassen
├── install/               # Installationsskripte
│   ├── install.sql        # Datenbank-Schema
│   └── upgrade_v1_to_v2.sql  # Upgrade-Script
├── lang/                  # Sprachdateien
├── logs/                  # Log-Dateien
├── templates/             # HTML-Templates
├── tests/                 # PHPUnit Tests
├── vendor/                # Composer-Pakete
├── config.inc.php         # Konfiguration
├── composer.json          # Composer-Konfiguration
└── index.php              # Einstiegspunkt
```

### 4. Berechtigungen setzen

**Linux/macOS:**
```bash
# Verzeichnisse
chmod 755 logs/
chmod 755 images/avatars/

# Konfiguration (nach Bearbeitung)
chmod 644 config.inc.php

# Owner setzen (Apache/Nginx)
chown -R www-data:www-data logs/
chown -R www-data:www-data images/avatars/
```

**Windows:**
- Sicherstellen, dass der Webserver Schreibzugriff auf `logs/` hat
- IIS: IUSR-Benutzer Schreibrechte geben

---

## Webserver-Konfiguration

### Apache

**1. DocumentRoot setzen:**

```apache
<VirtualHost *:80>
    ServerName forum.example.com
    DocumentRoot /var/www/PowerPHPBoard

    <Directory /var/www/PowerPHPBoard>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/powerphpboard_error.log
    CustomLog ${APACHE_LOG_DIR}/powerphpboard_access.log combined
</VirtualHost>
```

**2. mod_rewrite aktivieren:**

```bash
a2enmod rewrite
systemctl restart apache2
```

**3. .htaccess (optional, fuer schoene URLs):**

```apache
RewriteEngine On
RewriteBase /

# Direkter Zugriff auf Dateien erlauben
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Alle anderen Anfragen an index.php
RewriteRule ^(.*)$ index.php?route=$1 [QSA,L]
```

### Nginx

```nginx
server {
    listen 80;
    server_name forum.example.com;
    root /var/www/PowerPHPBoard;
    index index.php;

    # Logs
    access_log /var/log/nginx/powerphpboard_access.log;
    error_log /var/log/nginx/powerphpboard_error.log;

    # PHP-Verarbeitung
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Statische Dateien cachen
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Sensible Dateien blockieren
    location ~ /\. {
        deny all;
    }

    location ~ /(config\.inc\.php|composer\.(json|lock)|\.git) {
        deny all;
    }

    # Logs-Verzeichnis blockieren
    location /logs/ {
        deny all;
    }
}
```

### HTTPS einrichten (empfohlen)

**Mit Let's Encrypt:**

```bash
# Certbot installieren (Ubuntu/Debian)
apt install certbot python3-certbot-nginx

# Zertifikat erstellen
certbot --nginx -d forum.example.com

# Automatische Erneuerung
certbot renew --dry-run
```

---

## Datenbank einrichten

### 1. Datenbank erstellen

**MySQL/MariaDB:**

```sql
-- Als root-Benutzer
CREATE DATABASE powerphpboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Benutzer erstellen
CREATE USER 'ppb_user'@'localhost' IDENTIFIED BY 'sicheres_passwort_hier';

-- Rechte vergeben
GRANT ALL PRIVILEGES ON powerphpboard.* TO 'ppb_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Schema importieren

```bash
# Ueber Kommandozeile
mysql -u ppb_user -p powerphpboard < install/install.sql

# Oder in MySQL-Konsole
mysql -u ppb_user -p
USE powerphpboard;
SOURCE install/install.sql;
```

### 3. Datenbank-Schema (Uebersicht)

| Tabelle | Beschreibung |
|---------|--------------|
| `ppb_users` | Benutzerkonten |
| `ppb_boards` | Foren und Kategorien |
| `ppb_posts` | Threads und Beitraege |
| `ppb_config` | Board-Konfiguration |
| `ppb_sessions` | Aktive Sessions |

---

## Konfiguration

### config.inc.php erstellen

```bash
cp config.inc.php.example config.inc.php
```

### Konfiguration bearbeiten

```php
<?php
// config.inc.php

// ============================================
// DATENBANK-KONFIGURATION
// ============================================
define('PPB_DB_HOST', 'localhost');      // Datenbank-Host
define('PPB_DB_NAME', 'powerphpboard');  // Datenbank-Name
define('PPB_DB_USER', 'ppb_user');       // Datenbank-Benutzer
define('PPB_DB_PASS', 'passwort');       // Datenbank-Passwort
define('PPB_DB_PREFIX', 'ppb_');         // Tabellen-Prefix

// ============================================
// BOARD-KONFIGURATION
// ============================================
define('PPB_BOARD_NAME', 'Mein Forum');  // Board-Name
define('PPB_BOARD_URL', 'https://forum.example.com'); // Board-URL

// ============================================
// SESSION-KONFIGURATION
// ============================================
define('PPB_SESSION_LIFETIME', 3600);    // Session-Dauer (Sekunden)
define('PPB_SESSION_NAME', 'PPBSESSID'); // Session-Cookie-Name

// ============================================
// SICHERHEIT
// ============================================
define('PPB_DEBUG', false);              // Debug-Modus (NIEMALS true in Produktion!)

// ============================================
// PFADE
// ============================================
define('PPB_ROOT', __DIR__);             // Installations-Verzeichnis
define('PPB_LOG_PATH', PPB_ROOT . '/logs'); // Log-Verzeichnis
```

### Umgebungsvariablen (Alternative)

Fuer Docker oder Shared Hosting:

```php
<?php
// config.inc.php mit Umgebungsvariablen

define('PPB_DB_HOST', getenv('PPB_DB_HOST') ?: 'localhost');
define('PPB_DB_NAME', getenv('PPB_DB_NAME') ?: 'powerphpboard');
define('PPB_DB_USER', getenv('PPB_DB_USER') ?: 'ppb_user');
define('PPB_DB_PASS', getenv('PPB_DB_PASS') ?: '');
define('PPB_DEBUG', (bool)(getenv('PPB_DEBUG') ?: false));
```

---

## Erste Schritte

### 1. Installation ueberpruefen

Oeffnen Sie das Forum im Browser:
```
http://forum.example.com/
```

Sie sollten die leere Forum-Startseite sehen.

### 2. Admin-Konto erstellen

**Option A: Ueber SQL**

```sql
INSERT INTO ppb_users (
    username, email, password, is_admin, registered
) VALUES (
    'admin',
    'admin@example.com',
    -- Argon2id-Hash fuer 'admin123' (Passwort sofort aendern!)
    '$argon2id$v=19$m=65536,t=4,p=1$...',
    1,
    NOW()
);
```

**Option B: Registrierung + SQL-Update**

1. Registrieren Sie sich normal
2. Setzen Sie Admin-Rechte:
   ```sql
   UPDATE ppb_users SET is_admin = 1 WHERE email = 'ihre@email.com';
   ```

### 3. Erstes Board erstellen

1. Einloggen als Admin
2. "Admin" im Menu klicken
3. "Board erstellen" waehlen
4. Name und Beschreibung eingeben

### 4. Konfiguration anpassen

Im Admin-Bereich unter "Einstellungen":

- Board-Name und Beschreibung
- E-Mail-Einstellungen
- Registrierungsoptionen
- Beitrags-Optionen

---

## Upgrade von v1.x

### Backup erstellen

**WICHTIG: Vor jedem Upgrade ein Backup erstellen!**

```bash
# Datenbank-Backup
mysqldump -u root -p powerphpboard > backup_$(date +%Y%m%d).sql

# Datei-Backup
tar -czvf powerphpboard_backup_$(date +%Y%m%d).tar.gz PowerPHPBoard/
```

### Upgrade-Schritte

**1. Neue Dateien herunterladen:**

```bash
cd /var/www
mv PowerPHPBoard PowerPHPBoard_old
git clone https://github.com/schubertnico/PowerPHPBoard.git
```

**2. Konfiguration uebernehmen:**

```bash
cp PowerPHPBoard_old/config.inc.php PowerPHPBoard/config.inc.php
```

**3. Uploads uebernehmen:**

```bash
cp -r PowerPHPBoard_old/images/avatars/* PowerPHPBoard/images/avatars/
```

**4. Datenbank migrieren:**

```bash
mysql -u ppb_user -p powerphpboard < install/upgrade_v1_to_v2.sql
```

**5. Composer-Abhaengigkeiten:**

```bash
cd PowerPHPBoard
composer install --no-dev
```

**6. Passwort-Migration:**

Bei v1.x wurden Passwoerter Base64-kodiert gespeichert.
PowerPHPBoard 2.0 migriert diese automatisch bei Login zu Argon2id.

Benutzer muessen sich einmal einloggen, um die Migration durchzufuehren.

---

## Fehlerbehebung

### Haeufige Probleme

#### "Class not found" Fehler

```bash
# Composer-Autoloader neu generieren
composer dump-autoload
```

#### "Permission denied" bei Logs

```bash
# Linux/macOS
chmod 755 logs/
chown www-data:www-data logs/

# Pruefen
ls -la logs/
```

#### "Connection refused" zur Datenbank

1. MySQL-Service pruefen:
   ```bash
   systemctl status mysql
   ```

2. Zugangsdaten in `config.inc.php` pruefen

3. MySQL-Benutzerrechte pruefen:
   ```sql
   SHOW GRANTS FOR 'ppb_user'@'localhost';
   ```

#### Weisse Seite (Blank Page)

1. PHP-Fehler aktivieren:
   ```php
   // Temporaer in config.inc.php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

2. Error-Log pruefen:
   ```bash
   tail -f logs/php-error.log
   tail -f /var/log/apache2/error.log
   ```

#### Session-Probleme

1. Session-Verzeichnis pruefen:
   ```bash
   php -r "echo session_save_path();"
   ls -la /var/lib/php/sessions/
   ```

2. Cookie-Einstellungen pruefen:
   - Browser: Cookies aktiviert?
   - HTTPS: Secure-Flag korrekt?

### Diagnose-Tools

**PHP-Info anzeigen:**

```php
<?php
// phpinfo.php (nach Diagnose loeschen!)
phpinfo();
```

**Datenbank-Verbindung testen:**

```php
<?php
// db_test.php (nach Diagnose loeschen!)
require_once 'config.inc.php';

try {
    $pdo = new PDO(
        'mysql:host=' . PPB_DB_HOST . ';dbname=' . PPB_DB_NAME,
        PPB_DB_USER,
        PPB_DB_PASS
    );
    echo "Verbindung erfolgreich!";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage();
}
```

**Erweiterungen pruefen:**

```bash
php -m | grep -E "pdo|mysql|mbstring|json"
```

### Support

- **GitHub Issues:** Bug-Reports und Feature-Requests
- **E-Mail:** support@powerscripts.org
- **Dokumentation:** [README.md](README.md)

---

## Checkliste nach Installation

- [ ] Datenbank-Verbindung funktioniert
- [ ] Keine PHP-Fehler in den Logs
- [ ] Admin-Konto erstellt
- [ ] Erstes Board erstellt
- [ ] HTTPS aktiviert
- [ ] Debug-Modus deaktiviert (`PPB_DEBUG = false`)
- [ ] Installationsverzeichnis geschuetzt oder entfernt
- [ ] Backup-Strategie eingerichtet
- [ ] Monitoring eingerichtet (optional)

---

Stand: Januar 2026
Version: 2.0