<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Themen und Antworten löschen
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

/**
 * Löscht Themen und Antworten und hält dabei die Zeiger auf den letzten
 * Beitrag aktuell:
 *
 * - ppb_boards.lastchange/lastauthor (Startseite: „Letzter Beitrag“)
 * - ppb_posts.lastreply/lastauthor des Themas (Themenliste, Sortierung)
 *
 * Beide werden nach dem Löschen aus den verbliebenen Beiträgen neu
 * berechnet. Ein Board ohne Beiträge bekommt 0/0; ein Thema ohne Antworten
 * zeigt wieder auf seinen Starterbeitrag – so wie beim Anlegen.
 */
final class PostDeletion
{
    /**
     * Thema mit allen Antworten löschen.
     */
    public static function deleteThread(Database $db, int $threadId, int $boardId): void
    {
        $db->query('DELETE FROM ppb_posts WHERE id = ? OR threadid = ?', [$threadId, $threadId]);
        $db->query("DELETE FROM ppb_visits WHERE vid = ? AND type = 'Thread'", [$threadId]);
        self::refreshBoard($db, $boardId);
    }

    /**
     * Einzelne Antwort löschen.
     */
    public static function deleteReply(Database $db, int $postId, int $threadId, int $boardId): void
    {
        $db->query("DELETE FROM ppb_posts WHERE id = ? AND type = 'Post'", [$postId]);
        self::refreshThread($db, $threadId);
        self::refreshBoard($db, $boardId);
    }

    /**
     * lastreply/lastauthor des Themas aus dem neuesten verbliebenen Beitrag.
     */
    public static function refreshThread(Database $db, int $threadId): void
    {
        $latest = $db->fetchOne(
            'SELECT time, author FROM ppb_posts WHERE id = ? OR threadid = ? ORDER BY time DESC, id DESC LIMIT 1',
            [$threadId, $threadId]
        );
        [$time, $author] = self::pointer($latest);
        $db->query('UPDATE ppb_posts SET lastreply = ?, lastauthor = ? WHERE id = ?', [$time, $author, $threadId]);
    }

    /**
     * lastchange/lastauthor des Boards aus dem neuesten verbliebenen Beitrag.
     */
    public static function refreshBoard(Database $db, int $boardId): void
    {
        $latest = $db->fetchOne(
            'SELECT time, author FROM ppb_posts WHERE boardid = ? ORDER BY time DESC, id DESC LIMIT 1',
            [$boardId]
        );
        [$time, $author] = self::pointer($latest);
        $db->query('UPDATE ppb_boards SET lastchange = ?, lastauthor = ? WHERE id = ?', [$time, $author, $boardId]);
    }

    /**
     * @param array<string, mixed>|null $row
     *
     * @return array{int, int} Zeit und Autor, ohne Beitrag 0/0
     */
    private static function pointer(?array $row): array
    {
        if ($row === null) {
            return [0, 0];
        }

        return [self::toInt($row['time'] ?? 0), self::toInt($row['author'] ?? 0)];
    }

    private static function toInt(mixed $value): int
    {
        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : 0;
    }
}
