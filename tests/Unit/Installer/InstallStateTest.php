<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\InstallState;
use PowerPHPBoard\Installer\LocalConfig;

final class InstallStateTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ppb-installstate-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/install', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach ([InstallState::LOCK_FILE, InstallState::INSTALLER_ENTRY, LocalConfig::FILENAME] as $file) {
            if (is_file($this->root . '/' . $file)) {
                unlink($this->root . '/' . $file);
            }
        }
        rmdir($this->root . '/install');
        rmdir($this->root);
    }

    /**
     * @return array<string, array{bool, bool, ?bool, bool, ?string}>
     */
    public static function lockMatrix(): array
    {
        return [
            'frisch, Vorgaben, keine Datenbank' => [false, false, null, true, null],
            'frisch, Datenbank erreichbar und leer' => [false, false, false, true, null],
            'eigene Zugangsdaten, Datenbank leer' => [false, false, false, false, null],
            'Sperrdatei' => [true, false, null, true, InstallState::REASON_LOCK_FILE],
            'Sperrdatei hat Vorrang' => [true, true, true, false, InstallState::REASON_LOCK_FILE],
            'config.local.php' => [false, true, null, true, InstallState::REASON_LOCAL_CONFIG],
            'Datenbank eingerichtet' => [false, false, true, true, InstallState::REASON_DATABASE],
            'Datenbank eingerichtet, eigene Zugangsdaten' => [false, false, true, false, InstallState::REASON_DATABASE],
            'eigene Zugangsdaten, Datenbank ausgefallen' => [false, false, null, false, InstallState::REASON_UNREACHABLE],
        ];
    }

    #[DataProvider('lockMatrix')]
    public function testLockReason(bool $lockFile, bool $localConfig, ?bool $dbInstalled, bool $defaultConfig, ?string $expected): void
    {
        $this->assertSame($expected, InstallState::lockReason($lockFile, $localConfig, $dbInstalled, $defaultConfig));
    }

    public function testLockFileLocksWithoutAskingTheDatabase(): void
    {
        touch($this->root . '/' . InstallState::LOCK_FILE);

        $reason = InstallState::detectLockReason($this->root, LocalConfig::DEFAULT_MYSQL, $this->failingProbe());

        $this->assertSame(InstallState::REASON_LOCK_FILE, $reason);
    }

    public function testLocalConfigLocksWithoutAskingTheDatabase(): void
    {
        touch($this->root . '/' . LocalConfig::FILENAME);

        $reason = InstallState::detectLockReason($this->root, LocalConfig::DEFAULT_MYSQL, $this->failingProbe());

        $this->assertSame(InstallState::REASON_LOCAL_CONFIG, $reason);
    }

    public function testInstalledDatabaseLocks(): void
    {
        $reason = InstallState::detectLockReason($this->root, LocalConfig::DEFAULT_MYSQL, static fn (): ?bool => true);

        $this->assertSame(InstallState::REASON_DATABASE, $reason);
    }

    public function testFreshSetupIsOpen(): void
    {
        $unreachable = InstallState::detectLockReason($this->root, LocalConfig::DEFAULT_MYSQL, static fn (): ?bool => null);
        $empty = InstallState::detectLockReason($this->root, LocalConfig::DEFAULT_MYSQL, static fn (): ?bool => false);

        $this->assertNull($unreachable);
        $this->assertNull($empty);
    }

    public function testConfiguredButUnreachableDatabaseLocks(): void
    {
        $mysql = ['server' => 'db', 'port' => 3306, 'user' => 'forum', 'password' => 'x', 'database' => 'forum'];

        $reason = InstallState::detectLockReason($this->root, $mysql, static fn (): ?bool => null);

        $this->assertSame(InstallState::REASON_UNREACHABLE, $reason);
    }

    public function testNoRedirectWithoutInstaller(): void
    {
        $this->assertFalse(InstallState::shouldRedirectToInstaller($this->root, LocalConfig::DEFAULT_MYSQL));
    }

    public function testNoRedirectWhenLocked(): void
    {
        touch($this->root . '/' . InstallState::INSTALLER_ENTRY);
        touch($this->root . '/' . InstallState::LOCK_FILE);

        $this->assertFalse(InstallState::shouldRedirectToInstaller($this->root, LocalConfig::DEFAULT_MYSQL));
    }

    public function testNoRedirectWhenLocalConfigExists(): void
    {
        touch($this->root . '/' . InstallState::INSTALLER_ENTRY);
        touch($this->root . '/' . LocalConfig::FILENAME);

        $this->assertFalse(InstallState::shouldRedirectToInstaller($this->root, LocalConfig::DEFAULT_MYSQL));
    }

    public function testWriteLockFile(): void
    {
        $this->assertTrue(InstallState::writeLockFile($this->root, '2026-09-25 12:00:00'));
        $this->assertSame(
            "Installiert am 2026-09-25 12:00:00\n",
            file_get_contents($this->root . '/' . InstallState::LOCK_FILE)
        );
    }

    public function testWriteLockFileFailsWithoutInstallDirectory(): void
    {
        $this->assertFalse(InstallState::writeLockFile($this->root . '/fehlt', '2026-09-25 12:00:00'));
    }

    /**
     * @return callable(): ?bool
     */
    private function failingProbe(): callable
    {
        return function (): ?bool {
            $this->fail('Die Datenbank darf nicht befragt werden.');
        };
    }
}
