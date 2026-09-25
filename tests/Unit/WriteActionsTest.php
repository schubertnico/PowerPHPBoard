<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Schaltflächen „Neues Thema“/„Neuer Beitrag“ in geschlossenen Boards und
 * Themen: Berechtigte sehen sie mit Hinweis, alle anderen die Badge.
 */
final class WriteActionsTest extends TestCase
{
    private const array ADMIN = ['id' => 1, 'status' => 'Administrator', 'email' => 'admin@example.org'];

    private const array MODERATOR = ['id' => 2, 'status' => 'Normal user', 'email' => 'mod@example.org'];

    private const array MEMBER = ['id' => 5, 'status' => 'Normal user', 'email' => 'member@example.org'];

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../functions.inc.php';
    }

    #[Test]
    public function membersSeeOnlyTheBadgeInAClosedBoard(): void
    {
        $html = ppb_write_actions($this->board('Closed'), $this->thread('Open'), self::MEMBER, 0, true);

        $this->assertStringContainsString('Board closed', $html);
        $this->assertStringNotContainsString('newthread.php', $html);
        $this->assertStringNotContainsString('newpost.php', $html);
        $this->assertStringNotContainsString('ppb-mod-hint', $html);
    }

    #[Test]
    public function adminsAndModeratorsKeepTheButtonsWithAHintInAClosedBoard(): void
    {
        foreach ([self::ADMIN, self::MODERATOR] as $user) {
            $html = ppb_write_actions($this->board('Closed'), $this->thread('Open'), $user, 25, true);

            $this->assertStringContainsString('ppb-mod-hint', $html);
            $this->assertStringContainsString('Board closed – you are posting with moderator rights.', $html);
            $this->assertStringContainsString('class="btn btn-primary btn-sm" href="newthread.php?boardid=3"', $html);
            $this->assertStringContainsString('class="btn btn-success btn-sm" href="newpost.php?threadid=10&current=25"', $html);
            $this->assertStringNotContainsString('badge', $html);
        }
    }

    #[Test]
    public function closedThreadShowsBadgeForMembersAndReplyButtonForModerators(): void
    {
        $member = ppb_write_actions($this->board('Open'), $this->thread('Closed'), self::MEMBER, 0, false);
        $this->assertStringContainsString('href="newthread.php?boardid=3"', $member);
        $this->assertStringContainsString('Thread closed', $member);
        $this->assertStringNotContainsString('newpost.php', $member);

        $moderator = ppb_write_actions($this->board('Open'), $this->thread('Closed'), self::MODERATOR, 0, false);
        $this->assertStringContainsString('Thread closed – you are replying with moderator rights.', $moderator);
        $this->assertStringContainsString('class="btn btn-success" href="newpost.php?threadid=10&current=0"', $moderator);
    }

    #[Test]
    public function openBoardShowsButtonsWithoutHint(): void
    {
        $html = ppb_write_actions($this->board('Open'), $this->thread('Open'), null, 0, true);

        $this->assertStringContainsString('newthread.php?boardid=3', $html);
        $this->assertStringContainsString('newpost.php?threadid=10', $html);
        $this->assertStringNotContainsString('ppb-mod-hint', $html);

        $this->assertSame('', ppb_closed_write_notice($this->board('Open'), $this->thread('Open')));
        $this->assertStringContainsString('moderator rights', ppb_closed_write_notice($this->board('Closed'), []));
        $this->assertStringContainsString('replying with moderator rights', ppb_closed_write_notice($this->board('Open'), $this->thread('Closed')));
    }

    /**
     * @return array<string, mixed>
     */
    private function board(string $status): array
    {
        return ['id' => 3, 'status' => $status, 'mods' => 'mod@example.org'];
    }

    /**
     * @return array<string, mixed>
     */
    private function thread(string $status): array
    {
        return ['id' => 10, 'status' => $status, 'title' => 'Ankündigung'];
    }
}
