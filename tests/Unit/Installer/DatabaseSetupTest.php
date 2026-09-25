<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\AdminAccount;
use PowerPHPBoard\Installer\DatabaseSetup;
use PowerPHPBoard\Security;
use UnexpectedValueException;

/**
 * Die Installationslogik wird mit SQLite geprüft (MySQL-spezifische Teile wie
 * SHOW TABLES deckt der echte Durchlauf gegen MySQL ab).
 */
final class DatabaseSetupTest extends TestCase
{
    /**
     * @return array<string, array{string, int, string}>
     */
    public static function connectionErrors(): array
    {
        return [
            'Zugang verweigert' => [
                "SQLSTATE[HY000] [1045] Access denied for user 'geheimuser'@'172.18.0.5' (using password: YES)",
                1045,
                'Benutzername oder Passwort ist falsch',
            ],
            'auth_socket' => ["SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'", 1698, 'Benutzername oder Passwort'],
            'Datenbank fehlt' => ["SQLSTATE[HY000] [1049] Unknown database 'forum_x'", 1049, 'existiert nicht'],
            'keine Rechte' => ["SQLSTATE[HY000] [1044] Access denied for user 'u'@'%' to database 'd'", 1044, 'keine Berechtigung'],
            'Server nicht erreichbar' => ['SQLSTATE[HY000] [2002] Connection refused', 2002, 'nicht erreichbar'],
            'Host unbekannt' => ['SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for dbx failed', 2002, 'nicht erreichbar'],
            'Host nicht freigegeben' => ["SQLSTATE[HY000] [1130] Host '10.0.0.1' is not allowed", 1130, 'lässt keine Verbindungen'],
            'Rechte fehlen beim Anlegen' => [
                "SQLSTATE[42000]: Syntax error or access violation: 1142 CREATE command denied to user 'u'@'h' for table 'ppb_boards'",
                1142,
                'fehlen Rechte',
            ],
            'ungültige Zeichen' => ["SQLSTATE[HY000]: General error: 1366 Incorrect string value: '\\x84' for column 'boardtitle'", 1366, 'nicht speichern'],
            'unbekannter Fehler' => ['SQLSTATE[HY000] [9999] Irgendwas', 9999, 'Fehlercode 9999'],
            'ohne Code' => ['kaputt', 0, 'nicht erreichbar oder hat die Anfrage abgelehnt'],
        ];
    }

    #[DataProvider('connectionErrors')]
    public function testFriendlyErrorHidesCredentials(string $message, int $code, string $expectedText): void
    {
        $exception = new PDOException($message);

        $this->assertSame($code, DatabaseSetup::driverCode($exception));
        $friendly = DatabaseSetup::friendlyError($exception);
        $this->assertStringContainsString($expectedText, $friendly);
        $this->assertStringNotContainsString('geheimuser', $friendly);
        $this->assertStringNotContainsString('@', $friendly);
        $this->assertStringNotContainsString('SQLSTATE', $friendly);
    }

    public function testDriverCodeAndSqlStateFromErrorInfo(): void
    {
        $exception = new PDOException('SQLSTATE[42S02]: Base table or view not found');
        $exception->errorInfo = ['42S02', 1146, "Table 'x.ppb_config' doesn't exist"];

        $this->assertSame(1146, DatabaseSetup::driverCode($exception));
        $this->assertSame('42S02', DatabaseSetup::sqlState($exception));
    }

    public function testSqlStateFromMessage(): void
    {
        $this->assertSame('HY000', DatabaseSetup::sqlState(new PDOException('SQLSTATE[HY000] [2002] x')));
        $this->assertSame('', DatabaseSetup::sqlState(new PDOException('ohne Status')));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testInstallCreatesTablesConfigAndAdministrator(): void
    {
        $pdo = $this->sqlite();
        $hash = Security::hashPassword('S3cure!Pass');

        $adminId = DatabaseSetup::install($pdo, $this->schema(), $this->forum(), [
            'username' => 'Chef',
            'email' => 'chef@example.com',
            'password_hash' => $hash,
        ], 1_790_000_000);

        $this->assertSame(1, $adminId);
        $config = $pdo->query('SELECT boardtitle, boardurl, adminemail, language, htmlcode, bbcode, smilies FROM ppb_config WHERE id = 1')?->fetch();
        $this->assertSame([
            'boardtitle' => 'Mein Forum',
            'boardurl' => 'https://example.com',
            'adminemail' => 'admin@example.com',
            'language' => 'Deutsch-Sie',
            'htmlcode' => 'OFF',
            'bbcode' => 'ON',
            'smilies' => 'ON',
        ], $config, 'HTML bleibt aus, auch wenn die Schemavorgabe anders lautet');

        $user = $pdo->query('SELECT * FROM ppb_users WHERE id = 1')?->fetch();
        $this->assertIsArray($user);
        $this->assertSame('Chef', $user['username']);
        $this->assertSame('Administrator', $user['status']);
        $this->assertSame(1_790_000_000, (int) $user['registered']);
        $this->assertTrue(Security::verifyPassword('S3cure!Pass', (string) $user['password']));
        $this->assertTrue(DatabaseSetup::hasConfigRow($pdo));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testFailedInstallRemovesOnlyTheTablesItCreated(): void
    {
        $pdo = $this->sqlite();
        $pdo->exec('CREATE TABLE fremde_tabelle (id INTEGER)');
        $schema = $this->schema();
        $schema[] = 'CREATE TABLE ppb_kaputt (';

        try {
            DatabaseSetup::install($pdo, $schema, $this->forum(), $this->admin(), 1);
            $this->fail('Es wurde eine PDOException erwartet.');
        } catch (PDOException) {
            $this->assertSame(['fremde_tabelle'], $this->tables($pdo));
        }
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testFailedAdminInsertRollsBackAndCleansUp(): void
    {
        $pdo = $this->sqlite();
        $schema = $this->schema();
        // ppb_users ohne Spalte "lastvisit" – das INSERT des Administrators scheitert
        $schema[2] = str_replace(', lastvisit INTEGER', '', $schema[2]);

        $this->expectException(PDOException::class);

        try {
            DatabaseSetup::install($pdo, $schema, $this->forum(), $this->admin(), 1);
        } finally {
            $this->assertFalse($pdo->inTransaction());
            $this->assertSame([], $this->tables($pdo));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidBoardUrls(): array
    {
        return [
            'leer' => [''],
            'ohne Schema' => ['example.com'],
            'JavaScript' => ['javascript:alert(1)'],
        ];
    }

    #[DataProvider('invalidBoardUrls')]
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testInstallRefusesMissingBoardUrlBeforeTouchingTheDatabase(string $url): void
    {
        $pdo = $this->sqlite();
        $forum = $this->forum();
        $forum['boardurl'] = $url;

        try {
            DatabaseSetup::install($pdo, $this->schema(), $forum, $this->admin(), 1);
            $this->fail('Es wurde eine UnexpectedValueException erwartet.');
        } catch (UnexpectedValueException $e) {
            $this->assertStringContainsString('Adresse des Forums', $e->getMessage());
            $this->assertSame([], $this->tables($pdo));
        }
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testInstallFailsCleanlyWithoutConfigRow(): void
    {
        $pdo = $this->sqlite();
        $schema = $this->schema();
        unset($schema[1]);

        try {
            DatabaseSetup::install($pdo, array_values($schema), $this->forum(), $this->admin(), 1);
            $this->fail('Es wurde eine UnexpectedValueException erwartet.');
        } catch (UnexpectedValueException $e) {
            $this->assertStringContainsString('ppb_config', $e->getMessage());
            $this->assertFalse($pdo->inTransaction());
            $this->assertSame([], $this->tables($pdo));
        }
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testHasConfigRowDistinguishesEmptyTable(): void
    {
        $pdo = $this->sqlite();
        $pdo->exec($this->schema()[0]);

        $this->assertFalse(DatabaseSetup::hasConfigRow($pdo));

        $pdo->exec($this->schema()[1]);
        $this->assertTrue(DatabaseSetup::hasConfigRow($pdo));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testAdminAccountHelpers(): void
    {
        $pdo = $this->sqlite();
        $pdo->exec($this->schema()[2]);

        $id = AdminAccount::create($pdo, 'Chef', 'chef@example.com', 'hash-1', 5);
        $pdo->exec("INSERT INTO ppb_users (username, email, password, homepage, icq, biography, signature, hideemail, logincookie, status, registered, lastvisit)
                    VALUES ('Nutzer', 'nutzer@example.com', 'x', '', '', '', '', 'YES', 'YES', 'Normal user', 1, 0)");

        $this->assertTrue(AdminAccount::usernameTaken($pdo, 'Chef'));
        $this->assertFalse(AdminAccount::usernameTaken($pdo, 'Niemand'));
        $this->assertSame(['id' => $id, 'username' => 'Chef', 'status' => 'Administrator'], AdminAccount::findByEmail($pdo, 'chef@example.com'));
        $this->assertNull(AdminAccount::findByEmail($pdo, 'fehlt@example.com'));

        $user = AdminAccount::findByEmail($pdo, 'nutzer@example.com');
        $this->assertNotNull($user);
        AdminAccount::promote($pdo, $user['id'], 'hash-2');

        $promoted = $pdo->query("SELECT status, password FROM ppb_users WHERE email = 'nutzer@example.com'")?->fetch();
        $this->assertSame(['status' => 'Administrator', 'password' => 'hash-2'], $promoted);
    }

    private function sqlite(): PDO
    {
        return new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * SQLite-Nachbau der für den Installer relevanten Tabellen.
     *
     * @return list<string>
     */
    private function schema(): array
    {
        return [
            'CREATE TABLE ppb_config (id INTEGER PRIMARY KEY, boardtitle TEXT, boardurl TEXT, adminemail TEXT, language TEXT, '
                . 'htmlcode TEXT, bbcode TEXT, smilies TEXT)',
            'INSERT INTO ppb_config (id, boardtitle, boardurl, adminemail, language, htmlcode, bbcode, smilies) '
                . "VALUES (1, 'Vorgabe', '', '', 'Deutsch-Du', 'ON', 'OFF', 'OFF')",
            'CREATE TABLE ppb_users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, email TEXT, password TEXT, '
                . 'homepage TEXT, icq TEXT, biography TEXT, signature TEXT, hideemail TEXT, logincookie TEXT, status TEXT, '
                . 'registered INTEGER, lastvisit INTEGER)',
        ];
    }

    /**
     * @return array{boardtitle: string, boardurl: string, adminemail: string, language: string}
     */
    private function forum(): array
    {
        return [
            'boardtitle' => 'Mein Forum',
            'boardurl' => 'https://example.com',
            'adminemail' => 'admin@example.com',
            'language' => 'Deutsch-Sie',
        ];
    }

    /**
     * @return array{username: string, email: string, password_hash: string}
     */
    private function admin(): array
    {
        return ['username' => 'Chef', 'email' => 'chef@example.com', 'password_hash' => 'hash'];
    }

    /**
     * @return list<string>
     */
    private function tables(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $this->assertNotFalse($stmt);

        /** @var list<string> $names */
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $names;
    }
}
