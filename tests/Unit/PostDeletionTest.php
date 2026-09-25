<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Database;
use PowerPHPBoard\PostDeletion;

/**
 * Regressionstest: Nach dem Löschen eines Themas oder einer Antwort
 * blieben ppb_boards.lastchange/lastauthor und ppb_posts.lastreply/lastauthor
 * auf dem gelöschten Beitrag stehen – die Startseite zeigte ihn weiter an,
 * der Pfeil führte auf „#“.
 */
final class PostDeletionTest extends TestCase
{
    /** @var list<array{string, array<int|string, mixed>}> */
    private array $queries = [];

    #[Test]
    public function deletingAReplyRecalculatesThreadAndBoard(): void
    {
        $db = $this->db([
            'OR threadid' => ['time' => '1700000600', 'author' => '4'],
            'WHERE boardid' => ['time' => '1700000900', 'author' => '5'],
        ]);

        PostDeletion::deleteReply($db, 42, 10, 3);

        $this->assertSame("DELETE FROM ppb_posts WHERE id = ? AND type = 'Post'", $this->queries[0][0]);
        $this->assertSame([42], $this->queries[0][1]);
        $this->assertSame([1_700_000_600, 4, 10], $this->find('UPDATE ppb_posts SET lastreply'));
        $this->assertSame([1_700_000_900, 5, 3], $this->find('UPDATE ppb_boards SET lastchange'));
    }

    #[Test]
    public function deletingAThreadRemovesRepliesAndVisitsAndRecalculatesTheBoard(): void
    {
        $db = $this->db(['WHERE boardid' => ['time' => 1_700_000_100, 'author' => 2]]);

        PostDeletion::deleteThread($db, 10, 3);

        $this->assertSame([10, 10], $this->find('DELETE FROM ppb_posts WHERE id = ? OR threadid = ?'));
        $this->assertSame([10], $this->find("DELETE FROM ppb_visits WHERE vid = ? AND type = 'Thread'"));
        $this->assertSame([1_700_000_100, 2, 3], $this->find('UPDATE ppb_boards SET lastchange'));
        $this->assertNull($this->find('UPDATE ppb_posts SET lastreply'), 'Das gelöschte Thema wird nicht mehr aktualisiert');
    }

    #[Test]
    public function emptyBoardGetsZeroPointer(): void
    {
        $db = $this->db([]);

        PostDeletion::refreshBoard($db, 3);

        $this->assertSame([0, 0, 3], $this->find('UPDATE ppb_boards SET lastchange'));
    }

    /**
     * @param array<string, array<string, mixed>> $latest Treffer je Teil der SELECT-Abfrage
     */
    private function db(array $latest): Database
    {
        $this->queries = [];
        $db = $this->createMock(Database::class);
        $db->method('fetchOne')->willReturnCallback(
            static function (string $sql) use ($latest): ?array {
                foreach ($latest as $needle => $row) {
                    if (str_contains($sql, $needle)) {
                        return $row;
                    }
                }

                return null;
            }
        );
        $db->method('query')->willReturnCallback(
            function (string $sql, array $params = []) {
                $this->queries[] = [$sql, $params];

                return $this->createStub(PDOStatement::class);
            }
        );

        return $db;
    }

    /**
     * @return array<int|string, mixed>|null Parameter der ersten passenden Abfrage
     */
    private function find(string $needle): ?array
    {
        foreach ($this->queries as [$sql, $params]) {
            if (str_contains($sql, $needle)) {
                return $params;
            }
        }

        return null;
    }
}
