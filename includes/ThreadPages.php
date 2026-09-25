<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Seiten eines Themas
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

/**
 * Ein Thema zeigt seine Beiträge (Starterbeitrag plus Antworten, sortiert
 * nach id) in Seiten zu PER_PAGE Beiträgen. Der Parameter current von
 * showthread.php ist der Index des ersten Beitrags der Seite (0, 25, 50 …).
 *
 * Alle Sprung-Links („Zum letzten Beitrag“, „Zum ersten ungelesenen
 * Beitrag“, der Link nach einer neuen Antwort) berechnen die Seite hier,
 * damit bei genau 25, 50 … Beiträgen keine leere Seite entsteht.
 */
final class ThreadPages
{
    public const int PER_PAGE = 25;

    /**
     * Seite (current) für den Beitrag an Position $position, 0 = Starterbeitrag.
     */
    public static function offsetForPosition(int $position): int
    {
        return intdiv(max(0, $position), self::PER_PAGE) * self::PER_PAGE;
    }

    /**
     * Seite (current) des letzten Beitrags bei $postCount Beiträgen
     * einschließlich Starterbeitrag.
     */
    public static function lastPageOffset(int $postCount): int
    {
        return self::offsetForPosition($postCount - 1);
    }

    /**
     * Seite (current), auf der showthread.php den Beitrag $postId zeigt.
     */
    public static function offsetOfPost(Database $db, int $threadId, int $postId): int
    {
        $before = $db->fetchOne(
            'SELECT COUNT(*) AS count FROM ppb_posts WHERE (id = ? OR threadid = ?) AND id < ?',
            [$threadId, $threadId, $postId]
        );

        return self::offsetForPosition((int) ($before['count'] ?? 0));
    }

    /**
     * Link auf einen Beitrag, einschließlich der richtigen Seite.
     */
    public static function postLink(Database $db, int $threadId, int $postId): string
    {
        return 'showthread.php?threadid=' . $threadId
            . '&current=' . self::offsetOfPost($db, $threadId, $postId)
            . '#post' . $postId;
    }
}
