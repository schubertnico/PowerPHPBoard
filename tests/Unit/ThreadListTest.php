<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Database;

/**
 * Regressionstest: Ein neues Thema ohne Antworten zeigte in der
 * Themenliste unter „Letzte Antwort“ bereits Datum und „von <Autor>“ seines
 * Starterbeitrags.
 */
final class ThreadListTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../functions.inc.php';
    }

    #[Test]
    public function threadWithoutRepliesShowsNoRepliesInsteadOfDateAndAuthor(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->never())->method('fetchOne');
        $thread = ['id' => 14, 'author' => 2, 'lastreply' => 1_758_040_000, 'lastauthor' => 2];

        $html = ppb_last_reply_cell($db, $thread, 0);

        $this->assertStringContainsString('No replies', $html);
        $this->assertStringNotContainsString(date('d.m.Y', 1_758_040_000), $html);
        $this->assertStringNotContainsString('by', $html);
        $this->assertStringNotContainsString('<a', $html);
    }

    #[Test]
    public function threadWithRepliesShowsLinkDateAndAuthorOfTheLastReply(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('fetchOne')->willReturnCallback(static fn (string $sql): ?array => match (true) {
            str_contains($sql, 'FROM ppb_users') => ['username' => 'Anna <Admin>'],
            str_contains($sql, 'COUNT(*)') => ['count' => 25],
            default => ['id' => 140],
        });
        $thread = ['id' => 14, 'lastreply' => 1_758_040_000, 'lastauthor' => 1];

        $html = ppb_last_reply_cell($db, $thread, 25);

        $this->assertStringContainsString('href="showthread.php?threadid=14&amp;current=25#post140"', $html);
        $this->assertStringContainsString(date('d.m.Y - H:i', 1_758_040_000), $html);
        $this->assertStringContainsString('Anna &lt;Admin&gt;', $html);
    }
}
