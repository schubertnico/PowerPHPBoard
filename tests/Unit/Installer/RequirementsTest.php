<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\LocalConfig;
use PowerPHPBoard\Installer\Requirements;

final class RequirementsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ppb-requirements-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/logs', 0o777, true);
        file_put_contents($this->root . '/install.sql', 'SELECT 1;');
    }

    protected function tearDown(): void
    {
        foreach (['install.sql', LocalConfig::FILENAME] as $file) {
            if (is_file($this->root . '/' . $file)) {
                unlink($this->root . '/' . $file);
            }
        }
        if (is_dir($this->root . '/logs')) {
            rmdir($this->root . '/logs');
        }
        rmdir($this->root);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function phpVersions(): array
    {
        return [
            '8.4.0' => ['8.4.0', true],
            '8.4.26' => ['8.4.26', true],
            '8.5.0' => ['8.5.0', true],
            '8.3.30' => ['8.3.30', false],
            '7.4.33' => ['7.4.33', false],
        ];
    }

    #[DataProvider('phpVersions')]
    public function testPhpVersion(string $version, bool $expected): void
    {
        $this->assertSame($expected, Requirements::phpVersionOk($version));
    }

    /**
     * @return array<string, array{array<string, string>, bool}>
     */
    public static function servers(): array
    {
        return [
            'HTTPS on' => [['HTTPS' => 'on'], true],
            'HTTPS 1' => [['HTTPS' => '1'], true],
            'HTTPS off (IIS)' => [['HTTPS' => 'off'], false],
            'Port 443' => [['SERVER_PORT' => '443'], true],
            'Port 80' => [['SERVER_PORT' => '80'], false],
            'nichts' => [[], false],
        ];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('servers')]
    public function testIsHttps(array $server, bool $expected): void
    {
        $this->assertSame($expected, Requirements::isHttps($server));
    }

    #[RequiresPhpExtension('pdo_mysql')]
    #[RequiresPhpExtension('mbstring')]
    public function testAllRequirementsMetOnPreparedDirectory(): void
    {
        $checks = Requirements::check($this->root, ['HTTPS' => 'on'], '8.4.1');

        $this->assertTrue(Requirements::allRequiredMet($checks));
        $this->assertSame(
            ['php', 'pdo_mysql', 'mbstring', 'schema', 'logs', 'config', 'https'],
            array_column($checks, 'id')
        );
        foreach ($checks as $check) {
            $this->assertTrue($check['ok'], $check['id']);
        }
    }

    public function testOldPhpVersionFails(): void
    {
        $checks = Requirements::check($this->root, [], '8.3.0');

        $this->assertFalse($this->find($checks, 'php')['ok']);
        $this->assertStringContainsString('8.3.0', $this->find($checks, 'php')['detail']);
        $this->assertFalse(Requirements::allRequiredMet($checks));
    }

    public function testMissingSchemaAndLogsFail(): void
    {
        unlink($this->root . '/install.sql');
        rmdir($this->root . '/logs');

        $checks = Requirements::check($this->root, [], '8.4.1');

        $this->assertFalse($this->find($checks, 'schema')['ok']);
        $this->assertFalse($this->find($checks, 'logs')['ok']);
        $this->assertFalse(Requirements::allRequiredMet($checks));
    }

    public function testOptionalChecksDoNotBlock(): void
    {
        touch($this->root . '/' . LocalConfig::FILENAME);

        $checks = Requirements::check($this->root, [], '8.4.1');

        $this->assertFalse($this->find($checks, 'config')['ok']);
        $this->assertFalse($this->find($checks, 'config')['required']);
        $this->assertFalse($this->find($checks, 'https')['ok']);
        $this->assertFalse($this->find($checks, 'https')['required']);
    }

    public function testCanWriteLocalConfig(): void
    {
        $this->assertTrue(Requirements::canWriteLocalConfig($this->root));

        touch($this->root . '/' . LocalConfig::FILENAME);
        $this->assertFalse(Requirements::canWriteLocalConfig($this->root));
        $this->assertFalse(Requirements::canWriteLocalConfig($this->root . '/gibt-es-nicht'));
    }

    /**
     * @param list<array{id: string, label: string, ok: bool, required: bool, detail: string}> $checks
     *
     * @return array{id: string, label: string, ok: bool, required: bool, detail: string}
     */
    private function find(array $checks, string $id): array
    {
        foreach ($checks as $check) {
            if ($check['id'] === $id) {
                return $check;
            }
        }
        $this->fail('Prüfung ' . $id . ' fehlt');
    }
}
