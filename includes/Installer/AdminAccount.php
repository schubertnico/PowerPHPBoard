<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Administrator-Konten anlegen
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use PDO;
use SensitiveParameter;

/**
 * Gemeinsame Datenbanklogik für den Web-Installer und das
 * CLI-Notfallwerkzeug bin/create-admin.php.
 */
final class AdminAccount
{
    public const string STATUS_ADMIN = 'Administrator';

    /**
     * Legt einen Administrator an und liefert seine ID.
     */
    public static function create(
        PDO $pdo,
        string $username,
        string $email,
        #[SensitiveParameter]
        string $passwordHash,
        int $now
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO ppb_users (username, email, password, homepage, icq, biography, signature, hideemail, logincookie, status, registered, lastvisit)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $passwordHash, '', '', '', '', 'YES', 'YES', self::STATUS_ADMIN, $now, 0]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Macht ein bestehendes Konto zum Administrator und setzt ein neues Passwort.
     */
    public static function promote(PDO $pdo, int $userId, #[SensitiveParameter] string $passwordHash): void
    {
        $stmt = $pdo->prepare('UPDATE ppb_users SET status = ?, password = ? WHERE id = ?');
        $stmt->execute([self::STATUS_ADMIN, $passwordHash, $userId]);
    }

    /**
     * @return array{id: int, username: string, status: string}|null
     */
    public static function findByEmail(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare('SELECT id, username, status FROM ppb_users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'status' => (string) $row['status'],
        ];
    }

    public static function usernameTaken(PDO $pdo, string $username): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ppb_users WHERE username = ?');
        $stmt->execute([$username]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
