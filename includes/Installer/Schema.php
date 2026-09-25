<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Datenbankschema (install.sql) einlesen
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use RuntimeException;

/**
 * Zerlegt install.sql in einzelne Anweisungen, damit Web-Installer und
 * `mysql < install.sql` dieselbe Schemaquelle verwenden.
 *
 * Kommentare (`# …`, `-- …`, `/* … *\/`) werden entfernt, Semikolons in
 * Zeichenketten und Bezeichnern bleiben erhalten.
 */
final class Schema
{
    public const string FILENAME = 'install.sql';

    /**
     * @return list<string>
     */
    public static function fromFile(string $path): array
    {
        $sql = is_readable($path) ? file_get_contents($path) : false;
        if ($sql === false) {
            throw new RuntimeException('Die Schemadatei ' . basename($path) . ' ist nicht lesbar.');
        }

        return self::split($sql);
    }

    /**
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        if (str_starts_with($sql, "\xEF\xBB\xBF")) {
            $sql = substr($sql, 3);
        }

        $statements = [];
        $current = '';
        $length = strlen($sql);
        $pos = 0;

        while ($pos < $length) {
            $char = $sql[$pos];

            $commentEnd = self::commentEnd($sql, $pos);
            if ($commentEnd !== null) {
                $current .= ' ';
                $pos = $commentEnd;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $end = self::quotedEnd($sql, $pos);
                $current .= substr($sql, $pos, $end - $pos);
                $pos = $end;
                continue;
            }

            if ($char === ';') {
                self::addStatement($statements, $current);
                $current = '';
                $pos++;
                continue;
            }

            $current .= $char;
            $pos++;
        }

        self::addStatement($statements, $current);

        return $statements;
    }

    /**
     * Name der Tabelle, die eine CREATE-TABLE-Anweisung anlegt, sonst null.
     */
    public static function createdTable(string $statement): ?string
    {
        $pattern = '/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i';
        if (preg_match($pattern, $statement, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * @param list<string> $statements
     *
     * @return list<string>
     */
    public static function tableNames(array $statements): array
    {
        $tables = [];
        foreach ($statements as $statement) {
            $table = self::createdTable($statement);
            if ($table !== null) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * @param list<string> $statements
     */
    private static function addStatement(array &$statements, string $statement): void
    {
        $statement = trim($statement);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }

    /**
     * Beginnt an $pos ein Kommentar, liefert die Position direkt dahinter
     * (bei Zeilenkommentaren: den Zeilenumbruch), sonst null.
     */
    private static function commentEnd(string $sql, int $pos): ?int
    {
        $char = $sql[$pos];
        $next = $sql[$pos + 1] ?? '';

        $isLineComment = $char === '#'
            || ($char === '-' && $next === '-' && in_array($sql[$pos + 2] ?? "\n", [' ', "\t", "\r", "\n"], true));

        if ($isLineComment) {
            $end = strpos($sql, "\n", $pos);
            return $end === false ? strlen($sql) : $end;
        }

        if ($char === '/' && $next === '*') {
            $end = strpos($sql, '*/', $pos + 2);
            return $end === false ? strlen($sql) : $end + 2;
        }

        return null;
    }

    /**
     * Position direkt hinter dem schließenden Anführungszeichen. Berücksichtigt
     * Backslash-Escapes und verdoppelte Anführungszeichen.
     */
    private static function quotedEnd(string $sql, int $start): int
    {
        $quote = $sql[$start];
        $length = strlen($sql);
        $pos = $start + 1;

        while ($pos < $length) {
            $char = $sql[$pos];

            if ($char === '\\' && $quote !== '`') {
                $pos += 2;
                continue;
            }

            if ($char === $quote) {
                if (($sql[$pos + 1] ?? '') === $quote) {
                    $pos += 2;
                    continue;
                }
                return $pos + 1;
            }

            $pos++;
        }

        return $length;
    }
}
