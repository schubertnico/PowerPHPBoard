<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\FormValidator;
use PowerPHPBoard\Mailer;

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
            'mail' => ['host' => 'localhost', 'port' => 25, 'from' => 'noreply@example.com', 'user' => '', 'password' => '', 'encryption' => 'none'],
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

    public function testEmptySenderMeansTheForumAddress(): void
    {
        $result = FormValidator::forum($this->forum(['smtp_from' => '', 'smtp_port' => '']));

        // Leer bleibt leer: Absender ist dann stets die aktuelle Admin-E-Mail
        // aus den Einstellungen, auch wenn sie später geändert wird
        $this->assertSame(
            ['host' => 'localhost', 'port' => 25, 'from' => '', 'user' => '', 'password' => '', 'encryption' => 'none'],
            $result['values']['mail']
        );
        $mail = $result['values']['mail'];
        $this->assertNotNull($mail);
        $this->assertSame('admin@example.com', Mailer::senderAddress(['adminemail' => 'admin@example.com'], $mail));
    }

    public function testConfiguredSenderBecomesFromAddress(): void
    {
        $mail = FormValidator::forum($this->forum(['smtp_from' => 'forum@example.com']))['values']['mail'];

        $this->assertNotNull($mail);
        $this->assertSame('forum@example.com', $mail['from']);
        $this->assertSame('forum@example.com', Mailer::senderAddress(['adminemail' => 'admin@example.com'], $mail));
        $this->assertSame('admin@example.com', Mailer::replyToAddress(['adminemail' => 'admin@example.com'], $mail));
    }

    public function testSmtpLoginWithStarttls(): void
    {
        $result = FormValidator::forum($this->forum([
            'smtp_host' => 'smtp.hoster.example',
            'smtp_port' => '587',
            'smtp_encryption' => 'starttls',
            'smtp_user' => ' forum@example.com ',
            'smtp_password' => '  App Passwort  ',
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertSame([
            'host' => 'smtp.hoster.example',
            'port' => 587,
            'from' => 'noreply@example.com',
            'user' => 'forum@example.com',
            'password' => '  App Passwort  ',
            'encryption' => 'starttls',
        ], $result['values']['mail']);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function defaultPorts(): array
    {
        return [
            'keine' => ['none', 25],
            'STARTTLS' => ['starttls', 587],
            'SSL/TLS' => ['ssl', 465],
            'Feld fehlt (altes Formular)' => ['', 25],
        ];
    }

    #[DataProvider('defaultPorts')]
    public function testEmptyPortUsesTheUsualPortOfTheEncryption(string $encryption, int $port): void
    {
        $result = FormValidator::forum($this->forum(['smtp_encryption' => $encryption, 'smtp_port' => '']));

        $this->assertSame([], $result['errors']);
        $this->assertSame($port, $result['values']['mail']['port'] ?? null);
        $this->assertSame($encryption === '' ? 'none' : $encryption, $result['values']['mail']['encryption'] ?? null);
    }

    public function testSmtpUserRequiresPassword(): void
    {
        $result = FormValidator::forum($this->forum(['smtp_user' => 'forum@example.com', 'smtp_password' => '']));

        $this->assertSame(['smtp_password'], array_keys($result['errors']));
        $this->assertSame('Bitte geben Sie das Passwort des E-Mail-Postfachs an.', $result['errors']['smtp_password']);
    }

    public function testPasswordWithoutUserIsNotStored(): void
    {
        // z. B. vom Browser automatisch ausgefüllt
        $result = FormValidator::forum($this->forum(['smtp_user' => '', 'smtp_password' => 'autofill']));

        $this->assertSame([], $result['errors']);
        $this->assertSame('', $result['values']['mail']['password'] ?? null);
    }

    public function testEmptyPasswordKeepsTheStoredPasswordOfTheSameUser(): void
    {
        $previous = $this->smtp(['user' => 'forum@example.com', 'password' => 'gespeichert']);

        $same = FormValidator::forum($this->forum(['smtp_user' => 'forum@example.com', 'smtp_password' => '']), $previous);
        $other = FormValidator::forum($this->forum(['smtp_user' => 'anderer@example.com', 'smtp_password' => '']), $previous);
        $changed = FormValidator::forum($this->forum(['smtp_user' => 'forum@example.com', 'smtp_password' => 'neu']), $previous);

        $this->assertSame([], $same['errors']);
        $this->assertSame('gespeichert', $same['values']['mail']['password'] ?? null);
        $this->assertSame(['smtp_password'], array_keys($other['errors']));
        $this->assertSame('neu', $changed['values']['mail']['password'] ?? null);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function smtpWithoutHost(): array
    {
        return [
            'Benutzer' => [['smtp_user' => 'forum@example.com', 'smtp_password' => 'x']],
            'Verschlüsselung' => [['smtp_encryption' => 'ssl']],
        ];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('smtpWithoutHost')]
    public function testSmtpSettingsWithoutHostAreReported(array $override): void
    {
        $result = FormValidator::forum($this->forum(['smtp_host' => ''] + $override));

        $this->assertSame(['smtp_host'], array_keys($result['errors']));
        $this->assertNull($result['values']['mail']);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidSmtpCredentials(): array
    {
        return [
            'Steuerzeichen im Benutzer' => [['smtp_user' => "forum\r\nRCPT", 'smtp_password' => 'x'], 'smtp_user'],
            'Benutzer zu lang' => [['smtp_user' => str_repeat('u', 256), 'smtp_password' => 'x'], 'smtp_user'],
            'Passwort zu lang' => [['smtp_user' => 'forum', 'smtp_password' => str_repeat('p', 256)], 'smtp_password'],
        ];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('invalidSmtpCredentials')]
    public function testInvalidSmtpCredentials(array $override, string $field): void
    {
        $result = FormValidator::forum($this->forum($override));

        $this->assertSame([$field], array_keys($result['errors']));
    }

    public function testEncryptionLabelsForTheSelectList(): void
    {
        $this->assertSame(
            ['none' => 'Keine', 'starttls' => 'STARTTLS (meist Port 587)', 'ssl' => 'SSL/TLS (meist Port 465)'],
            FormValidator::ENCRYPTIONS
        );
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
            'SMTP-Port null' => ['smtp_port', '0'],
            'Absender ungültig' => ['smtp_from', 'kein@'],
            'Verschlüsselung unbekannt' => ['smtp_encryption', 'tls'],
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
     * @param array<string, string|int> $override
     *
     * @return array{host: string, port: int, from: string, user: string, password: string, encryption: string}
     */
    private function smtp(array $override = []): array
    {
        /** @var array{host: string, port: int, from: string, user: string, password: string, encryption: string} */
        return array_replace(
            ['host' => 'localhost', 'port' => 25, 'from' => 'noreply@example.com', 'user' => '', 'password' => '', 'encryption' => 'none'],
            $override
        );
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
