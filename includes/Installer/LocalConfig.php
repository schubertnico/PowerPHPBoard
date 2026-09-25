<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Lokale Konfiguration (config.local.php)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use SensitiveParameter;

/**
 * Erzeugt, liest und prüft die vom Web-Installer geschriebene config.local.php.
 *
 * Rangfolge der Konfiguration (höchste zuerst):
 *   1. config.local.php  – vom Web-Installer geschrieben oder von Hand angelegt
 *   2. Umgebungsvariablen – PPB_DB_*, PPB_MAIL_* (Docker, SetEnv, PHP-FPM)
 *   3. Vorgaben          – DEFAULT_MYSQL / DEFAULT_MAIL
 *
 * Die Datei liefert per `return` ein Array; sie setzt keine globalen Variablen.
 *
 * @phpstan-type MysqlConfig array{server: string, port: int, user: string, password: string, database: string}
 * @phpstan-type MailConfig array{host: string, port: int, from: string}
 */
final class LocalConfig
{
    public const string FILENAME = 'config.local.php';

    /**
     * Vorgaben ohne Umgebungsvariablen und ohne config.local.php.
     */
    public const array DEFAULT_MYSQL = [
        'server' => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'password' => '',
        'database' => 'PowerPHPBoard_v2',
    ];

    public const array DEFAULT_MAIL = [
        'host' => 'mailpit',
        'port' => 1025,
        'from' => 'noreply@powerphpboard.local',
    ];

    /**
     * Überlagert die Werte aus Umgebungsvariablen/Vorgaben mit dem Inhalt
     * der config.local.php. Unbekannte Schlüssel und Werte mit falschem Typ
     * werden ignoriert.
     *
     * @param MysqlConfig $mysql
     * @param MailConfig $mail
     *
     * @return array{mysql: MysqlConfig, mail: MailConfig}
     */
    public static function apply(
        #[SensitiveParameter]
        array $mysql,
        array $mail,
        #[SensitiveParameter]
        mixed $local
    ): array {
        if (!is_array($local)) {
            return ['mysql' => $mysql, 'mail' => $mail];
        }

        $mysqlOverride = is_array($local['mysql'] ?? null) ? $local['mysql'] : [];
        $mailOverride = is_array($local['mail'] ?? null) ? $local['mail'] : [];

        return [
            'mysql' => [
                'server' => self::stringOr($mysqlOverride, 'server', $mysql['server']),
                'port' => self::portOr($mysqlOverride, 'port', $mysql['port']),
                'user' => self::stringOr($mysqlOverride, 'user', $mysql['user']),
                'password' => self::stringOr($mysqlOverride, 'password', $mysql['password']),
                'database' => self::stringOr($mysqlOverride, 'database', $mysql['database']),
            ],
            'mail' => [
                'host' => self::stringOr($mailOverride, 'host', $mail['host']),
                'port' => self::portOr($mailOverride, 'port', $mail['port']),
                'from' => self::stringOr($mailOverride, 'from', $mail['from']),
            ],
        ];
    }

    /**
     * Entspricht die Datenbank-Konfiguration den ausgelieferten Vorgaben?
     * Dann wurde das Forum weder per Umgebungsvariablen noch per angepasster
     * config.inc.php eingerichtet.
     *
     * @param array<array-key, mixed> $mysql
     */
    public static function isDefaultDatabase(#[SensitiveParameter] array $mysql): bool
    {
        foreach (self::DEFAULT_MYSQL as $key => $default) {
            if ($key === 'port' && !array_key_exists('port', $mysql)) {
                // config.inc.php aus 2.2.x kennt noch keinen Port
                continue;
            }
            if (($mysql[$key] ?? null) !== $default) {
                return false;
            }
        }

        return true;
    }

    /**
     * Erzeugt den PHP-Quelltext der config.local.php.
     *
     * Alle Werte werden per var_export() als PHP-Literale geschrieben. So
     * bleiben Sonderzeichen in Passwörtern (' " \ $ ?> Zeilenumbrüche …)
     * unverändert und können den Code nicht verändern.
     *
     * @param MysqlConfig $mysql
     * @param MailConfig|null $mail null = Mail-Einstellungen nicht festschreiben
     */
    public static function render(
        #[SensitiveParameter]
        array $mysql,
        ?array $mail,
        string $generatedAt
    ): string {
        $generatedAt = (string) preg_replace('/[^0-9: -]/', '', $generatedAt);

        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            '/*',
            ' * PowerPHPBoard - lokale Konfiguration',
            ' *',
            ' * Erzeugt vom Web-Installer am ' . $generatedAt . '.',
            ' *',
            ' * Diese Datei enthält Zugangsdaten: nicht weitergeben und nicht in ein',
            ' * Repository einchecken. Werte hier haben Vorrang vor den',
            ' * Umgebungsvariablen PPB_DB_* und PPB_MAIL_*.',
            ' */',
            '',
            'return [',
            "    'mysql' => [",
            "        'server' => " . self::export($mysql['server']) . ',',
            "        'port' => " . self::export($mysql['port']) . ',',
            "        'user' => " . self::export($mysql['user']) . ',',
            "        'password' => " . self::export($mysql['password']) . ',',
            "        'database' => " . self::export($mysql['database']) . ',',
            '    ],',
        ];

        if ($mail !== null) {
            $lines[] = "    'mail' => [";
            $lines[] = "        'host' => " . self::export($mail['host']) . ',';
            $lines[] = "        'port' => " . self::export($mail['port']) . ',';
            $lines[] = "        'from' => " . self::export($mail['from']) . ',';
            $lines[] = '    ],';
        }

        $lines[] = '];';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Legt die Datei exklusiv an (niemals überschreiben) und setzt die Rechte
     * auf 0640. Liefert false, wenn das nicht möglich war.
     */
    public static function writeFile(string $path, #[SensitiveParameter] string $content): bool
    {
        if (file_exists($path) || !is_writable(dirname($path))) {
            return false;
        }

        $handle = @fopen($path, 'x');
        if ($handle === false) {
            return false;
        }

        $written = @fwrite($handle, $content);
        fclose($handle);

        if ($written !== strlen($content)) {
            @unlink($path);
            return false;
        }

        @chmod($path, 0o640);

        return true;
    }

    /**
     * @param array<array-key, mixed> $source
     */
    private static function stringOr(array $source, string $key, string $default): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<array-key, mixed> $source
     */
    private static function portOr(array $source, string $key, int $default): int
    {
        $value = $source[$key] ?? null;

        return is_int($value) && $value >= 1 && $value <= 65535 ? $value : $default;
    }

    private static function export(string|int $value): string
    {
        return var_export($value, true);
    }
}
