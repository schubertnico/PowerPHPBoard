<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Systemprüfung des Web-Installers
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

/**
 * Schritt 1 des Web-Installers: prüft PHP-Version, Erweiterungen und
 * Schreibrechte.
 *
 * @phpstan-type Check array{id: string, label: string, ok: bool, required: bool, detail: string}
 */
final class Requirements
{
    public const string MIN_PHP = '8.4.0';

    /**
     * @param array<array-key, mixed> $server $_SERVER
     *
     * @return list<Check>
     */
    public static function check(string $rootDir, array $server, string $phpVersion = PHP_VERSION): array
    {
        $phpOk = self::phpVersionOk($phpVersion);
        $pdoOk = extension_loaded('pdo_mysql');
        $mbOk = extension_loaded('mbstring');
        $sslOk = extension_loaded('openssl');
        $schemaOk = is_readable($rootDir . '/' . Schema::FILENAME);
        $logsOk = is_dir($rootDir . '/logs') && is_writable($rootDir . '/logs');
        $configOk = self::canWriteLocalConfig($rootDir);
        $httpsOk = self::isHttps($server);

        return [
            [
                'id' => 'php',
                'label' => 'PHP ' . self::MIN_PHP . ' oder neuer',
                'ok' => $phpOk,
                'required' => true,
                'detail' => 'Gefunden: PHP ' . $phpVersion . '.',
            ],
            [
                'id' => 'pdo_mysql',
                'label' => 'PHP-Erweiterung pdo_mysql',
                'ok' => $pdoOk,
                'required' => true,
                'detail' => $pdoOk ? 'Vorhanden.' : 'Fehlt – bitte beim Hoster bzw. in der php.ini aktivieren.',
            ],
            [
                'id' => 'mbstring',
                'label' => 'PHP-Erweiterung mbstring',
                'ok' => $mbOk,
                'required' => true,
                'detail' => $mbOk ? 'Vorhanden.' : 'Fehlt – bitte beim Hoster bzw. in der php.ini aktivieren.',
            ],
            [
                'id' => 'openssl',
                'label' => 'PHP-Erweiterung openssl (verschlüsselter Mailversand)',
                'ok' => $sslOk,
                'required' => false,
                'detail' => $sslOk
                    ? 'Vorhanden – E-Mails können per STARTTLS oder SSL/TLS verschlüsselt verschickt werden.'
                    : 'Fehlt – E-Mails lassen sich dann nur unverschlüsselt verschicken, was die meisten Mailserver ablehnen. Bitte beim Hoster bzw. in der php.ini aktivieren.',
            ],
            [
                'id' => 'schema',
                'label' => 'Schemadatei install.sql lesbar',
                'ok' => $schemaOk,
                'required' => true,
                'detail' => $schemaOk ? 'Vorhanden.' : 'Bitte install.sql vollständig in das Forumverzeichnis hochladen.',
            ],
            [
                'id' => 'logs',
                'label' => 'Verzeichnis logs/ beschreibbar',
                'ok' => $logsOk,
                'required' => true,
                'detail' => $logsOk
                    ? 'Beschreibbar.'
                    : 'Bitte die Rechte von logs/ anpassen (z. B. 775 bzw. per FTP-Programm „Schreibrechte für Gruppe“).',
            ],
            [
                'id' => 'config',
                'label' => 'Forumverzeichnis beschreibbar (für config.local.php)',
                'ok' => $configOk,
                'required' => false,
                'detail' => $configOk
                    ? 'Der Installer legt config.local.php selbst an.'
                    : 'Nicht beschreibbar – kein Problem: Am Ende zeigt der Installer den Inhalt der Datei an, damit Sie sie selbst hochladen können.',
            ],
            [
                'id' => 'https',
                'label' => 'Verschlüsselte Verbindung (HTTPS)',
                'ok' => $httpsOk,
                'required' => false,
                'detail' => $httpsOk
                    ? 'Die Verbindung ist verschlüsselt.'
                    : 'Die Seite wurde ohne HTTPS aufgerufen. Zugangsdaten werden dann unverschlüsselt übertragen – rufen Sie den Installer nach Möglichkeit über https:// auf.',
            ],
        ];
    }

    /**
     * @param list<Check> $checks
     */
    public static function allRequiredMet(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && !$check['ok']) {
                return false;
            }
        }

        return true;
    }

    public static function phpVersionOk(string $version): bool
    {
        return version_compare($version, self::MIN_PHP, '>=');
    }

    /**
     * @param array<array-key, mixed> $server $_SERVER
     */
    public static function isHttps(array $server): bool
    {
        $https = $server['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return (string) ($server['SERVER_PORT'] ?? '') === '443';
    }

    public static function canWriteLocalConfig(string $rootDir): bool
    {
        return !file_exists($rootDir . '/' . LocalConfig::FILENAME) && is_writable($rootDir);
    }
}
