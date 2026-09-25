<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PowerPHPBoard\PostDeletion;
use PowerPHPBoard\ThreadPages;

/**
 * Feature-Tests gegen eine echte Datenbank für Themen: Sprung-Links auf
 * die richtige Seite und Zeiger auf den letzten Beitrag nach dem Löschen.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ThreadWorkflowTest extends FeatureTestCase
{
    private ?int $userId = null;

    private ?int $boardId = null;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../includes/autoload.php';

        if ($this->db === null) {
            return;
        }
        $existing = $this->findUserByEmail('threadworkflow@example.com');
        if ($existing !== null) {
            $this->deleteTestUser((int) $existing['id']);
        }
        $this->userId = $this->createTestUser('threadworkflow@example.com', 'Password123', 'ThreadWorkflowUser');

        $this->db->query("INSERT INTO ppb_boards (title, description, type, status) VALUES ('Feature-Test Themen', '', 'Board', 'Open')");
        $this->boardId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            if ($this->boardId !== null) {
                $this->db->query('DELETE FROM ppb_posts WHERE boardid = ?', [$this->boardId]);
                $this->db->query('DELETE FROM ppb_boards WHERE id = ?', [$this->boardId]);
            }
            if ($this->userId !== null) {
                $this->deleteTestUser($this->userId);
            }
        }
        parent::tearDown();
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function postCounts(): array
    {
        return [
            '24 Beiträge' => [24, 0],
            '25 Beiträge' => [25, 0],
            '26 Beiträge' => [26, 25],
            '50 Beiträge' => [50, 25],
        ];
    }

    #[Test]
    #[DataProvider('postCounts')]
    public function jumpToLastPostOpensAPageThatShowsIt(int $postCount, int $expectedOffset): void
    {
        $this->requiresDatabase();
        assert($this->db !== null);

        $ids = $this->createThread($postCount);
        $threadId = $ids[0];
        $lastId = $ids[count($ids) - 1];

        $offset = ThreadPages::offsetOfPost($this->db, $threadId, $lastId);
        $this->assertSame($expectedOffset, $offset);
        $this->assertSame(ThreadPages::lastPageOffset($postCount), $offset);

        // Genau die Abfrage von showthread.php: Die Seite enthält den Beitrag
        $page = $this->db->fetchAll(
            'SELECT id FROM ppb_posts WHERE threadid = ? OR id = ? ORDER BY id LIMIT ?, 25',
            [$threadId, $threadId, $offset]
        );
        $this->assertContains($lastId, array_map(static fn (array $row): int => (int) $row['id'], $page));
        $this->assertStringContainsString('&current=' . $expectedOffset . '#post' . $lastId, ThreadPages::postLink($this->db, $threadId, $lastId));
    }

    #[Test]
    public function deletingPostsMovesLastPostPointersToTheRemainingPosts(): void
    {
        $this->requiresDatabase();
        assert($this->db !== null && $this->userId !== null && $this->boardId !== null);

        $older = $this->createThread(2, 1_700_000_000);   // Thema + 1 Antwort, zuletzt 1_700_000_060
        $newer = $this->createThread(3, 1_700_100_000);   // Thema + 2 Antworten, zuletzt 1_700_100_120

        // Letzte Antwort des neueren Themas löschen
        PostDeletion::deleteReply($this->db, $newer[2], $newer[0], $this->boardId);
        $this->assertSame([1_700_100_060, $this->userId], $this->threadPointer($newer[0]));
        $this->assertSame([1_700_100_060, $this->userId], $this->boardPointer());

        // Letzte verbliebene Antwort löschen: Das Thema zeigt wieder auf den Starterbeitrag
        PostDeletion::deleteReply($this->db, $newer[1], $newer[0], $this->boardId);
        $this->assertSame([1_700_100_000, $this->userId], $this->threadPointer($newer[0]));

        // Ganzes Thema löschen: Das Board zeigt auf das ältere Thema
        PostDeletion::deleteThread($this->db, $newer[0], $this->boardId);
        $this->assertNull($this->db->fetchOne('SELECT id FROM ppb_posts WHERE id = ? OR threadid = ?', [$newer[0], $newer[0]]));
        $this->assertSame([1_700_000_060, $this->userId], $this->boardPointer());

        // Letztes Thema löschen: leeres Board
        PostDeletion::deleteThread($this->db, $older[0], $this->boardId);
        $this->assertSame([0, 0], $this->boardPointer());
    }

    /**
     * @return array{int, int}
     */
    private function threadPointer(int $threadId): array
    {
        assert($this->db !== null);
        $row = $this->db->fetchOne('SELECT lastreply, lastauthor FROM ppb_posts WHERE id = ?', [$threadId]);
        $this->assertNotNull($row);

        return [(int) $row['lastreply'], (int) $row['lastauthor']];
    }

    /**
     * @return array{int, int}
     */
    private function boardPointer(): array
    {
        assert($this->db !== null);
        $row = $this->db->fetchOne('SELECT lastchange, lastauthor FROM ppb_boards WHERE id = ?', [$this->boardId]);
        $this->assertNotNull($row);

        return [(int) $row['lastchange'], (int) $row['lastauthor']];
    }

    /**
     * Legt ein Thema mit $postCount Beiträgen (Starterbeitrag plus Antworten) an.
     *
     * @return list<int> ids in Reihenfolge, [0] ist das Thema
     */
    private function createThread(int $postCount, int $startTime = 1_700_000_000): array
    {
        assert($this->db !== null && $this->userId !== null && $this->boardId !== null);

        $this->db->query(
            "INSERT INTO ppb_posts (boardid, type, time, author, title, text, lastreply, lastauthor) VALUES (?, 'Thread', ?, ?, 'Feature-Test', 'Start', ?, ?)",
            [$this->boardId, $startTime, $this->userId, $startTime, $this->userId]
        );
        $threadId = (int) $this->db->lastInsertId();
        $ids = [$threadId];
        for ($i = 1; $i < $postCount; $i++) {
            $this->db->query(
                "INSERT INTO ppb_posts (boardid, threadid, type, time, author, text) VALUES (?, ?, 'Post', ?, ?, ?)",
                [$this->boardId, $threadId, $startTime + $i * 60, $this->userId, 'Antwort ' . $i]
            );
            $ids[] = (int) $this->db->lastInsertId();
        }
        $last = $startTime + ($postCount - 1) * 60;
        $this->db->query('UPDATE ppb_posts SET lastreply = ?, lastauthor = ? WHERE id = ?', [$last, $this->userId, $threadId]);
        $this->db->query('UPDATE ppb_boards SET lastchange = ?, lastauthor = ? WHERE id = ?', [$last, $this->userId, $this->boardId]);

        return $ids;
    }
}
