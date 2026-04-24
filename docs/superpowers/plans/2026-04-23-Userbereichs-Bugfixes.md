# Userbereich Bugfixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline, user requested "ohne Nachfrage"). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Behebe alle 18 im Userbereich-Audit vom 2026-04-23 dokumentierten Bugs (BUG-001 bis BUG-018).

**Architecture:** Direkte Edits in bestehenden PHP-Dateien. Neue Hilfsklassen `includes/RateLimiter.php`, `includes/Mailer.php`, `includes/Validator.php`. Neue Migration fuer `ppb_password_resets`-Tabelle und Username-UNIQUE-Index. TDD wo sinnvoll (Unit-Tests fuer Helper-Klassen).

**Tech Stack:** PHP 8.4, PDO/MySQL 8, PHPUnit 11, bestehende PSR-4-Autoloader (Namespace `PowerPHPBoard\\`), Docker-Compose (Mailpit SMTP auf `mailpit:1025`).

---

## Bug-Mapping

| Task | Bugs adressiert |
|------|-----------------|
| 1 | Vorbereitung: DB-Migration `install.sql` + Delta-SQL |
| 2 | BUG-003 (UNIQUE username), BUG-016 (pw_resets-Tabelle) |
| 3 | Neue `Validator`-Klasse: BUG-004, BUG-005, BUG-015 |
| 4 | Neue `RateLimiter`-Klasse: BUG-007, BUG-018 |
| 5 | Neue `Mailer`-Klasse: BUG-002 |
| 6 | BUG-001 Registrierung: acception via REQUEST, Username-Unique-Check, Validator nutzen, Mailer nutzen |
| 7 | BUG-010 Signature-XSS: Whitelist-strip_tags in register + profile, force-escape in showthread |
| 8 | BUG-006 Login User-Enumeration, BUG-007 RateLimit, BUG-009 Legacy-Text |
| 9 | BUG-008 Logout: POST + CSRF |
| 10 | BUG-011/012/013 Profil: Password optional, Re-Auth fuer kritische Aenderungen, ICQ-Default |
| 11 | BUG-016/017/018 sendpassword: Token-Flow, Unified-Response, RateLimit, neuer `resetpassword.php` |
| 12 | BUG-014 Posting: Session-only, Email/Password-Fallback entfernen |
| 13 | BUG-015 Posting: Length-Validation |
| 14 | Finalisierung: Audit-Dokumente aktualisieren, Status setzen |

---

## Task 1: Vorbereitung - DB-Migration anlegen

**Files:**
- Create: `install_bugfix_2026-04-23.sql`
- Modify: `install.sql` (UNIQUE username, neue Tabelle am Ende)

**Why:** DB-Aenderungen muessen reproduzierbar sein. Fuer frische Installs in `install.sql`, fuer bestehende Installs in Delta-Migration.

- [ ] **Step 1: Delta-Migration anlegen**

Create `install_bugfix_2026-04-23.sql`:
```sql
-- Migration 2026-04-23: Userbereich Bugfixes
-- Sicher mehrfach ausfuehrbar

-- BUG-003: Username UNIQUE Index
-- Achtung: Falls Duplicates existieren, muessen diese vorher manuell bereinigt werden.
ALTER TABLE ppb_users
    ADD UNIQUE INDEX IF NOT EXISTS idx_users_username_unique (username);

-- BUG-016: Tabelle fuer Passwort-Reset-Tokens
CREATE TABLE IF NOT EXISTS ppb_password_resets (
    id int(11) NOT NULL auto_increment,
    userid int(11) NOT NULL,
    token_hash varchar(64) NOT NULL,
    expires_at int(14) NOT NULL,
    used_at int(14) NOT NULL default '0',
    created_at int(14) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_pwreset_userid (userid),
    INDEX idx_pwreset_token (token_hash),
    INDEX idx_pwreset_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- BUG-007/018: Tabelle fuer Rate-Limits
CREATE TABLE IF NOT EXISTS ppb_rate_limits (
    id int(11) NOT NULL auto_increment,
    action varchar(50) NOT NULL,
    identifier varchar(255) NOT NULL,
    attempts int(11) NOT NULL default '0',
    window_start int(14) NOT NULL,
    locked_until int(14) NOT NULL default '0',
    PRIMARY KEY (id),
    UNIQUE KEY idx_rl_action_identifier (action, identifier),
    INDEX idx_rl_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: install.sql so erweitern, dass Frisch-Installs die neuen Tabellen/Index enthalten**

Am Ende von `install.sql` die Tabellen `ppb_password_resets`, `ppb_rate_limits` anhaengen (ohne `IF NOT EXISTS`-Klauseln, da frisch), und bei `ppb_users` `UNIQUE KEY idx_users_username_unique (username)` ins Create integrieren.

- [ ] **Step 3: Migration gegen Docker-DB ausfuehren**

Run:
```bash
docker exec -i powerphpboard_db mysql -upowerphpboard -ppowerphpboard_secret powerphpboard < install_bugfix_2026-04-23.sql
```
Expected: Keine Fehler. Index und Tabellen vorhanden.

Wenn UNIQUE-Index fehlschlaegt wegen Duplicates: vorher bereinigen per:
```sql
UPDATE ppb_users SET username = CONCAT(username, '_dup_', id) WHERE id IN (SELECT id FROM (SELECT u1.id FROM ppb_users u1 JOIN ppb_users u2 ON u1.username = u2.username AND u1.id > u2.id) tmp);
```

- [ ] **Step 4: Commit**

```bash
git add install.sql install_bugfix_2026-04-23.sql
git commit -m "feat(db): Migration fuer Username-UNIQUE, Password-Reset-Tokens und Rate-Limits"
```

---

## Task 2: Validator-Klasse

**Files:**
- Create: `includes/Validator.php`
- Create: `tests/Unit/ValidatorTest.php`

**Why:** Wiederverwendbare Input-Validierung fuer Username, Laengen, etc. (BUG-004, BUG-005, BUG-015).

- [ ] **Step 1: Test schreiben**

Create `tests/Unit/ValidatorTest.php`:
```php
<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Validator;

final class ValidatorTest extends TestCase
{
    public function testUsernameAcceptsAlphanumeric(): void
    {
        $this->assertTrue(Validator::isValidUsername('testuser_01'));
        $this->assertTrue(Validator::isValidUsername('Alice.Bob'));
        $this->assertTrue(Validator::isValidUsername('user-name'));
    }

    public function testUsernameRejectsTagsAndSpecialChars(): void
    {
        $this->assertFalse(Validator::isValidUsername('<script>alert(1)</script>'));
        $this->assertFalse(Validator::isValidUsername('user@example'));
        $this->assertFalse(Validator::isValidUsername('user space'));
        $this->assertFalse(Validator::isValidUsername(''));
        $this->assertFalse(Validator::isValidUsername('a'));
        $this->assertFalse(Validator::isValidUsername(str_repeat('x', 51)));
    }

    public function testMaxLengthRespectsUtf8(): void
    {
        $this->assertTrue(Validator::withinLength('Hallo', 5));
        $this->assertFalse(Validator::withinLength('Hallo', 4));
        $this->assertTrue(Validator::withinLength('aeoeue', 6));
    }

    public function testPasswordRulesRequireMinEight(): void
    {
        $this->assertFalse(Validator::isStrongPassword('short'));
        $this->assertFalse(Validator::isStrongPassword('1234567'));
        $this->assertTrue(Validator::isStrongPassword('Password1'));
    }
}
```

- [ ] **Step 2: Test ausfuehren (muss fehlschlagen)**

Run: `vendor/bin/phpunit --filter ValidatorTest`
Expected: FAIL mit `Class "PowerPHPBoard\Validator" not found`.

- [ ] **Step 3: Validator implementieren**

Create `includes/Validator.php`:
```php
<?php

declare(strict_types=1);

namespace PowerPHPBoard;

final class Validator
{
    public const USERNAME_MIN = 2;
    public const USERNAME_MAX = 50;
    public const PASSWORD_MIN = 8;
    public const POST_MAX = 65000;
    public const BIOGRAPHY_MAX = 1000;
    public const SIGNATURE_MAX = 500;
    public const HOMEPAGE_MAX = 150;

    public static function isValidUsername(string $username): bool
    {
        if (! self::withinLength($username, self::USERNAME_MAX)) {
            return false;
        }
        if (mb_strlen($username) < self::USERNAME_MIN) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9._-]+$/', $username) === 1;
    }

    public static function withinLength(string $value, int $max): bool
    {
        return mb_strlen($value) <= $max;
    }

    public static function isStrongPassword(string $password): bool
    {
        return mb_strlen($password) >= self::PASSWORD_MIN;
    }
}
```

- [ ] **Step 4: Tests laufen lassen**

Run: `vendor/bin/phpunit --filter ValidatorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/Validator.php tests/Unit/ValidatorTest.php
git commit -m "feat(validator): Validator-Klasse fuer Username-, Laengen- und Passwortregeln"
```

---

## Task 3: RateLimiter-Klasse

**Files:**
- Create: `includes/RateLimiter.php`
- Create: `tests/Unit/RateLimiterTest.php`

**Why:** Zentrale Rate-Limiting-Logik fuer Login (BUG-007) und Passwort-Reset (BUG-018). Nutzt die `ppb_rate_limits`-Tabelle.

- [ ] **Step 1: Test schreiben** (fokussiert auf reine Logik mit Mock-DB-Interface)

Create `tests/Unit/RateLimiterTest.php`:
```php
<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PowerPHPBoard\RateLimiter;
use PowerPHPBoard\RateLimiterStorage;

final class InMemoryRateLimitStorage implements RateLimiterStorage
{
    /** @var array<string, array{attempts:int, window_start:int, locked_until:int}> */
    private array $data = [];

    public function getState(string $action, string $identifier, int $now): array
    {
        $key = $action . '|' . $identifier;
        return $this->data[$key] ?? ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0];
    }

    public function saveState(string $action, string $identifier, array $state): void
    {
        $this->data[$action . '|' . $identifier] = $state;
    }
}

final class RateLimiterTest extends TestCase
{
    public function testAllowsWithinLimit(): void
    {
        $storage = new InMemoryRateLimitStorage();
        $rl = new RateLimiter($storage, maxAttempts: 5, windowSeconds: 60, lockSeconds: 300);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($rl->check('login', 'ip:1.2.3.4'), "Attempt $i should pass");
            $rl->recordFailure('login', 'ip:1.2.3.4');
        }
        $this->assertFalse($rl->check('login', 'ip:1.2.3.4'), 'Attempt 6 must be blocked');
    }

    public function testResetOnSuccess(): void
    {
        $storage = new InMemoryRateLimitStorage();
        $rl = new RateLimiter($storage, maxAttempts: 3, windowSeconds: 60, lockSeconds: 300);

        $rl->recordFailure('login', 'u');
        $rl->recordFailure('login', 'u');
        $rl->recordSuccess('login', 'u');
        $this->assertTrue($rl->check('login', 'u'));
    }

    public function testWindowExpirationResetsCounter(): void
    {
        $storage = new InMemoryRateLimitStorage();
        $rl = new RateLimiter($storage, maxAttempts: 2, windowSeconds: 1, lockSeconds: 10, now: fn () => 1000);
        $rl->recordFailure('login', 'u');
        $rl->recordFailure('login', 'u');
        $rl2 = new RateLimiter($storage, maxAttempts: 2, windowSeconds: 1, lockSeconds: 10, now: fn () => 1005);
        $this->assertTrue($rl2->check('login', 'u'));
    }
}
```

- [ ] **Step 2: Test ausfuehren (fail expected)**

Run: `vendor/bin/phpunit --filter RateLimiterTest`
Expected: FAIL (class not found).

- [ ] **Step 3: RateLimiter implementieren**

Create `includes/RateLimiter.php`:
```php
<?php

declare(strict_types=1);

namespace PowerPHPBoard;

use PDO;

interface RateLimiterStorage
{
    /**
     * @return array{attempts:int, window_start:int, locked_until:int}
     */
    public function getState(string $action, string $identifier, int $now): array;

    /**
     * @param array{attempts:int, window_start:int, locked_until:int} $state
     */
    public function saveState(string $action, string $identifier, array $state): void;
}

final class DatabaseRateLimitStorage implements RateLimiterStorage
{
    public function __construct(private Database $db)
    {
    }

    public function getState(string $action, string $identifier, int $now): array
    {
        $row = $this->db->fetchOne(
            'SELECT attempts, window_start, locked_until FROM ppb_rate_limits WHERE action = ? AND identifier = ?',
            [$action, $identifier]
        );
        if ($row === null) {
            return ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0];
        }
        return [
            'attempts' => (int) $row['attempts'],
            'window_start' => (int) $row['window_start'],
            'locked_until' => (int) $row['locked_until'],
        ];
    }

    public function saveState(string $action, string $identifier, array $state): void
    {
        $this->db->query(
            'INSERT INTO ppb_rate_limits (action, identifier, attempts, window_start, locked_until) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), window_start = VALUES(window_start), locked_until = VALUES(locked_until)',
            [$action, $identifier, $state['attempts'], $state['window_start'], $state['locked_until']]
        );
    }
}

final class RateLimiter
{
    /** @var callable(): int */
    private $nowFn;

    public function __construct(
        private RateLimiterStorage $storage,
        private int $maxAttempts = 10,
        private int $windowSeconds = 900,
        private int $lockSeconds = 900,
        ?callable $now = null,
    ) {
        $this->nowFn = $now ?? static fn (): int => time();
    }

    public function check(string $action, string $identifier): bool
    {
        $now = ($this->nowFn)();
        $state = $this->storage->getState($action, $identifier, $now);
        if ($state['locked_until'] > $now) {
            return false;
        }
        if ($now - $state['window_start'] > $this->windowSeconds) {
            return true;
        }
        return $state['attempts'] < $this->maxAttempts;
    }

    public function recordFailure(string $action, string $identifier): void
    {
        $now = ($this->nowFn)();
        $state = $this->storage->getState($action, $identifier, $now);
        if ($now - $state['window_start'] > $this->windowSeconds) {
            $state = ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0];
        }
        $state['attempts']++;
        if ($state['attempts'] >= $this->maxAttempts) {
            $state['locked_until'] = $now + $this->lockSeconds;
        }
        $this->storage->saveState($action, $identifier, $state);
    }

    public function recordSuccess(string $action, string $identifier): void
    {
        $now = ($this->nowFn)();
        $this->storage->saveState($action, $identifier, ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0]);
    }
}
```

- [ ] **Step 4: Tests laufen lassen**

Run: `vendor/bin/phpunit --filter RateLimiterTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/RateLimiter.php tests/Unit/RateLimiterTest.php
git commit -m "feat(ratelimit): RateLimiter mit DB-Backend gegen Brute-Force"
```

---

## Task 4: Mailer-Klasse

**Files:**
- Create: `includes/Mailer.php`
- Create: `tests/Unit/MailerTest.php`

**Why:** `@mail()` schlaegt stumm fehl (BUG-002). Neuer Mailer nutzt SMTP direkt zu Mailpit (Docker-Netzwerk: `mailpit:1025`, Host: `localhost:1032`).

- [ ] **Step 1: Test fuer Mailer-Builder schreiben**

Create `tests/Unit/MailerTest.php`:
```php
<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Mailer;

final class MailerTest extends TestCase
{
    public function testBuildsRfc822Message(): void
    {
        $msg = Mailer::buildMessage(
            to: 'alice@example.com',
            from: 'board@example.com',
            subject: 'Test "Subject"',
            body: "Hallo\r\nZeile 2"
        );

        $this->assertStringContainsString("From: board@example.com\r\n", $msg);
        $this->assertStringContainsString("To: alice@example.com\r\n", $msg);
        $this->assertStringContainsString("Subject: =?UTF-8?B?", $msg);
        $this->assertStringContainsString("MIME-Version: 1.0\r\n", $msg);
        $this->assertStringContainsString("Content-Type: text/plain; charset=UTF-8\r\n", $msg);
        $this->assertStringEndsWith("Hallo\r\nZeile 2\r\n", $msg);
    }

    public function testRejectsInvalidRecipient(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Mailer::buildMessage(to: 'not-an-email', from: 'a@b.c', subject: 'x', body: 'y');
    }
}
```

- [ ] **Step 2: Test ausfuehren (fail)**

Run: `vendor/bin/phpunit --filter MailerTest`
Expected: FAIL.

- [ ] **Step 3: Mailer implementieren**

Create `includes/Mailer.php`:
```php
<?php

declare(strict_types=1);

namespace PowerPHPBoard;

use InvalidArgumentException;
use RuntimeException;

final class Mailer
{
    public function __construct(
        private string $smtpHost = 'mailpit',
        private int $smtpPort = 1025,
        private int $timeoutSeconds = 5,
    ) {
    }

    public function send(string $to, string $from, string $subject, string $body): bool
    {
        if (!Security::isValidEmail($to) || !Security::isValidEmail($from)) {
            return false;
        }

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->smtpHost, $this->smtpPort),
            $errno,
            $errstr,
            $this->timeoutSeconds
        );
        if ($socket === false) {
            error_log(sprintf('[Mailer] SMTP connect failed: %s (%d)', $errstr, $errno));
            return false;
        }

        try {
            $this->expect($socket, '220');
            $this->cmd($socket, 'HELO powerphpboard', '250');
            $this->cmd($socket, 'MAIL FROM:<' . $from . '>', '250');
            $this->cmd($socket, 'RCPT TO:<' . $to . '>', '250');
            $this->cmd($socket, 'DATA', '354');
            $msg = self::buildMessage($to, $from, $subject, $body) . ".\r\n";
            fwrite($socket, $msg);
            $this->expect($socket, '250');
            $this->cmd($socket, 'QUIT', '221');
            return true;
        } catch (RuntimeException $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return false;
        } finally {
            fclose($socket);
        }
    }

    public static function buildMessage(string $to, string $from, string $subject, string $body): string
    {
        if (!Security::isValidEmail($to)) {
            throw new InvalidArgumentException('Invalid recipient');
        }
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $normalizedBody = preg_replace("/\r\n|\r|\n/", "\r\n", $body);
        $headers = [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Date: ' . date(DATE_RFC2822),
        ];
        return implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody . "\r\n";
    }

    /**
     * @param resource $socket
     */
    private function cmd($socket, string $cmd, string $expect): void
    {
        fwrite($socket, $cmd . "\r\n");
        $this->expect($socket, $expect);
    }

    /**
     * @param resource $socket
     */
    private function expect($socket, string $expected): void
    {
        $line = fgets($socket, 1024);
        if ($line === false || !str_starts_with((string) $line, $expected)) {
            throw new RuntimeException('SMTP expected ' . $expected . ', got ' . (string) $line);
        }
        // Swallow multi-line replies
        while (preg_match('/^' . preg_quote($expected, '/') . '-/', (string) $line) === 1) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SMTP unexpected close');
            }
        }
    }
}
```

- [ ] **Step 4: Test ausfuehren (pass erwartet)**

Run: `vendor/bin/phpunit --filter MailerTest`
Expected: PASS.

- [ ] **Step 5: Konfig-Umgebungsvariablen dokumentieren**

Modify `config.inc.php` (ans Ende vor der letzten `?>`-Zeile, falls vorhanden, sonst einfach am Ende der Datei):

```php
// Mail configuration (Mailpit fuer lokale Dev; produktiv SMTP-Credentials setzen)
$mail = [
    'host' => getenv('PPB_MAIL_HOST') ?: 'mailpit',
    'port' => (int) (getenv('PPB_MAIL_PORT') ?: 1025),
    'from' => getenv('PPB_MAIL_FROM') ?: 'noreply@powerphpboard.local',
];
```

- [ ] **Step 6: Commit**

```bash
git add includes/Mailer.php tests/Unit/MailerTest.php config.inc.php
git commit -m "feat(mailer): Mailer-Klasse mit SMTP-Direktanbindung (Mailpit-fuer-Dev)"
```

---

## Task 5: Fix register.php (BUG-001, BUG-003, BUG-004, BUG-005 + Mailer)

**Files:**
- Modify: `register.php`

- [ ] **Step 1: Aenderungen an register.php**

Ersetze in `register.php`:

Nach dem `use`-Block hinzufuegen:
```php
use PowerPHPBoard\Validator;
use PowerPHPBoard\Mailer;
```

Zeile 22 aendern:
```php
$acception = Security::getInt('acception', 'REQUEST');
$register = Security::getInt('register', 'POST');
```

Nach `$username = Security::getString('username', 'POST');` (nach Zeile 60) fuege neue Validierungen zwischen die bestehenden Checks ein. Die bestehende Validierungskette (`if ($username === '' ...)`) bleibt, ergaenze diese Bloecke NACH der Passwort-Laengen-Pruefung (nach Zeile 118) und VOR der ICQ-Pruefung:

```php
            } elseif (!Validator::isValidUsername($username)) {
                default_error(
                    $lang_usernameinvalid ?? 'Username must be 2-50 chars and only contain letters, digits, . _ -',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif (!Validator::isStrongPassword($password1)) {
                default_error(
                    $lang_pwdtooshort ?? 'Password must be at least 8 characters',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif (!Validator::withinLength($biography, Validator::BIOGRAPHY_MAX)
                || !Validator::withinLength($signature, Validator::SIGNATURE_MAX)
                || !Validator::withinLength($homepage, Validator::HOMEPAGE_MAX)) {
                default_error(
                    $lang_inputstoolong ?? 'One or more fields exceed the allowed length',
                    'javascript:history.back()',
                    $lang_backtoregform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
```

Die bestehende `strlen($password1) < 6`-Pruefung durch Validator-Call ersetzen: den alten `elseif (strlen($password1) < 6)`-Block komplett entfernen (die neue `isStrongPassword`-Pruefung deckt das mit 8 Zeichen ab).

Username-Duplicate-Check nach Email-Check einfuegen (nach dem bestehenden `$existing = $db->fetchOne('SELECT id FROM ppb_users WHERE email = ?', ...)`-Block, ergaenze zweiten Check):

```php
                // Check username uniqueness (BUG-003)
                $existingUsername = $db->fetchOne('SELECT id FROM ppb_users WHERE username = ?', [$username]);
                if ($existingUsername !== null) {
                    default_error(
                        $lang_usernametaken ?? 'This username is already taken',
                        'javascript:history.back()',
                        $lang_backtoregform ?? 'Back',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                    goto end_registration;
                }
```

Die Zeile 154 `$username = strip_tags($username);` ENTFERNEN - ueberfluessig nach Validator-Check.

Die Zeile 182 `@mail($email1, $subject, $message, $headers);` ersetzen durch:
```php
                        require_once __DIR__ . '/config.inc.php';
                        global $mail;
                        $mailer = new Mailer($mail['host'], $mail['port']);
                        $mailer->send($email1, $settings['adminemail'] ?? ($mail['from'] ?? 'noreply@powerphpboard.local'), $subject, $message);
```

HTML-Hinweis "Password minlength" fuer Formular aktualisieren - Zeile 245, `minlength="6"` zu `minlength="8"` aendern (zwei Stellen: password1 und password2 Felder).

Ebenso die Formular-Action auf `register.php?acception=1` setzen (Zeile 218) - redundant, aber sauberer:
```php
        <form action="register.php?acception=1" method="post">
```

- [ ] **Step 2: Manuell pruefen**

Run:
```bash
curl -s -c /tmp/c.txt "http://localhost:8085/register.php?acception=1" > /tmp/form.html
# Hole CSRF aus /tmp/form.html, dann POST direkt gegen register.php
```

Expected: Registrierung gelingt ohne Query-String-Trick, Username-Duplikate werden abgelehnt.

- [ ] **Step 3: Commit**

```bash
git add register.php
git commit -m "fix(register): BUG-001 acception via REQUEST, BUG-003 Username-Unique, BUG-004 Laengen, BUG-005 Username-Regex, BUG-002 Mailer"
```

---

## Task 6: Fix Signature-XSS (BUG-010)

**Files:**
- Modify: `register.php`
- Modify: `profile.php`
- Modify: `showthread.php`

**Strategy:** Signaturen werden sowohl beim Speichern als auch beim Rendern behandelt. Rendern mit erzwungenem `htmlcode=OFF` fuer Signaturen verhindert XSS unabhaengig von Nutzerinput.

- [ ] **Step 1: register.php - Signatur beim Save sanitieren**

In `register.php` den INSERT-Block vor `$db->query("INSERT INTO ppb_users ..."`:

```php
                    // BUG-010: Signature mit whitelist
                    $allowedTagsSig = '<b><i><u><strong><em><br><a>';
                    $signature = strip_tags($signature, $allowedTagsSig);
                    $biography = strip_tags($biography); // bereits oben, aber zur Sicherheit
```

- [ ] **Step 2: profile.php - Signatur beim Save sanitieren**

In `profile.php` Zeile ~193 (im UPDATE-Call) den `$signature` Parameter durch sanitierten Wert ersetzen:

Vor dem `$db->query('UPDATE ppb_users SET username = ?...`-Block ergaenzen:
```php
                    $allowedTagsSig = '<b><i><u><strong><em><br><a>';
                    $signature = strip_tags($signature, $allowedTagsSig);
```

- [ ] **Step 3: showthread.php - Signature-Rendering zwingt htmlcode=OFF**

In `showthread.php` Zeile 314 die Signatur-Rendering-Zeile anpassen - `htmlcode` hart auf `'OFF'` setzen:

```php
                $signature = TextFormatter::formatPost($author['signature'], $settings['bbcode'] ?? 'ON', $settings['smilies'] ?? 'ON', 'OFF');
```

- [ ] **Step 4: Manueller XSS-Test**

Gehe ins Browserfenster, oeffne Profil und setze Signatur auf `<script>alert('XSS')</script><b>bold</b>`. Speichere Profil. Oeffne einen Thread mit Post dieses Nutzers.
Expected: `<script>` erscheint escaped im HTML (`&lt;script&gt;`), `<b>bold</b>` wird (dank Whitelist-strip_tags) als `<b>` gespeichert und via `formatPost` mit htmlcode=OFF zu `&lt;b&gt;bold&lt;/b&gt;` escaped angezeigt. Ergebnis: KEIN Script-Aufruf.

- [ ] **Step 5: Commit**

```bash
git add register.php profile.php showthread.php
git commit -m "fix(security): BUG-010 Signature-XSS - Whitelist-strip_tags + force htmlcode=OFF in Rendering"
```

---

## Task 7: Fix Login (BUG-006, BUG-007, BUG-009)

**Files:**
- Modify: `login.php`
- Modify: `english.inc.php`
- Modify: `deutsch-sie.inc.php`
- Modify: `deutsch-du.inc.php`

- [ ] **Step 1: Login-Meldungen vereinheitlichen und RateLimit einbauen**

In `login.php` im `use`-Block hinzufuegen:
```php
use PowerPHPBoard\DatabaseRateLimitStorage;
use PowerPHPBoard\RateLimiter;
```

Nach `$loginerror = '';` (Zeile 47) ergaenzen:
```php
$rateLimiter = new RateLimiter(
    new DatabaseRateLimitStorage($db),
    maxAttempts: 10,
    windowSeconds: 900,
    lockSeconds: 900
);
$rateLimitIdentifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
```

Innerhalb `if ($_SERVER['REQUEST_METHOD'] === 'POST' && $login === 1)` vor der CSRF-Pruefung:
```php
    if (!$rateLimiter->check('login', $rateLimitIdentifier)) {
        $loginerror = $lang_toomanyattempts ?? 'Too many login attempts. Please try again later.';
        goto endLoginProcessing;
    }
```

Am Ende der `else`-Branch nach `CSRF::regenerate();`:
```php
                        $rateLimiter->recordSuccess('login', $rateLimitIdentifier);
```

Nach jedem `$loginerror = ...` im Fehlerpfad (nach CSRF-OK):
- Bei `'No user with email'`, `'invalid password'` und `'no cookie login'` Cases → danach `$rateLimiter->recordFailure('login', $rateLimitIdentifier);` ergaenzen.

**Meldungen vereinheitlichen (BUG-006):**

Die drei Error-Cases
- `$loginerror = $lang_nouserwithemail ?? ...;`
- `$loginerror = $lang_pwdnotcorrect ?? ...;`
- (optional: `$loginerror = $lang_nocookielogin ?? ...;`)

werden alle durch eine einheitliche Meldung ersetzt:
```php
                $loginerror = $lang_loginfailed ?? 'Invalid email or password.';
```

ErrorHandler-Calls bleiben erhalten (die ermoeglichen serverseitige Analyse).

Am Schluss Label einbauen:
```php
endLoginProcessing:
```

Zwischen `} else { ... }` (dem Error-/CSRF-Block) und `}` (dem finalen if-POST-Ende) muss `endLoginProcessing:;` direkt vor dem schliessenden `}` des `if ($_SERVER['REQUEST_METHOD'] === 'POST' && $login === 1)` Blocks stehen.

- [ ] **Step 2: Legacy-Text "360 days" entfernen (BUG-009)**

Edit `english.inc.php:71`:
```php
$lang_loginok = 'Your login was successful. Session active.';
```
Edit `deutsch-sie.inc.php:68`:
```php
$lang_loginok = 'Sie haben sich erfolgreich eingeloggt. Die Session ist aktiv.';
```
Edit `deutsch-du.inc.php:68`:
```php
$lang_loginok = 'Du hast Dich erfolgreich eingeloggt. Die Session ist aktiv.';
```

- [ ] **Step 3: Fuegen neue Sprachkeys in alle drei Sprachdateien ein**

Ans Ende (vor dem eventuell bestehenden `?>`) ergaenzen:
```php
$lang_loginfailed = 'Invalid email or password.';
$lang_toomanyattempts = 'Too many attempts. Please try again later.';
$lang_usernameinvalid = 'Username must be 2-50 chars and contain only letters, digits and . _ -';
$lang_usernametaken = 'This username is already taken.';
$lang_inputstoolong = 'One or more fields exceed the allowed length.';
$lang_pwdtooshort = 'Password must be at least 8 characters.';
$lang_currentpasswordwrong = 'Current password is not correct.';
$lang_pwdresetlinksent = 'If the email is registered, a reset link has been sent.';
$lang_pwdresetsuccess = 'Password has been reset. You can now log in.';
$lang_pwdresettokeninvalid = 'Invalid or expired reset link.';
```

In deutsch-sie.inc.php / deutsch-du.inc.php entsprechende Texte (z. B. "Anmeldung fehlgeschlagen. Email oder Passwort falsch." bzw. "Du-Variante"). Exakte Texte:

deutsch-sie:
```php
$lang_loginfailed = 'Anmeldung fehlgeschlagen. E-Mail oder Passwort sind nicht korrekt.';
$lang_toomanyattempts = 'Zu viele Versuche. Bitte warten Sie, bevor Sie es erneut versuchen.';
$lang_usernameinvalid = 'Benutzername muss 2-50 Zeichen haben und nur Buchstaben, Ziffern und . _ - enthalten.';
$lang_usernametaken = 'Dieser Benutzername ist bereits vergeben.';
$lang_inputstoolong = 'Ein oder mehrere Felder ueberschreiten die maximale Laenge.';
$lang_pwdtooshort = 'Passwort muss mindestens 8 Zeichen haben.';
$lang_currentpasswordwrong = 'Das aktuelle Passwort ist nicht korrekt.';
$lang_pwdresetlinksent = 'Falls die E-Mail registriert ist, wurde ein Reset-Link gesendet.';
$lang_pwdresetsuccess = 'Das Passwort wurde zurueckgesetzt. Sie koennen sich jetzt anmelden.';
$lang_pwdresettokeninvalid = 'Ungueltiger oder abgelaufener Reset-Link.';
```

deutsch-du:
```php
$lang_loginfailed = 'Anmeldung fehlgeschlagen. E-Mail oder Passwort sind nicht korrekt.';
$lang_toomanyattempts = 'Zu viele Versuche. Bitte warte, bevor Du es erneut versuchst.';
$lang_usernameinvalid = 'Benutzername muss 2-50 Zeichen haben und nur Buchstaben, Ziffern und . _ - enthalten.';
$lang_usernametaken = 'Dieser Benutzername ist bereits vergeben.';
$lang_inputstoolong = 'Ein oder mehrere Felder ueberschreiten die maximale Laenge.';
$lang_pwdtooshort = 'Passwort muss mindestens 8 Zeichen haben.';
$lang_currentpasswordwrong = 'Das aktuelle Passwort ist nicht korrekt.';
$lang_pwdresetlinksent = 'Falls die E-Mail registriert ist, wurde ein Reset-Link gesendet.';
$lang_pwdresetsuccess = 'Das Passwort wurde zurueckgesetzt. Du kannst Dich jetzt anmelden.';
$lang_pwdresettokeninvalid = 'Ungueltiger oder abgelaufener Reset-Link.';
```

- [ ] **Step 4: Test manuell**

Brute-Force-Test:
```bash
for i in $(seq 1 12); do
  curl -s -c /tmp/c.txt -b /tmp/c.txt -X POST "http://localhost:8085/login.php" \
    -d "email=foo@bar&password=wrong$i&login=1&csrf_token=xxx" | grep -oE "Too many|Invalid email" | head -1
done
```
Expected: Nach 10 Versuchen "Too many attempts".

- [ ] **Step 5: Commit**

```bash
git add login.php english.inc.php deutsch-sie.inc.php deutsch-du.inc.php
git commit -m "fix(login): BUG-006 unified message, BUG-007 rate-limit, BUG-009 legacy cookie text"
```

---

## Task 8: Fix Logout (BUG-008)

**Files:**
- Modify: `logout.php`
- Modify: `header.inc.php` (Logout-Link auf POST umstellen)

- [ ] **Step 1: logout.php - GET zu POST mit CSRF**

Ersetze den GET-Logout-Path. Der GET-Fall zeigt nur das Bestaetigungsformular; Logout wird nur per POST mit CSRF-Token durchgefuehrt.

Ersetze `logout.php`-Inhalt ab Zeile 13 (nach dem `use`):

```php
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/config.inc.php';

Session::start();

$logout = $_SERVER['REQUEST_METHOD'] === 'POST' ? Security::getInt('logout', 'POST') : 0;

if ($logout === 1) {
    if (!CSRF::validateFromPost()) {
        http_response_code(400);
        include __DIR__ . '/header.inc.php';
        echo '<table border="0" cellpadding="2" cellspacing="1" width="100%">
        <tr><td>Security token invalid.</td></tr></table>';
        include __DIR__ . '/footer.inc.php';
        exit;
    }
    Session::logout();
}

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');

include __DIR__ . '/header.inc.php';
?>
<table border="0" cellpadding="2" cellspacing="1" width="100%">
<?php if ($logout === 1): ?>
    <tr><td bgcolor="<?= Security::escape($settings['tablebg3'] ?? '#cccccc') ?>">
      <b><?= $lang_statusmessage ?? 'Status' ?></b>
    </td></tr>
    <tr><td bgcolor="<?= Security::escape($settings['tablebg2'] ?? '#eeeeee') ?>"><br>
      <?= $lang_logoutok ?? 'Logout successful!' ?><br><br>
    </td></tr>
    <tr><td bgcolor="<?= Security::escape($settings['tablebg1'] ?? '#ffffff') ?>" align="center">
      <a href="index.php">Home</a>
    </td></tr>
<?php else: ?>
    <form action="logout.php" method="post">
      <?= CSRF::getTokenField() ?>
      <input type="hidden" name="logout" value="1">
      <input type="hidden" name="catid" value="<?= (int) $catid ?>">
      <input type="hidden" name="boardid" value="<?= (int) $boardid ?>">
      <tr><td bgcolor="<?= Security::escape($settings['tablebg3'] ?? '#cccccc') ?>">
        <b><?= $lang_logout ?? 'Logout' ?></b>
      </td></tr>
      <tr><td bgcolor="<?= Security::escape($settings['tablebg2'] ?? '#eeeeee') ?>"><br>
        <?= $lang_reallylogout ?? 'Do you really want to logout?' ?><br><br>
      </td></tr>
      <tr><td bgcolor="<?= Security::escape($settings['tablebg1'] ?? '#ffffff') ?>" align="center">
        <button type="submit" name="logout" value="1"><?= $lang_yeslogout ?? 'Yes, logout' ?></button>
        &nbsp;|&nbsp;
        <a href="index.php"><?= $lang_nologout ?? 'No, stay logged in' ?></a>
      </td></tr>
    </form>
<?php endif; ?>
</table>
<?php include __DIR__ . '/footer.inc.php'; ?>
```

- [ ] **Step 2: header.inc.php - Logout-Link zeigt Bestaetigung statt direkt**

In `header.inc.php` den Logout-Link im Menu: der fuehrt bereits zu `logout.php` (ohne `?logout=1`) → zeigt Formular. Falls der Link noch `?logout=1` haette, entfernen. Standardmaessig sollte `logout.php` die Bestaetigungsseite zeigen.

Pruefen mit:
```bash
grep -n "logout.php" header.inc.php
```
Falls `?logout=1` drin ist, den Query-String entfernen.

- [ ] **Step 3: Test**
```bash
curl -i "http://localhost:8085/logout.php?logout=1"  # GET → sollte Formular zeigen, nicht ausloggen
curl -i -X POST "http://localhost:8085/logout.php" -d "logout=1"  # POST ohne CSRF → Fehler
```

- [ ] **Step 4: Commit**

```bash
git add logout.php header.inc.php
git commit -m "fix(logout): BUG-008 Logout nur per POST mit CSRF"
```

---

## Task 9: Fix Profil-Edit (BUG-011, BUG-012, BUG-013)

**Files:**
- Modify: `profile.php`

- [ ] **Step 1: Passwort optional, Re-Auth, ICQ-Default**

In `profile.php` ersetze den Validierungsblock (Zeilen ~107-161):

Neuer Check:
```php
            $currentPassword = Security::getString('current_password', 'POST');
            $newPassword = $password1;  // alias for clarity

            if ($username === '' || $email1 === '' || $email2 === '') {
                default_error(
                    $lang_insertvaluesforall ?? 'Please fill in all required fields',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
                goto end_profile_edit;
            }

            $passwordWillChange = $newPassword !== '' || $password2 !== '';
            $emailWillChange = $email1 !== $user['email'];
            $sensitiveChange = $passwordWillChange || $emailWillChange;

            // Re-Auth fuer sensible Aenderungen (BUG-012)
            if ($sensitiveChange) {
                if ($currentPassword === '' || !Security::verifyPassword($currentPassword, $user['password'])) {
                    default_error(
                        $lang_currentpasswordwrong ?? 'Current password is not correct',
                        'javascript:history.back()',
                        $lang_backtoprofileform ?? 'Back',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                    goto end_profile_edit;
                }
            }

            if ($email1 !== $email2) {
                default_error(
                    $lang_emailsdifferent ?? 'Email addresses do not match',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
                goto end_profile_edit;
            }

            if (!Security::isValidEmail($email1)) {
                default_error(
                    $lang_emailnotcorrect ?? 'Invalid email address',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
                goto end_profile_edit;
            }

            if ($passwordWillChange) {
                if ($newPassword !== $password2) {
                    default_error(
                        $lang_pwdsdifferent ?? 'Passwords do not match',
                        'javascript:history.back()',
                        $lang_backtoprofileform ?? 'Back',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                    goto end_profile_edit;
                }
                if (!\PowerPHPBoard\Validator::isStrongPassword($newPassword)) {
                    default_error(
                        $lang_pwdtooshort ?? 'Password must be at least 8 characters',
                        'javascript:history.back()',
                        $lang_backtoprofileform ?? 'Back',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                    goto end_profile_edit;
                }
            }

            if (!\PowerPHPBoard\Validator::isValidUsername($username)) {
                default_error(
                    $lang_usernameinvalid ?? 'Username invalid',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
                goto end_profile_edit;
            }

            if ($icq !== '' && !ctype_digit($icq)) {
                default_error(
                    $lang_icqnotcorrect ?? 'ICQ number must be numeric',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
                goto end_profile_edit;
            }

            // Username-Unique check (BUG-003 auch im Profil)
            $existingByUsername = $db->fetchOne(
                'SELECT id FROM ppb_users WHERE username = ? AND id != ?',
                [$username, $user['id']]
            );
            if ($existingByUsername !== null) {
                default_error(
                    $lang_usernametaken ?? 'This username is already taken',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
                goto end_profile_edit;
            }

            // Email-Unique check (bestehend, bleibt)
            $existingUser = $db->fetchOne(
                'SELECT id FROM ppb_users WHERE email = ? AND id != ?',
                [$email1, $user['id']]
            );
            if ($existingUser !== null) {
                default_error(
                    $lang_emailalreadyexists ?? 'Email already exists',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
                goto end_profile_edit;
            }

            // Signature whitelist-sanitize (BUG-010)
            $allowedTagsSig = '<b><i><u><strong><em><br><a>';
            $signature = strip_tags($signature, $allowedTagsSig);

            // Compute final password hash
            $finalHash = $passwordWillChange ? Security::hashPassword($newPassword) : $user['password'];

            try {
                $db->query(
                    'UPDATE ppb_users SET username = ?, email = ?, password = ?, homepage = ?, icq = ?, biography = ?, signature = ?, hideemail = ?, logincookie = ? WHERE id = ?',
                    [
                        $username,
                        $email1,
                        $finalHash,
                        $homepage,
                        $icq,
                        strip_tags($biography),
                        $signature,
                        $hideemail === 'YES' ? 'YES' : 'NO',
                        $logincookie === 'YES' ? 'YES' : 'NO',
                        $user['id'],
                    ]
                );
            } catch (PDOException) {
                default_error(
                    $lang_errorwhileupdprofile ?? 'Error updating profile',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
                goto end_profile_edit;
            }

            CSRF::regenerate();
            echo '
            <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
            <b>' . ($lang_statusmessage ?? 'Status') . '</b>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
            ' . ($lang_changedprofilesuccessfull ?? 'Profile updated successfully') . '<br><br>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
            <a href="index.php">Home</a>
            </td></tr>
            ';

            end_profile_edit:;
```

Die `username = strip_tags($username)`-Zeile in den DB-Parametern entfernen (oben durch Validator-Pruefung abgesichert).

- [ ] **Step 2: Formular anpassen - Passwort optional, ICQ-Default fix, current_password Feld**

Im HTML-Formular (ab Zeile ~234) die Passwortfelder:
- `required` entfernen
- `minlength="6"` zu `minlength="8"` oder weg (Server validiert).
- Neues Feld `current_password` oben im Formular einfuegen, nur Pflicht wenn Email/Passwort geaendert - visuell aber immer anzeigen mit Hinweis.

ICQ-Default: Ersetze
```php
<input name="icq" size="10" maxlength="10" value="' . Security::escape($user['icq'] ?? '') . '">
```
durch
```php
<input name="icq" size="10" maxlength="10" value="' . Security::escape(($user['icq'] ?? '') === '0' ? '' : ($user['icq'] ?? '')) . '">
```

Passwortfelder-Replacement:
```php
        <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_currentpassword ?? 'Current password (for sensitive changes)') . '</b>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_currentpassword ?? 'Current Password') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="current_password" size="25" maxlength="255" type="password">
        <small>' . ($lang_currentpwdnote ?? 'Only required if you change email or password') . '</small>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_newpassword ?? 'New Password') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="password1" size="25" maxlength="255" type="password" minlength="8">
        <small>' . ($lang_leaveemptynochange ?? 'Leave empty to keep current password') . '</small>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_newpassword ?? 'New Password') . '</b> <small>(' . ($lang_confirmation ?? 'Confirmation') . ')</small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="password2" size="25" maxlength="255" type="password" minlength="8">
        </td></tr>
```

(Die alte doppelte Passwort-Sektion wird dadurch ersetzt.)

- [ ] **Step 3: Neue Lang-Strings**

In allen drei Sprachdateien:
```php
$lang_currentpassword = 'Current password';      // english
$lang_newpassword = 'New password';
$lang_currentpwdnote = 'Only required if you change email or password';
$lang_leaveemptynochange = 'Leave empty to keep current password';
```
(deutsch-sie/du: uebersetzen mit Sie/Du-Form)

- [ ] **Step 4: Manueller Test**

Browser: Profil aufrufen, nur Biography aendern → soll ohne Passwort-Eingabe funktionieren.
Profil aufrufen, Email aendern ohne current_password → Fehler.
Profil aufrufen, Email aendern MIT current_password → OK.

- [ ] **Step 5: Commit**

```bash
git add profile.php english.inc.php deutsch-sie.inc.php deutsch-du.inc.php
git commit -m "fix(profile): BUG-011 Password optional, BUG-012 Re-Auth, BUG-013 ICQ-Default, BUG-003 Username-Unique"
```

---

## Task 10: Fix Password-Reset (BUG-016, BUG-017, BUG-018)

**Files:**
- Modify: `sendpassword.php`
- Create: `resetpassword.php`

- [ ] **Step 1: sendpassword.php komplett ueberarbeiten**

Ersetze `sendpassword.php` ab Zeile 13 (nach `use`-Block) durch:

```php
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\DatabaseRateLimitStorage;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\RateLimiter;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

require_once __DIR__ . '/config.inc.php';
Session::start();

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];

$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$send = Security::getInt('send', 'REQUEST');

$rateLimiter = new RateLimiter(
    new DatabaseRateLimitStorage($db),
    maxAttempts: 5,
    windowSeconds: 3600,
    lockSeconds: 3600
);
$rlIdent = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $send === 1) {
    if (!CSRF::validateFromPost()) {
        default_error(
            'Security token invalid. Please try again.',
            'javascript:history.back()',
            $lang_backtosendpwdform ?? 'Back',
            $settings['tablebg3'] ?? '#cccccc',
            $settings['tablebg2'] ?? '#eeeeee',
            $settings['tablebg1'] ?? '#ffffff'
        );
    } elseif (!$rateLimiter->check('pwreset', $rlIdent)) {
        default_error(
            $lang_toomanyattempts ?? 'Too many attempts. Please try again later.',
            'index.php',
            'Home',
            $settings['tablebg3'] ?? '#cccccc',
            $settings['tablebg2'] ?? '#eeeeee',
            $settings['tablebg1'] ?? '#ffffff'
        );
    } else {
        $email = Security::getString('email', 'POST');
        $rateLimiter->recordFailure('pwreset', $rlIdent); // jede Anfrage zaehlt, unabhaengig davon ob User existiert

        $user = null;
        if ($email !== '' && Security::isValidEmail($email)) {
            $user = $db->fetchOne('SELECT * FROM ppb_users WHERE email = ?', [$email]);
        }

        if ($user !== null) {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $now = time();
            $expires = $now + 3600;

            // Invalidiere alte Tokens
            $db->query('UPDATE ppb_password_resets SET used_at = ? WHERE userid = ? AND used_at = 0', [$now, $user['id']]);
            $db->query(
                'INSERT INTO ppb_password_resets (userid, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
                [$user['id'], $tokenHash, $expires, $now]
            );

            $resetUrl = rtrim((string)($settings['boardurl'] ?? ''), '/') . '/resetpassword.php?token=' . $rawToken;
            if ($settings['boardurl'] === null || $settings['boardurl'] === '') {
                $scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $resetUrl = $scheme . '://' . $host . '/resetpassword.php?token=' . $rawToken;
            }

            $subject = ($settings['boardtitle'] ?? 'PowerPHPBoard') . ' - ' . ($lang_passwordreminder ?? 'Password Reset');
            $message = ($lang_hello ?? 'Hello') . ' ' . $user['username'] . ",\n\n"
                . ($lang_pwdresetclicklink ?? 'Click this link within one hour to reset your password:') . "\n\n"
                . $resetUrl . "\n\n"
                . ($lang_ifyoudidntrequestmail ?? 'If you did not request this, you can ignore this email.') . "\n";

            global $mail;
            $mailer = new Mailer($mail['host'] ?? 'mailpit', (int)($mail['port'] ?? 1025));
            $mailer->send($email, $settings['adminemail'] ?? ($mail['from'] ?? 'noreply@powerphpboard.local'), $subject, $message);
        }

        CSRF::regenerate();
        // Einheitliche Meldung, egal ob User existiert (BUG-017)
        echo '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_statusmessage ?? 'Status') . '</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
        ' . ($lang_pwdresetlinksent ?? 'If the email is registered, a reset link has been sent.') . '<br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
        <a href="index.php">Home</a>
        </td></tr>
        ';
    }
} else {
    echo '
    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
    <b>' . ($lang_sendpwd ?? 'Send Password') . '</b>
    </td></tr>
    <form action="sendpassword.php?send=1" method="post">
    ' . CSRF::getTokenField() . '
    <input type="hidden" name="send" value="1">
    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center"><br>
    ' . ($lang_email ?? 'Email') . ':<br>
    <br>
    <input name="email" size="25" maxlength="100" type="email" required>&nbsp;&nbsp;&nbsp;<input type="submit" value="' . ($lang_send ?? 'Send') . '"><br><br>
    </td></tr>
    </form>
    ';
}
?>
</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
```

- [ ] **Step 2: resetpassword.php anlegen**

Create `resetpassword.php`:
```php
<?php

declare(strict_types=1);

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use PowerPHPBoard\Validator;

require_once __DIR__ . '/config.inc.php';
Session::start();

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}
$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

$token = Security::getString('token', 'REQUEST');
$now = time();

$resetInvalid = function () use ($settings, $lang_pwdresettokeninvalid): void {
    default_error(
        $lang_pwdresettokeninvalid ?? 'Invalid or expired reset link.',
        'index.php',
        'Home',
        $settings['tablebg3'] ?? '#cccccc',
        $settings['tablebg2'] ?? '#eeeeee',
        $settings['tablebg1'] ?? '#ffffff'
    );
};

if ($token === '' || strlen($token) > 128) {
    include __DIR__ . '/header.inc.php';
    echo '<table border="0" cellpadding="2" cellspacing="1" width="100%">';
    $resetInvalid();
    echo '</table>';
    include __DIR__ . '/footer.inc.php';
    exit;
}

$tokenHash = hash('sha256', $token);
$reset = $db->fetchOne(
    'SELECT r.id, r.userid, r.expires_at, r.used_at, u.id AS uid FROM ppb_password_resets r
     JOIN ppb_users u ON u.id = r.userid WHERE r.token_hash = ?',
    [$tokenHash]
);

if ($reset === null || (int) $reset['used_at'] !== 0 || (int) $reset['expires_at'] < $now) {
    include __DIR__ . '/header.inc.php';
    echo '<table border="0" cellpadding="2" cellspacing="1" width="100%">';
    $resetInvalid();
    echo '</table>';
    include __DIR__ . '/footer.inc.php';
    exit;
}

$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateFromPost()) {
        $errorText = 'Security token invalid. Please try again.';
    } else {
        $p1 = Security::getString('password1', 'POST');
        $p2 = Security::getString('password2', 'POST');
        if ($p1 !== $p2) {
            $errorText = $lang_pwdsdifferent ?? 'Passwords do not match';
        } elseif (!Validator::isStrongPassword($p1)) {
            $errorText = $lang_pwdtooshort ?? 'Password must be at least 8 characters';
        } else {
            $hash = Security::hashPassword($p1);
            $db->query('UPDATE ppb_users SET password = ? WHERE id = ?', [$hash, $reset['userid']]);
            $db->query('UPDATE ppb_password_resets SET used_at = ? WHERE id = ?', [$now, $reset['id']]);
            $done = true;
        }
    }
}

include __DIR__ . '/header.inc.php';
?>
<table border="0" cellpadding="2" cellspacing="1" width="100%">
<?php if ($done): ?>
  <tr><td bgcolor="<?= Security::escape($settings['tablebg3'] ?? '#cccccc') ?>"><b><?= $lang_statusmessage ?? 'Status' ?></b></td></tr>
  <tr><td bgcolor="<?= Security::escape($settings['tablebg2'] ?? '#eeeeee') ?>"><br><?= $lang_pwdresetsuccess ?? 'Password has been reset.' ?><br><br></td></tr>
  <tr><td bgcolor="<?= Security::escape($settings['tablebg1'] ?? '#ffffff') ?>" align="center"><a href="login.php">Login</a></td></tr>
<?php else: ?>
  <tr><td bgcolor="<?= Security::escape($settings['tablebg3'] ?? '#cccccc') ?>"><b><?= $lang_newpassword ?? 'New Password' ?></b></td></tr>
  <?php if (isset($errorText)): ?>
    <tr><td bgcolor="<?= Security::escape($settings['tablebg2'] ?? '#eeeeee') ?>"><?= Security::escape($errorText) ?></td></tr>
  <?php endif; ?>
  <form action="resetpassword.php?token=<?= Security::escape($token) ?>" method="post">
    <?= CSRF::getTokenField() ?>
    <tr><td bgcolor="<?= Security::escape($settings['tablebg2'] ?? '#eeeeee') ?>" align="center">
      <br>
      <?= $lang_newpassword ?? 'New password' ?>: <input type="password" name="password1" minlength="8" required><br><br>
      <?= $lang_confirmation ?? 'Confirmation' ?>: <input type="password" name="password2" minlength="8" required><br><br>
      <input type="submit" value="<?= $lang_send ?? 'Send' ?>">
    </td></tr>
  </form>
<?php endif; ?>
</table>
<?php include __DIR__ . '/footer.inc.php'; ?>
```

- [ ] **Step 3: Neue Lang-Strings in den Sprachdateien**

```php
$lang_pwdresetclicklink = 'Click this link within one hour to reset your password:';
```
(deutsch entsprechend)

- [ ] **Step 4: Manueller Test**

1. `POST /sendpassword.php?send=1` mit bekannter Email → Status "If the email is registered...".
2. `POST` mit unbekannter Email → gleiche Meldung (keine Enumeration).
3. Mailpit oeffnen → Mail mit Link sichtbar.
4. Link oeffnen → resetpassword.php Formular.
5. Neues Passwort setzen → Erfolg, alter Passwort-Hash nicht mehr gueltig.
6. Alter Passwort-Link erneut → "Invalid or expired".
7. 6x `POST /sendpassword.php` von gleicher IP → ab 6. blockiert.

- [ ] **Step 5: Commit**

```bash
git add sendpassword.php resetpassword.php english.inc.php deutsch-sie.inc.php deutsch-du.inc.php
git commit -m "fix(pwreset): BUG-016 Token-Flow, BUG-017 unified response, BUG-018 rate-limit, neue resetpassword.php"
```

---

## Task 11: Fix Posting (BUG-014, BUG-015)

**Files:**
- Modify: `newpost.php`
- Modify: `newthread.php`
- Modify: `editpost.php`

- [ ] **Step 1: newpost.php - Session-only und Length-Validation**

In `newpost.php` den Email/Password-Fallback-Block (Zeilen 158-206) ersetzen durch einfache Session-Pruefung:

```php
                } elseif ($loggedin !== 'YES') {
                    default_error(
                        $lang_loginfirst ?? 'You have to log in first',
                        'login.php',
                        $lang_login ?? 'Login',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                    $user = null;
                } else {
                    $user = $ppbuser;
                }
```

Nach `$text = Security::getString('text', 'POST');` und vor dem `if ($text === '')`:
```php
                if (mb_strlen($text) > \PowerPHPBoard\Validator::POST_MAX) {
                    default_error(
                        $lang_posttoolong ?? 'Post text is too long',
                        'javascript:history.back()',
                        $lang_backtonewpostform ?? 'Back to form',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                    return;
                }
```

Zusaetzlich: im Formular-HTML die Email/Password-Inputs entfernen (nur wenn nicht eingeloggt sind die ueberhaupt relevant - am besten durch Login-Prompt ersetzen).

In der Formular-Ausgabe (nicht eingeloggt) ersetzen durch:
```php
if ($loggedin !== 'YES') {
    default_error(
        $lang_loginfirst ?? 'You have to log in first',
        'login.php',
        $lang_login ?? 'Login',
        $settings['tablebg3'] ?? '#cccccc',
        $settings['tablebg2'] ?? '#eeeeee',
        $settings['tablebg1'] ?? '#ffffff'
    );
}
```

- [ ] **Step 2: newthread.php - gleiche Aenderungen**

Analog zu Task 11/Step 1. Email/Password-Fallback entfernen, Length-Validation ergaenzen.

- [ ] **Step 3: editpost.php - Session-only Path**

Der Code hat `$loggedin === 'YES'` Fallback → ueberall wo das Email/Password abgefragt wird, diesen Pfad entfernen (nur Session-Login zulassen). Spezifisch Zeilen 100-182 - der komplette Login-Form-Block fuer nicht-eingeloggte wird geloescht, durch Redirect ersetzt.

- [ ] **Step 4: Manueller Test**

- Logout und versuchen zu posten → Redirect zu Login.
- Einloggen, posten, extrem langen Text (70000 chars) → Length-Fehler.

- [ ] **Step 5: Commit**

```bash
git add newpost.php newthread.php editpost.php
git commit -m "fix(posting): BUG-014 Session-only Authentication, BUG-015 Post-Length-Validation"
```

---

## Task 12: Audit-Dokumente aktualisieren

**Files:**
- Modify: `docs/2026-04-23-Userbereichs-bugs.md`
- Modify: `docs/2026-04-23-Userbereichs-test-coverage.md` / `docs/2026-04-23-Userbereichs-testabdeckung.md`

- [ ] **Step 1: Alle Bugs von "Offen" zu "Behoben" setzen**

In `docs/2026-04-23-Userbereichs-bugs.md` und den Aliases: alle `**Status:** Offen` zu `**Status:** Behoben (Commit siehe git log)`.

- [ ] **Step 2: Test-Coverage-Dokumente anpassen**

Abschlussbericht-Section um "Fix-Pass 2026-04-23 durchgefuehrt" ergaenzen.

- [ ] **Step 3: Commit**

```bash
git add docs/
git commit -m "docs: Audit-Bugs auf Behoben setzen"
```

---

## Self-Review Checklist

**Spec coverage:**
- BUG-001 → Task 5 ✓
- BUG-002 → Task 4 (Mailer) + Task 5 (register) + Task 10 (sendpassword) ✓
- BUG-003 → Task 1 (Unique-Index) + Task 5 (register) + Task 9 (profile) ✓
- BUG-004 → Task 2 (Validator) + Task 5 (register) ✓
- BUG-005 → Task 2 + Task 5 ✓
- BUG-006 → Task 7 ✓
- BUG-007 → Task 3 (RateLimiter) + Task 7 ✓
- BUG-008 → Task 8 ✓
- BUG-009 → Task 7 ✓
- BUG-010 → Task 6 ✓
- BUG-011 → Task 9 ✓
- BUG-012 → Task 9 ✓
- BUG-013 → Task 9 ✓
- BUG-014 → Task 11 ✓
- BUG-015 → Task 2 + Task 11 ✓
- BUG-016 → Task 1 (pwreset-table) + Task 10 ✓
- BUG-017 → Task 10 ✓
- BUG-018 → Task 3 + Task 10 ✓

**Placeholder scan:** Kein TBD, alle Tasks haben konkrete Code-Bloecke.

**Type consistency:** Validator-Konstanten, RateLimiter-Interface, Mailer-Signatur durchgaengig gleich.
