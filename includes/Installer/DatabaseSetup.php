<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Datenbank einrichten (Web-Installer)
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use PDO;
use PDOException;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * Verbindungstest, Prüfung auf bestehende Tabellen und das eigentliche
 * Einspielen von Schema, Forum-Einstellungen und Administrator.
 *
 * @phpstan-import-type MysqlConfig from LocalConfig
 */
final class DatabaseSetup
{
    public const int CONNECT_TIMEOUT = 5;

    public const string TABLE_PREFIX = 'ppb_';

    /**
     * Baut eine eigene PDO-Verbindung (nicht das Database-Singleton), damit
     * der Installer beliebige Zugangsdaten testen kann.
     *
     * @param MysqlConfig $config
     */
    public static function connect(#[SensitiveParameter] array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['server'],
            $config['port'],
            $config['database']
        );

        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => self::CONNECT_TIMEOUT,
        ]);
    }

    /**
     * Vorhandene Tabellen mit dem Präfix ppb_ (nur MySQL/MariaDB).
     *
     * @return list<string>
     */
    public static function existingTables(PDO $pdo): array
    {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ppb\\_%'");
        if ($stmt === false) {
            return [];
        }

        $tables = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (is_string($table)) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * Ist PowerPHPBoard in dieser Datenbank eingerichtet?
     *
     * @return bool|null true = ppb_config hat mindestens eine Zeile,
     *                   false = Tabelle fehlt oder ist leer,
     *                   null = unklar (z. B. fehlende Rechte)
     */
    public static function hasConfigRow(PDO $pdo): ?bool
    {
        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM ppb_config');
        } catch (PDOException $e) {
            return self::sqlState($e) === '42S02' ? false : null;
        }

        return $stmt !== false && (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Spielt Schema, Forum-Einstellungen und Administrator ein.
     *
     * Schlägt ein Schritt fehl, werden die in diesem Lauf angelegten Tabellen
     * wieder entfernt, damit ein erneuter Versuch auf einer leeren Datenbank
     * beginnt. Bestehende Tabellen werden nie angefasst.
     *
     * @param list<string> $statements
     * @param array{boardtitle: string, boardurl: string, adminemail: string, language: string} $forum
     * @param array{username: string, email: string, password_hash: string} $admin
     *
     * @return int ID des Administrators
     *
     * @throws PDOException bei Datenbankfehlern (angelegte Tabellen sind dann wieder entfernt)
     * @throws UnexpectedValueException bei fehlender Board-URL oder unvollständigem Schema
     */
    public static function install(
        PDO $pdo,
        array $statements,
        array $forum,
        #[SensitiveParameter]
        array $admin,
        int $now
    ): int {
        // Ohne Board-URL bauen Mails (z. B. Passwort-Reset) ihre Links aus dem
        // Host-Header der Anfrage – deshalb ist sie Pflicht.
        if (!FormValidator::isWebUrl($forum['boardurl'])) {
            throw new UnexpectedValueException('Die Adresse des Forums fehlt oder ist ungültig. Bitte prüfen Sie Schritt 3.');
        }

        $created = [];

        try {
            foreach ($statements as $statement) {
                $pdo->exec($statement);
                $table = Schema::createdTable($statement);
                if ($table !== null) {
                    $created[] = $table;
                }
            }

            $pdo->beginTransaction();
            $adminId = self::saveSettingsAndAdmin($pdo, $forum, $admin, $now);
            $pdo->commit();

            return $adminId;
        } catch (PDOException|UnexpectedValueException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::dropTables($pdo, $created);
            throw $e;
        }
    }

    /**
     * Verständliche Fehlermeldung ohne Zugangsdaten (die Originalmeldung von
     * MySQL nennt u. a. Benutzer und Host und wird deshalb nie angezeigt).
     */
    public static function friendlyError(PDOException $e): string
    {
        $code = self::driverCode($e);

        return match ($code) {
            1045, 1698 => 'Die Anmeldung am Datenbankserver ist fehlgeschlagen: Benutzername oder Passwort ist falsch.',
            1044 => 'Der Benutzer hat keine Berechtigung für diese Datenbank.',
            1049 => 'Die Datenbank existiert nicht. Bitte legen Sie sie zuerst an (z. B. im Kundenmenü Ihres Hosters) oder prüfen Sie den Namen.',
            1130 => 'Der Datenbankserver lässt keine Verbindungen von diesem Webserver zu.',
            2002 => 'Der Datenbankserver ist nicht erreichbar. Bitte prüfen Sie Server und Port.',
            2005 => 'Der Datenbankserver ist unbekannt. Bitte prüfen Sie die Schreibweise.',
            2006, 2013 => 'Die Verbindung zum Datenbankserver wurde unterbrochen. Bitte versuchen Sie es erneut.',
            1142, 1227 => 'Dem Datenbank-Benutzer fehlen Rechte (benötigt werden CREATE, DROP, SELECT, INSERT, UPDATE, DELETE, INDEX, ALTER).',
            1366 => 'Eine Eingabe enthält Zeichen, die die Datenbank nicht speichern kann.',
            0 => 'Die Datenbank ist nicht erreichbar oder hat die Anfrage abgelehnt.',
            default => sprintf('Die Datenbank hat die Anfrage abgelehnt (Fehlercode %d).', $code),
        };
    }

    /**
     * MySQL-Fehlernummer (z. B. 1045) aus der Exception, 0 wenn unbekannt.
     */
    public static function driverCode(PDOException $e): int
    {
        $info = $e->errorInfo;
        if (is_array($info) && isset($info[1]) && is_numeric($info[1])) {
            return (int) $info[1];
        }

        if (preg_match('/SQLSTATE\[[0-9A-Z]{5}\]\s*\[(\d+)\]/', $e->getMessage(), $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/SQLSTATE\[[0-9A-Z]{5}\]:[^:]*:\s*(\d+)\s/', $e->getMessage(), $match) === 1) {
            return (int) $match[1];
        }

        return 0;
    }

    /**
     * SQLSTATE (z. B. 42S02) aus der Exception, leer wenn unbekannt.
     */
    public static function sqlState(PDOException $e): string
    {
        $info = $e->errorInfo;
        if (is_array($info) && isset($info[0]) && is_string($info[0])) {
            return $info[0];
        }

        return preg_match('/SQLSTATE\[([0-9A-Z]{5})\]/', $e->getMessage(), $match) === 1 ? $match[1] : '';
    }

    /**
     * Forum-Einstellungen setzen und Administrator anlegen (innerhalb der
     * Transaktion von install()).
     *
     * @param array{boardtitle: string, boardurl: string, adminemail: string, language: string} $forum
     * @param array{username: string, email: string, password_hash: string} $admin
     */
    private static function saveSettingsAndAdmin(PDO $pdo, array $forum, #[SensitiveParameter] array $admin, int $now): int
    {
        $check = $pdo->query('SELECT COUNT(*) FROM ppb_config WHERE id = 1');
        $rows = $check === false ? 0 : (int) $check->fetchColumn();
        if ($check !== false) {
            $check->closeCursor();
        }
        if ($rows !== 1) {
            throw new UnexpectedValueException('Die Schemadatei install.sql enthält keine Forum-Einstellungen (ppb_config mit id = 1).');
        }

        // HTML in Beiträgen bleibt aus Sicherheitsgründen aus (XSS),
        // unabhängig davon, was in install.sql steht.
        $stmt = $pdo->prepare(
            "UPDATE ppb_config SET boardtitle = ?, boardurl = ?, adminemail = ?, language = ?,
                    htmlcode = 'OFF', bbcode = 'ON', smilies = 'ON'
             WHERE id = 1"
        );
        $stmt->execute([$forum['boardtitle'], $forum['boardurl'], $forum['adminemail'], $forum['language']]);
        $stmt->closeCursor();

        return AdminAccount::create($pdo, $admin['username'], $admin['email'], $admin['password_hash'], $now);
    }

    /**
     * @param list<string> $tables
     */
    private static function dropTables(PDO $pdo, array $tables): void
    {
        foreach (array_reverse($tables) as $table) {
            if (preg_match('/^' . self::TABLE_PREFIX . '[a-z_]+$/', $table) !== 1) {
                continue;
            }
            try {
                $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
            } catch (PDOException) {
                // Aufräumen ist bestmöglich; der ursprüngliche Fehler zählt.
                continue;
            }
        }
    }
}
