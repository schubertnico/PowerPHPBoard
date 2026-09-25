<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Angemeldeter Benutzer und Berechtigungen
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

/**
 * Zentrale Stelle für "Wer ist angemeldet?" und "Wer darf was?".
 *
 * Deaktivierte Konten gelten überall als nicht angemeldet: Sie können sich
 * nicht anmelden, und eine noch offene Sitzung wird beim nächsten Aufruf
 * beendet. Damit sind alle schreibenden Aktionen, die eine Anmeldung
 * voraussetzen, serverseitig gesperrt.
 */
final class Auth
{
    public const STATUS_ADMIN = 'Administrator';

    public const STATUS_NORMAL = 'Normal user';

    public const STATUS_DEACTIVATED = 'Deactivated';

    /**
     * Konto ist aktiv (normaler Benutzer oder Administrator)
     *
     * @param array<string, mixed> $user
     */
    public static function isActive(array $user): bool
    {
        return in_array($user['status'] ?? '', [self::STATUS_NORMAL, self::STATUS_ADMIN], true);
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isAdmin(?array $user): bool
    {
        return $user !== null && ($user['status'] ?? '') === self::STATUS_ADMIN;
    }

    /**
     * Angemeldeten, aktiven Benutzer laden.
     *
     * Existiert das Konto nicht mehr oder ist es deaktiviert, wird die
     * Sitzung beendet und null geliefert.
     *
     * @return array<string, mixed>|null
     */
    public static function currentUser(Database $db): ?array
    {
        $userId = Session::getUserId();
        if ($userId === null || $userId <= 0) {
            return null;
        }

        $user = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
        if ($user === null || !self::isActive($user)) {
            Session::logout();

            return null;
        }

        return $user;
    }

    /**
     * Benutzer ist als Moderator des Boards eingetragen (E-Mail in ppb_boards.mods)
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $board
     */
    public static function isModerator(?array $user, array $board): bool
    {
        if ($user === null) {
            return false;
        }
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email === '') {
            return false;
        }
        foreach (explode(',', (string) ($board['mods'] ?? '')) as $mod) {
            if (strtolower(trim($mod)) === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * Darf Themen schließen/öffnen/löschen, fremde Beiträge bearbeiten und IP-Adressen sehen
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $board
     */
    public static function canModerate(?array $user, array $board): bool
    {
        if ($user === null || !self::isActive($user)) {
            return false;
        }

        return self::isAdmin($user) || self::isModerator($user, $board);
    }

    /**
     * Prüft eine Statusänderung im Adminbereich.
     *
     * Ein Administrator darf sich nicht selbst herabstufen oder deaktivieren
     * (er sperrte sich sonst aus dem Adminbereich aus), und der letzte
     * Administrator bleibt immer Administrator.
     *
     * @param array<string, mixed> $actor Angemeldeter Administrator
     * @param array<string, mixed> $target Zu ändernder Benutzer
     * @param int $adminCount Anzahl der Administratoren vor der Änderung
     *
     * @return string|null 'self', 'lastadmin' oder null, wenn die Änderung erlaubt ist
     */
    public static function statusChangeError(array $actor, array $target, string $newStatus, int $adminCount): ?string
    {
        if (($target['status'] ?? '') !== self::STATUS_ADMIN || $newStatus === self::STATUS_ADMIN) {
            return null;
        }
        if ((int) ($actor['id'] ?? 0) === (int) ($target['id'] ?? -1)) {
            return 'self';
        }

        return $adminCount <= 1 ? 'lastadmin' : null;
    }

    /**
     * Darf den Beitrag bearbeiten: Autor, Moderator des Boards oder Administrator
     *
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $post
     * @param array<string, mixed> $board
     */
    public static function canEditPost(?array $user, array $post, array $board): bool
    {
        if ($user === null || !self::isActive($user)) {
            return false;
        }

        return (int) ($user['id'] ?? 0) === (int) ($post['author'] ?? -1)
            || self::canModerate($user, $board);
    }
}
