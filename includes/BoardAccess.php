<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Zugang zu privaten Boards
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

/**
 * Passwortschutz privater Boards.
 *
 * - ppb_boards.password enthält einen Argon2id-Hash (wie Benutzerpasswörter).
 *   Alte Base64-Werte werden beim ersten richtigen Passwort umgeschrieben.
 * - Wer das Passwort kennt, bekommt einen Zugangsnachweis: in der Sitzung
 *   und für angemeldete Benutzer dauerhaft in ppb_visits.password. Der
 *   Nachweis ist ein HMAC über Benutzer und Board mit dem gespeicherten Hash
 *   als Schlüssel – nie das Passwort selbst. Ein neues Board-Passwort macht
 *   damit alle bisherigen Nachweise ungültig.
 * - Jede Seite, die Inhalte eines privaten Boards zeigt (Themenliste, Thema,
 *   Antwort- und Zitatformular), fragt check() bzw. hasAccess().
 */
final class BoardAccess
{
    public const GRANTED = 'granted';

    public const REQUIRED = 'required';

    public const WRONG_PASSWORD = 'wrong';

    public const LOCKED = 'locked';

    public const RATE_LIMIT_ACTION = 'boardpwd';

    private const SESSION_KEY = 'ppb_board_access';

    /**
     * @param array<string, mixed> $board
     */
    public static function isPrivate(array $board): bool
    {
        return ($board['status'] ?? '') === 'Private';
    }

    public static function hashPassword(string $password): string
    {
        return Security::hashPassword($password);
    }

    /**
     * Prüft ein eingegebenes Board-Passwort gegen den gespeicherten Wert
     * (Argon2id/bcrypt oder Base64 aus älteren Versionen).
     */
    public static function verifyPassword(string $password, string $stored): bool
    {
        if ($password === '' || $stored === '') {
            return false;
        }

        return Security::verifyPassword($password, $stored);
    }

    /**
     * Zugangsnachweis für Benutzer und Board, abhängig vom gespeicherten Hash
     */
    public static function grantToken(int $userId, int $boardId, string $storedHash): string
    {
        return hash_hmac('sha256', $userId . ':' . $boardId, $storedHash);
    }

    /**
     * Hat der Besucher bereits Zugang (ohne erneute Passworteingabe)?
     *
     * @param array<string, mixed> $board
     * @param array<string, mixed>|null $user angemeldeter Benutzer oder null
     */
    public static function hasAccess(array $board, ?array $user, Database $db): bool
    {
        if (!self::isPrivate($board)) {
            return true;
        }

        $boardId = (int) ($board['id'] ?? 0);
        $stored = (string) ($board['password'] ?? '');
        if ($boardId <= 0 || $stored === '') {
            return false;
        }

        return self::hasSessionGrant($boardId, $stored)
            || ($user !== null && Auth::isActive($user) && self::hasStoredGrant($user, $boardId, $stored, $db));
    }

    private static function hasSessionGrant(int $boardId, string $storedHash): bool
    {
        $grants = Session::get(self::SESSION_KEY, []);
        $token = is_array($grants) ? ($grants[$boardId] ?? null) : null;

        return is_string($token) && hash_equals(self::grantToken(0, $boardId, $storedHash), $token);
    }

    /**
     * Dauerhafter Zugangsnachweis eines angemeldeten Benutzers aus ppb_visits
     *
     * @param array<string, mixed> $user
     */
    private static function hasStoredGrant(array $user, int $boardId, string $storedHash, Database $db): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        $visit = $db->fetchOne(
            "SELECT id, password FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
            [$userId, $boardId]
        );
        $saved = (string) ($visit['password'] ?? '');
        if ($visit === null || $saved === '') {
            return false;
        }

        if (hash_equals(self::grantToken($userId, $boardId, $storedHash), $saved)) {
            self::rememberInSession($boardId, $storedHash);

            return true;
        }

        // Veralteter Eintrag, z. B. das Board-Passwort als Base64 aus älteren
        // Versionen oder ein Nachweis für ein inzwischen geändertes Passwort
        $db->query('UPDATE ppb_visits SET password = ? WHERE id = ?', ['', $visit['id']]);

        return false;
    }

    /**
     * Zugang prüfen und bei richtigem Passwort freischalten.
     *
     * @param array<string, mixed> $board
     * @param array<string, mixed>|null $user angemeldeter Benutzer oder null
     * @param string|null $submittedPassword null = kein Passwort abgeschickt
     *
     * @return string Eine der Konstanten GRANTED, REQUIRED, WRONG_PASSWORD, LOCKED
     */
    public static function check(
        array $board,
        ?array $user,
        Database $db,
        ?string $submittedPassword,
        ?RateLimiter $limiter = null,
        string $clientId = ''
    ): string {
        if (self::hasAccess($board, $user, $db)) {
            return self::GRANTED;
        }
        if ($submittedPassword === null) {
            return self::REQUIRED;
        }

        // Fehlversuche zählen je Board und Client: Das richtige Passwort eines
        // Boards setzt nur dessen Zähler zurück, nie den eines anderen Boards
        $boardId = (int) ($board['id'] ?? 0);
        $limitKey = self::rateLimitKey($clientId, $boardId);
        if ($limiter !== null && !$limiter->check(self::RATE_LIMIT_ACTION, $limitKey)) {
            return self::LOCKED;
        }

        $stored = (string) ($board['password'] ?? '');
        if (!self::verifyPassword($submittedPassword, $stored)) {
            $limiter?->recordFailure(self::RATE_LIMIT_ACTION, $limitKey);

            return self::WRONG_PASSWORD;
        }

        $limiter?->recordSuccess(self::RATE_LIMIT_ACTION, $limitKey);
        if (Security::needsRehash($stored)) {
            // Base64 aus älteren Versionen durch einen echten Hash ersetzen und
            // die dabei in ppb_visits gespeicherten Passwortkopien verwerfen
            $stored = self::hashPassword($submittedPassword);
            $db->query('UPDATE ppb_boards SET password = ? WHERE id = ?', [$stored, $boardId]);
            self::revokeAll($boardId, $db);
        }

        self::grant($boardId, $stored, $user, $db);

        return self::GRANTED;
    }

    /**
     * Schlüssel für den Fehlversuchszähler: Client (IP-Adresse) und Board
     */
    public static function rateLimitKey(string $clientId, int $boardId): string
    {
        return $clientId . '|board:' . $boardId;
    }

    /**
     * Zugangsnachweis in der Sitzung und (für angemeldete Benutzer) in ppb_visits ablegen
     *
     * @param array<string, mixed>|null $user
     */
    public static function grant(int $boardId, string $storedHash, ?array $user, Database $db): void
    {
        self::rememberInSession($boardId, $storedHash);

        if ($user === null || !Auth::isActive($user)) {
            return;
        }

        $userId = (int) ($user['id'] ?? 0);
        $token = self::grantToken($userId, $boardId, $storedHash);
        $visit = $db->fetchOne(
            "SELECT id FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
            [$userId, $boardId]
        );
        if ($visit !== null) {
            $db->query('UPDATE ppb_visits SET password = ? WHERE id = ?', [$token, $visit['id']]);

            return;
        }
        $db->query(
            "INSERT INTO ppb_visits (userid, vid, time, type, password) VALUES (?, ?, ?, 'Board', ?)",
            [$userId, $boardId, time(), $token]
        );
    }

    /**
     * Alle gespeicherten Zugangsnachweise eines Boards verwerfen
     * (nach Passwortänderung oder wenn das Board nicht mehr privat ist)
     */
    public static function revokeAll(int $boardId, Database $db): void
    {
        $db->query("UPDATE ppb_visits SET password = '' WHERE vid = ? AND type = 'Board'", [$boardId]);
    }

    private static function rememberInSession(int $boardId, string $storedHash): void
    {
        $grants = Session::get(self::SESSION_KEY, []);
        if (!is_array($grants)) {
            $grants = [];
        }
        $grants[$boardId] = self::grantToken(0, $boardId, $storedHash);
        Session::set(self::SESSION_KEY, $grants);
    }
}
