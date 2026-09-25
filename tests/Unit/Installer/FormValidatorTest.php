<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\FormValidator;

final class FormValidatorTest extends TestCase
{
    // ---------------------------------------------------------------
    //  Schritt 2: Datenbank
    // ---------------------------------------------------------------

    public function testValidDatabaseInput(): void
    {
        $result = FormValidator::database([
            'db_host' => ' db.example.com ',
            'db_port' => '3307',
            'db_name' => 'forum_1',
            'db_user' => 'forum',
            'db_password' => ' geheim ',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame([
            'server' => 'db.example.com',
            'port' => 3307,
            'user' => 'forum',
            'password' => ' geheim ',
            'database' => 'forum_1',
        ], $result['values']);
    }

    public function testDatabasePasswordIsKeptExactlyAndMayBeEmpty(): void
    {
        $withSpecialChars = FormValidator::database($this->db(['db_password' => "  a'b\"c\\d\$e?>  "]));
        $empty = FormValidator::database($this->db(['db_password' => '']));
        $missing = FormValidator::database($this->db(['db_password' => null]));

        $this->assertSame("  a'b\"c\\d\$e?>  ", $withSpecialChars['values']['password']);
        $this->assertSame('', $empty['values']['password']);
        $this->assertSame('', $missing['values']['password']);
        $this->assertSame([], $withSpecialChars['errors']);
    }

    public function testEmptyPortUsesDefault(): void
    {
        $result = FormValidator::database($this->db(['db_port' => '']));

        $this->assertSame([], $result['errors']);
        $this->assertSame(3306, $result['values']['port']);
    }

    public function testMissingDatabaseFieldsAreReported(): void
    {
        $result = FormValidator::database([]);

        $this->assertSame(['db_host', 'db_name', 'db_user'], array_keys($result['errors']));
        $this->assertStringContainsString('localhost', $result['errors']['db_host']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidDatabaseInput(): array
    {
        return [
            'DSN-Injektion im Host' => ['db_host', 'localhost;unix_socket=/tmp/x'],
            'Gleichheitszeichen im Host' => ['db_host', 'host=evil'],
            'Leerzeichen im Host' => ['db_host', 'db server'],
            'Port zu groß' => ['db_port', '70000'],
            'Port null' => ['db_port', '0'],
            'Port keine Zahl' => ['db_port', '33a'],
            'DSN-Injektion im Namen' => ['db_name', 'forum;host=evil'],
            'Name zu lang' => ['db_name', str_repeat('a', 65)],
            'Punkt im Namen' => ['db_name', 'forum.db'],
            'Steuerzeichen im Benutzer' => ['db_user', "for\num"],
            'Benutzer kein UTF-8' => ['db_user', "for\xFFum"],
            'Benutzer zu lang' => ['db_user', str_repeat('u', 81)],
        ];
    }

    #[DataProvider('invalidDatabaseInput')]
    public function testInvalidDatabaseInput(string $field, string $value): void
    {
        $result = FormValidator::database($this->db([$field => $value]));

        $this->assertArrayHasKey($field, $result['errors']);
        $this->assertCount(1, $result['errors']);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function hostnames(): array
    {
        return [
            'localhost' => ['localhost', true],
            'Docker-Dienst' => ['db', true],
            'FQDN' => ['mysql5.example.com', true],
            'Unterstrich' => ['my_db-1.internal', true],
            'IPv4' => ['127.0.0.1', true],
            'IPv6' => ['[::1]', true],
            'leer' => ['', false],
            'Semikolon' => ['a;b', false],
            'Schrägstrich' => ['/tmp/mysql.sock', false],
            'Punkt am Ende' => ['example.', false],
            'Doppelpunkt ohne Klammern' => ['localhost:3306', false],
            'zu lang' => [str_repeat('a', 256), false],
        ];
    }

    #[DataProvider('hostnames')]
    public function testIsHostname(string $host, bool $expected): void
    {
        $this->assertSame($expected, FormValidator::isHostname($host));
    }

    // ---------------------------------------------------------------
    //  Schritt 3: Forum
    // ---------------------------------------------------------------

    public function testValidForumInput(): void
    {
        $result = FormValidator::forum($this->forum());

        $this->assertSame([], $result['errors']);
        $this->assertSame([
            'boardtitle' => 'Mein Forum',
            'boardurl' => 'https://forum.example.com/board',
            'adminemail' => 'admin@example.com',
            'language' => 'Deutsch-Sie',
            'mail' => ['host' => 'localhost', 'port' => 25, 'from' => 'noreply@example.com'],
        ], $result['values']);
    }

    public function testUtf8TitleIsAccepted(): void
    {
        $result = FormValidator::forum($this->forum(['board_title' => 'Mein <b>Test</b>-Forum „Ä“ 🚀']));

        $this->assertSame([], $result['errors']);
        $this->assertSame('Mein <b>Test</b>-Forum „Ä“ 🚀', $result['values']['boardtitle'], 'Rohwert speichern, beim Ausgeben escapen');
    }

    public function testTrailingSlashIsRemovedFromBoardUrl(): void
    {
        $result = FormValidator::forum($this->forum(['board_url' => 'http://localhost:8219/']));

        $this->assertSame('http://localhost:8219', $result['values']['boardurl']);
    }

    public function testEmptySmtpHostKeepsDefaults(): void
    {
        $result = FormValidator::forum($this->forum(['smtp_host' => '', 'smtp_port' => 'egal', 'smtp_from' => 'kaputt']));

        $this->assertSame([], $result['errors']);
        $this->assertNull($result['values']['mail']);
    }

    public function testEmptySenderFallsBackToBoardEmail(): void
    {
        $result = FormValidator::forum($this->forum(['smtp_from' => '', 'smtp_port' => '']));

        $this->assertSame(['host' => 'localhost', 'port' => 25, 'from' => 'admin@example.com'], $result['values']['mail']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidForumInput(): array
    {
        return [
            'Titel leer' => ['board_title', ''],
            'Titel zu lang' => ['board_title', str_repeat('T', 201)],
            'Titel mit Steuerzeichen' => ['board_title', "Forum\x07"],
            'Titel kein UTF-8 (Windows-1252)' => ['board_title', "Mein \x84\xC4\x93 Forum"],
            'URL leer' => ['board_url', ''],
            'URL ohne Schema' => ['board_url', 'forum.example.com'],
            'JavaScript-URL' => ['board_url', 'javascript:alert(1)'],
            'FTP-URL' => ['board_url', 'ftp://example.com'],
            'URL zu lang' => ['board_url', 'https://example.com/' . str_repeat('a', 240)],
            'E-Mail ungültig' => ['board_email', 'keine-mail'],
            'E-Mail zu lang' => ['board_email', str_repeat('a', 95) . '@example.com'],
            'Sprache unbekannt' => ['board_language', 'Klingonisch'],
            'SMTP-Host ungültig' => ['smtp_host', 'smtp;evil'],
            'SMTP-Port ungültig' => ['smtp_port', '99999'],
            'Absender ungültig' => ['smtp_from', 'kein@'],
        ];
    }

    #[DataProvider('invalidForumInput')]
    public function testInvalidForumInput(string $field, string $value): void
    {
        $result = FormValidator::forum($this->forum([$field => $value]));

        $this->assertSame([$field], array_keys($result['errors']));
    }

    public function testAllThreeForumLanguagesAreAccepted(): void
    {
        foreach (array_keys(FormValidator::LANGUAGES) as $language) {
            $result = FormValidator::forum($this->forum(['board_language' => $language]));
            $this->assertSame([], $result['errors'], $language);
        }
        $this->assertSame(['Deutsch-Sie', 'Deutsch-Du', 'English'], array_keys(FormValidator::LANGUAGES));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function urls(): array
    {
        return [
            'https' => ['https://example.com', true],
            'http mit Port und Pfad' => ['http://localhost:8219/forum', true],
            'Großbuchstaben im Schema' => ['HTTPS://example.com', true],
            'ohne Schema' => ['example.com', false],
            'data' => ['data:text/html,x', false],
            'mailto' => ['mailto:a@b.de', false],
        ];
    }

    #[DataProvider('urls')]
    public function testIsWebUrl(string $url, bool $expected): void
    {
        $this->assertSame($expected, FormValidator::isWebUrl($url));
    }

    // ---------------------------------------------------------------
    //  Schritt 4: Administrator
    // ---------------------------------------------------------------

    public function testValidAdminInput(): void
    {
        $result = FormValidator::admin($this->admin());

        $this->assertSame([], $result['errors']);
        $this->assertSame(['username' => 'Chef.Admin_1', 'email' => 'chef@example.com', 'password' => 'S3cure!Pass'], $result['values']);
    }

    public function testAdminPasswordIsTrimmedLikeLoginAndRegistration(): void
    {
        $result = FormValidator::admin($this->admin([
            'admin_password' => '  S3cure!Pass  ',
            'admin_password_confirm' => 'S3cure!Pass',
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertSame('S3cure!Pass', $result['values']['password']);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidAdminInput(): array
    {
        return [
            'Benutzername zu kurz' => [['admin_username' => 'a'], 'admin_username'],
            'Benutzername mit Leerzeichen' => [['admin_username' => 'Chef Admin'], 'admin_username'],
            'Benutzername mit HTML' => [['admin_username' => '<b>x</b>'], 'admin_username'],
            'Benutzername zu lang' => [['admin_username' => str_repeat('x', 51)], 'admin_username'],
            'E-Mail ungültig' => [['admin_email' => 'chef@'], 'admin_email'],
            'Passwort zu kurz' => [['admin_password' => 'kurz', 'admin_password_confirm' => 'kurz'], 'admin_password'],
            'Passwörter verschieden' => [['admin_password_confirm' => 'Anderes!Pass'], 'admin_password_confirm'],
        ];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('invalidAdminInput')]
    public function testInvalidAdminInput(array $override, string $field): void
    {
        $result = FormValidator::admin($this->admin($override));

        $this->assertSame([$field], array_keys($result['errors']));
    }

    public function testNonStringInputIsTreatedAsEmpty(): void
    {
        $result = FormValidator::admin(['admin_username' => ['array'], 'admin_email' => 42]);

        $this->assertArrayHasKey('admin_username', $result['errors']);
        $this->assertArrayHasKey('admin_email', $result['errors']);
        $this->assertArrayHasKey('admin_password', $result['errors']);
    }

    /**
     * @param array<string, string|null> $override
     *
     * @return array<string, string|null>
     */
    private function db(array $override = []): array
    {
        return array_merge([
            'db_host' => 'localhost',
            'db_port' => '3306',
            'db_name' => 'forum',
            'db_user' => 'forum',
            'db_password' => 'geheim',
        ], $override);
    }

    /**
     * @param array<string, string> $override
     *
     * @return array<string, string>
     */
    private function forum(array $override = []): array
    {
        return array_merge([
            'board_title' => 'Mein Forum',
            'board_url' => 'https://forum.example.com/board',
            'board_email' => 'admin@example.com',
            'board_language' => 'Deutsch-Sie',
            'smtp_host' => 'localhost',
            'smtp_port' => '25',
            'smtp_from' => 'noreply@example.com',
        ], $override);
    }

    /**
     * @param array<string, string> $override
     *
     * @return array<string, string>
     */
    private function admin(array $override = []): array
    {
        return array_merge([
            'admin_username' => 'Chef.Admin_1',
            'admin_email' => 'chef@example.com',
            'admin_password' => 'S3cure!Pass',
            'admin_password_confirm' => 'S3cure!Pass',
        ], $override);
    }
}
