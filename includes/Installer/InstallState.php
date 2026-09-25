<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Installationszustand und Sperre des Web-Installers
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use PDOException;
use PowerPHPBoard\Database;
use SensitiveParameter;

/**
 * Entscheidet, ob der Web-Installer laufen darf.
 *
 * Der Installer ist gesperrt, sobald eines davon zutrifft:
 *   1. install/.installed existiert (schreibt der Installer am Ende)
 *   2. config.local.php existiert
 *   3. die konfigurierte Datenbank enthält eine ppb_config-Zeile
 *   4. es ist eine Datenbank konfiguriert (Umgebungsvariablen oder angepasste
 *      config.inc.php), die gerade nicht erreichbar ist – ein Datenbankausfall
 *      darf den Installer nicht wieder öffnen.
 *
 * Die Startseite leitet genau dann auf install/ weiter, wenn der Installer
 * vorhanden und nicht gesperrt ist.
 */
final class InstallState
{
    public const string LOCK_FILE = 'install/.installed';

    public const string INSTALLER_ENTRY = 'install/index.php';

    public const string REASON_LOCK_FILE = 'lockfile';

    public const string REASON_LOCAL_CONFIG = 'config';

    public const string REASON_DATABASE = 'database';

    public const string REASON_UNREACHABLE = 'unreachable';

    /**
     * Reine Entscheidungslogik.
     *
     * @param bool|null $databaseInstalled true = ppb_config hat eine Zeile,
     *                                     false = Datenbank erreichbar, aber leer,
     *                                     null = nicht erreichbar oder unklar
     * @param bool $defaultConfig Datenbank-Konfiguration entspricht den Vorgaben
     */
    public static function lockReason(
        bool $lockFileExists,
        bool $localConfigExists,
        ?bool $databaseInstalled,
        bool $defaultConfig
    ): ?string {
        if ($lockFileExists) {
            return self::REASON_LOCK_FILE;
        }
        if ($localConfigExists) {
            return self::REASON_LOCAL_CONFIG;
        }
        if ($databaseInstalled === true) {
            return self::REASON_DATABASE;
        }
        if ($databaseInstalled === null && !$defaultConfig) {
            return self::REASON_UNREACHABLE;
        }

        return null;
    }

    /**
     * Ermittelt die Sperre anhand der Dateien im Forumverzeichnis. Die
     * Datenbank wird nur befragt, wenn keine Datei die Frage schon beantwortet.
     *
     * @param array<array-key, mixed> $mysql wirksame Datenbank-Konfiguration
     * @param callable(): ?bool $probe prüft die konfigurierte Datenbank
     */
    public static function detectLockReason(
        string $rootDir,
        #[SensitiveParameter]
        array $mysql,
        callable $probe
    ): ?string {
        if (is_file($rootDir . '/' . self::LOCK_FILE)) {
            return self::REASON_LOCK_FILE;
        }
        if (is_file($rootDir . '/' . LocalConfig::FILENAME)) {
            return self::REASON_LOCAL_CONFIG;
        }

        return self::lockReason(false, false, $probe(), LocalConfig::isDefaultDatabase($mysql));
    }

    /**
     * Soll die Startseite auf den Installer weiterleiten? Nutzt das
     * Database-Singleton, damit header.inc.php die Verbindung weiterverwendet.
     *
     * @param array{server: string, user: string, password: string, database: string, port?: int} $mysql
     */
    public static function shouldRedirectToInstaller(string $rootDir, #[SensitiveParameter] array $mysql): bool
    {
        if (!is_file($rootDir . '/' . self::INSTALLER_ENTRY)) {
            return false;
        }

        $probe = static function () use ($mysql): ?bool {
            try {
                return DatabaseSetup::hasConfigRow(Database::getInstance($mysql)->getPdo());
            } catch (PDOException) {
                return null;
            }
        };

        return self::detectLockReason($rootDir, $mysql, $probe) === null;
    }

    /**
     * Schreibt die Sperrdatei (bestmöglich – ist install/ nicht beschreibbar,
     * greifen weiterhin config.local.php und die Datenbankprüfung).
     */
    public static function writeLockFile(string $rootDir, string $timestamp): bool
    {
        $file = $rootDir . '/' . self::LOCK_FILE;
        if (!is_writable(dirname($file))) {
            return false;
        }

        return @file_put_contents($file, 'Installiert am ' . $timestamp . "\n", LOCK_EX) !== false;
    }
}
