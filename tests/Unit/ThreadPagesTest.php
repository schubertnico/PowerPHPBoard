<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Database;
use PowerPHPBoard\ThreadPages;

/**
 * Regressionstest: „Zum letzten Beitrag springen“ und „Zum ersten
 * ungelesenen Beitrag springen“ führten bei genau 25, 50 … Beiträgen
 * (Starterbeitrag mitgezählt) auf eine leere Seite.
 */
final class ThreadPagesTest extends TestCase
{
    /**
     * @return array<string, array{int, int}>
     */
    public static function postCounts(): array
    {
        return [
            'nur Starterbeitrag' => [1, 0],
            '24 Beiträge' => [24, 0],
            '25 Beiträge (volle erste Seite)' => [25, 0],
            '26 Beiträge' => [26, 25],
            '50 Beiträge (volle zweite Seite)' => [50, 25],
            '51 Beiträge' => [51, 50],
        ];
    }

    #[Test]
    #[DataProvider('postCounts')]
    public function lastPostIsOnTheLastNonEmptyPage(int $postCount, int $expectedOffset): void
    {
        $offset = ThreadPages::lastPageOffset($postCount);

        $this->assertSame($expectedOffset, $offset);
        // Auf der Seite liegt mindestens ein Beitrag (sonst wäre sie leer)
        $this->assertLessThan($postCount, $offset);
    }

    #[Test]
    public function positionsMapToPageStarts(): void
    {
        $this->assertSame(0, ThreadPages::offsetForPosition(0));
        $this->assertSame(0, ThreadPages::offsetForPosition(24));
        $this->assertSame(25, ThreadPages::offsetForPosition(25));
        $this->assertSame(25, ThreadPages::offsetForPosition(49));
        $this->assertSame(50, ThreadPages::offsetForPosition(50));
        $this->assertSame(0, ThreadPages::offsetForPosition(-3), 'Ungültige Positionen landen auf der ersten Seite');
        $this->assertSame(0, ThreadPages::lastPageOffset(0), 'Thema ohne Beiträge');
    }

    #[Test]
    #[DataProvider('postCounts')]
    public function postLinkUsesThePageOfThePost(int $postCount, int $expectedOffset): void
    {
        // Der letzte Beitrag hat $postCount - 1 Vorgänger im Thema
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('id < ?'), [7, 7, 99])
            ->willReturn(['count' => $postCount - 1]);

        $this->assertSame(
            'showthread.php?threadid=7&current=' . $expectedOffset . '#post99',
            ThreadPages::postLink($db, 7, 99)
        );
    }

    #[Test]
    public function firstUnreadPostOnAnEarlierPageKeepsThatPage(): void
    {
        // 60 Beiträge, der erste ungelesene ist der 30. (Position 29) – er steht auf Seite 2
        $db = $this->createMock(Database::class);
        $db->method('fetchOne')->willReturn(['count' => 29]);

        $this->assertSame(25, ThreadPages::offsetOfPost($db, 7, 130));
    }
}
