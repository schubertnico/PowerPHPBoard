<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\Schema;
use RuntimeException;

final class SchemaTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../../..';

    private const array EXPECTED_TABLES = [
        'ppb_boards',
        'ppb_config',
        'ppb_posts',
        'ppb_users',
        'ppb_visits',
        'ppb_password_resets',
        'ppb_rate_limits',
    ];

    public function testInstallSqlSplitsIntoTablesAndConfigRow(): void
    {
        $statements = Schema::fromFile(self::ROOT . '/install.sql');

        $this->assertCount(8, $statements);
        $this->assertSame(self::EXPECTED_TABLES, Schema::tableNames($statements));
        $this->assertStringStartsWith('INSERT INTO ppb_config', $statements[7]);
    }

    public function testInstallSqlContainsNoDefaultAdministrator(): void
    {
        $sql = (string) file_get_contents(self::ROOT . '/install.sql');

        $this->assertStringNotContainsString('INSERT INTO ppb_users', $sql);
        $this->assertStringNotContainsStringIgnoringCase('gott', $sql);
        $this->assertStringNotContainsString('Z290dA==', $sql);
    }

    public function testInstallSqlIsCleanUtf8(): void
    {
        $sql = (string) file_get_contents(self::ROOT . '/install.sql');

        $this->assertTrue(mb_check_encoding($sql, 'UTF-8'));
        $this->assertStringNotContainsString("\xEF\xBF\xBD", $sql, 'Ersatzzeichen (kaputte Kodierung)');
        $this->assertStringNotContainsString("\r", $sql);
        $this->assertStringContainsString('Tabellenstruktur für Tabelle `ppb_users`', $sql);
    }

    public function testConfigRowHasSafeDefaults(): void
    {
        $statements = Schema::fromFile(self::ROOT . '/install.sql');
        $insert = $statements[7];

        $this->assertStringContainsString("(1, 'PowerPHPBoard", $insert);
        $this->assertStringContainsString("'OFF', 'ON', 'ON'", $insert, 'HTML aus, BBCode und Smilies an');
        $this->assertStringContainsString("'#000000', '#FFFFFF', '#F0F0F0', '#E0E0E0'", $insert);
        $this->assertStringContainsString(
            "htmlcode enum('ON','OFF') NOT NULL default 'OFF'",
            $statements[1],
            'Auch die Spaltenvorgabe schaltet HTML aus'
        );
    }

    public function testDevSeedCreatesDocumentedAccountsWithArgon2Hashes(): void
    {
        $seed = (string) file_get_contents(self::ROOT . '/.docker/dev-seed.sql');
        $statements = Schema::split($seed);

        $this->assertCount(2, $statements);
        preg_match_all('/\'(\$argon2id\$[^\']+)\'/', $seed, $matches);
        $this->assertCount(2, $matches[1]);
        foreach ($matches[1] as $hash) {
            $this->assertTrue(password_verify('Test1234!', $hash));
        }
        $this->assertStringContainsString("'RalphAdmin', 'ralphadmin@example.com'", $seed);
        $this->assertStringContainsString("'RalphUser', 'ralphuser@example.com'", $seed);
    }

    public function testDockerComposeLoadsSchemaBeforeDevSeed(): void
    {
        $compose = (string) file_get_contents(self::ROOT . '/.docker/docker-compose.yml');

        $this->assertStringContainsString('../install.sql:/docker-entrypoint-initdb.d/01-install.sql:ro', $compose);
        $this->assertStringContainsString('./dev-seed.sql:/docker-entrypoint-initdb.d/02-dev-seed.sql:ro', $compose);
    }

    public function testSemicolonsInsideQuotesDoNotSplit(): void
    {
        $sql = "INSERT INTO t VALUES ('a;b', \"c;d\", `e;f`);\nSELECT 1;";

        $this->assertSame(
            ["INSERT INTO t VALUES ('a;b', \"c;d\", `e;f`)", 'SELECT 1'],
            Schema::split($sql)
        );
    }

    public function testEscapedAndDoubledQuotesStayInsideTheString(): void
    {
        $sql = "INSERT INTO t VALUES ('it\\'s; ok', 'doppelt '' ; ok');SELECT 2";

        $this->assertSame(
            ["INSERT INTO t VALUES ('it\\'s; ok', 'doppelt '' ; ok')", 'SELECT 2'],
            Schema::split($sql)
        );
    }

    public function testCommentsAreRemoved(): void
    {
        $sql = "# Kommentar; mit Semikolon\n"
            . "-- noch einer; hier\n"
            . "/* Block;\n Kommentar */\n"
            . "SELECT 1; -- Rest\n"
            . "SELECT '#kein Kommentar', '-- auch keiner';";

        $this->assertSame(
            ['SELECT 1', "SELECT '#kein Kommentar', '-- auch keiner'"],
            Schema::split($sql)
        );
    }

    public function testDoubleDashWithoutSpaceIsNotAComment(): void
    {
        $this->assertSame(['SELECT 5--1'], Schema::split('SELECT 5--1;'));
    }

    public function testByteOrderMarkAndMissingFinalSemicolon(): void
    {
        $this->assertSame(['SELECT 1', 'SELECT 2'], Schema::split("\xEF\xBB\xBFSELECT 1;\n\nSELECT 2\n"));
    }

    public function testEmptyInputGivesNoStatements(): void
    {
        $this->assertSame([], Schema::split("  \n# nur Kommentar\n;;\n"));
    }

    public function testUnterminatedStringDoesNotLoop(): void
    {
        $this->assertSame(["SELECT 'offen; ohne Ende"], Schema::split("SELECT 'offen; ohne Ende"));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function createStatements(): array
    {
        return [
            'einfach' => ['CREATE TABLE ppb_users (id int)', 'ppb_users'],
            'Backticks' => ['CREATE TABLE `ppb_posts` (id int)', 'ppb_posts'],
            'IF NOT EXISTS' => ["create table if not exists ppb_visits (\n id int)", 'ppb_visits'],
            'Einrückung' => ["\n  CREATE   TABLE ppb_config(id int)", 'ppb_config'],
            'INSERT' => ['INSERT INTO ppb_users VALUES (1)', null],
            'DROP' => ['DROP TABLE ppb_users', null],
        ];
    }

    #[DataProvider('createStatements')]
    public function testCreatedTable(string $statement, ?string $expected): void
    {
        $this->assertSame($expected, Schema::createdTable($statement));
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fehlt.sql');

        Schema::fromFile(sys_get_temp_dir() . '/ppb-gibt-es-nicht/fehlt.sql');
    }
}
