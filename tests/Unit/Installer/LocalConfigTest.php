<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\LocalConfig;

final class LocalConfigTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ppb-localconfig-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @chmod($file, 0o666);
            unlink($file);
        }
        rmdir($this->dir);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function trickyPasswords(): array
    {
        return [
            'einfaches Anführungszeichen' => ["pa'ss"],
            'doppeltes Anführungszeichen' => ['pa"ss'],
            'Backslash am Ende' => ['pass\\'],
            'Backslash vor Anführungszeichen' => ["pa\\'ss"],
            'Dollarzeichen' => ['pa$ss{$x}'],
            'PHP-Endtag' => ['pa?>ss<?php echo 1; ?>'],
            'Kommentarende' => ['pa*/ss/*'],
            'Zeilenumbruch' => ["zeile1\nzeile2\r\n"],
            'Nullbyte' => ["pa\0ss"],
            'Umlaute und Emoji' => ['Pässwört-ß-🔑'],
            'Leerzeichen außen' => ['  mit Leerzeichen  '],
            'leer' => [''],
            'Semikolon und Klammern' => ['a;b];[c'],
        ];
    }

    #[DataProvider('trickyPasswords')]
    public function testRenderedFileReturnsExactlyTheGivenPassword(string $password): void
    {
        $mysql = [
            'server' => 'db.example.com',
            'port' => 3307,
            'user' => "us'er",
            'password' => $password,
            'database' => 'forum_db',
        ];

        $config = $this->renderAndLoad($mysql, null);

        $this->assertSame(['mysql' => $mysql], $config);
    }

    public function testRenderedFileIsValidPhpWithStrictTypes(): void
    {
        $source = LocalConfig::render($this->mysql(), $this->mail(), '2026-09-25 12:00:00');

        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $source);
        $this->assertStringContainsString('Erzeugt vom Web-Installer am 2026-09-25 12:00:00.', $source);
        $this->assertSame(['mysql' => $this->mysql(), 'mail' => $this->mail()], $this->load($source));
    }

    public function testRenderWithoutMailOmitsMailSection(): void
    {
        $source = LocalConfig::render($this->mysql(), null, '2026-09-25 12:00:00');

        $this->assertStringNotContainsString("'mail'", $source);
        $this->assertArrayNotHasKey('mail', $this->load($source));
    }

    public function testRenderStripsUnexpectedCharactersFromTimestamp(): void
    {
        $source = LocalConfig::render($this->mysql(), null, "2026-09-25 */ evil(); /*\n?>");

        $this->assertStringNotContainsString('evil', $source);
        $this->assertSame(1, substr_count($source, '*/'));
        $this->assertSame(['mysql' => $this->mysql()], $this->load($source));
    }

    public function testMailValuesWithSpecialCharactersSurviveRoundTrip(): void
    {
        $mail = ['host' => "smtp'.example.com", 'port' => 587, 'from' => 'no"reply@example.com'];

        $this->assertSame(['mysql' => $this->mysql(), 'mail' => $mail], $this->renderAndLoad($this->mysql(), $mail));
    }

    public function testApplyOverridesEnvironmentValues(): void
    {
        $result = LocalConfig::apply($this->mysql(), $this->mail(), [
            'mysql' => ['server' => 'other', 'port' => 3310, 'user' => 'u', 'password' => 'p', 'database' => 'd'],
            'mail' => ['host' => 'smtp.local', 'port' => 2525, 'from' => 'a@b.de'],
        ]);

        $this->assertSame(
            ['server' => 'other', 'port' => 3310, 'user' => 'u', 'password' => 'p', 'database' => 'd'],
            $result['mysql']
        );
        $this->assertSame(['host' => 'smtp.local', 'port' => 2525, 'from' => 'a@b.de'], $result['mail']);
    }

    public function testApplyKeepsValuesThatAreMissingOrHaveTheWrongType(): void
    {
        $result = LocalConfig::apply($this->mysql(), $this->mail(), [
            'mysql' => ['server' => 'other', 'port' => '3310', 'user' => 42, 'password' => null],
            'mail' => 'kein Array',
        ]);

        $expected = $this->mysql();
        $expected['server'] = 'other';
        $this->assertSame($expected, $result['mysql']);
        $this->assertSame($this->mail(), $result['mail']);
    }

    public function testApplyRejectsInvalidPorts(): void
    {
        $result = LocalConfig::apply($this->mysql(), $this->mail(), [
            'mysql' => ['port' => 0],
            'mail' => ['port' => 70000],
        ]);

        $this->assertSame(3306, $result['mysql']['port']);
        $this->assertSame(25, $result['mail']['port']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidLocalConfigs(): array
    {
        return [
            'null' => [null],
            'true' => [true],
            'Zahl' => [1],
            'Zeichenkette' => ['config'],
            'leeres Array' => [[]],
        ];
    }

    #[DataProvider('invalidLocalConfigs')]
    public function testApplyIgnoresInvalidFileContent(mixed $local): void
    {
        $result = LocalConfig::apply($this->mysql(), $this->mail(), $local);

        $this->assertSame(['mysql' => $this->mysql(), 'mail' => $this->mail()], $result);
    }

    public function testDefaultsAreDetected(): void
    {
        $this->assertTrue(LocalConfig::isDefaultDatabase(LocalConfig::DEFAULT_MYSQL));
    }

    public function testConfigFromVersion22WithoutPortCountsAsDefault(): void
    {
        $legacy = LocalConfig::DEFAULT_MYSQL;
        unset($legacy['port']);

        $this->assertTrue(LocalConfig::isDefaultDatabase($legacy));
    }

    /**
     * @return array<string, array{string, string|int}>
     */
    public static function customisedValues(): array
    {
        return [
            'Server' => ['server', 'db'],
            'Port' => ['port', 3307],
            'Benutzer' => ['user', 'forum'],
            'Passwort' => ['password', 'geheim'],
            'Datenbank' => ['database', 'forum'],
        ];
    }

    #[DataProvider('customisedValues')]
    public function testAnyChangedValueMeansCustomConfig(string $key, string|int $value): void
    {
        $mysql = LocalConfig::DEFAULT_MYSQL;
        $mysql[$key] = $value;

        $this->assertFalse(LocalConfig::isDefaultDatabase($mysql));
    }

    public function testWriteFileCreatesFileWithContent(): void
    {
        $path = $this->dir . '/' . LocalConfig::FILENAME;

        $this->assertTrue(LocalConfig::writeFile($path, "<?php\n\nreturn [];\n"));
        $this->assertSame("<?php\n\nreturn [];\n", file_get_contents($path));
    }

    public function testWriteFileNeverOverwritesAnExistingFile(): void
    {
        $path = $this->dir . '/' . LocalConfig::FILENAME;
        file_put_contents($path, 'alt');

        $this->assertFalse(LocalConfig::writeFile($path, 'neu'));
        $this->assertSame('alt', file_get_contents($path));
    }

    public function testWriteFileFailsForMissingDirectory(): void
    {
        $this->assertFalse(LocalConfig::writeFile($this->dir . '/fehlt/' . LocalConfig::FILENAME, 'x'));
    }

    /**
     * @param array{server: string, port: int, user: string, password: string, database: string} $mysql
     * @param array{host: string, port: int, from: string}|null $mail
     *
     * @return array<array-key, mixed>
     */
    private function renderAndLoad(array $mysql, ?array $mail): array
    {
        return $this->load(LocalConfig::render($mysql, $mail, '2026-09-25 12:00:00'));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function load(string $source): array
    {
        $path = $this->dir . '/config-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($path, $source);

        // Ein Syntaxfehler würde hier als ParseError den Test abbrechen.
        $config = require $path;
        $this->assertIsArray($config);

        return $config;
    }

    /**
     * @return array{server: string, port: int, user: string, password: string, database: string}
     */
    private function mysql(): array
    {
        return ['server' => 'localhost', 'port' => 3306, 'user' => 'forum', 'password' => 'geheim', 'database' => 'forum'];
    }

    /**
     * @return array{host: string, port: int, from: string}
     */
    private function mail(): array
    {
        return ['host' => 'localhost', 'port' => 25, 'from' => 'noreply@example.com'];
    }
}
