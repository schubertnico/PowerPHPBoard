<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Auth;
use PowerPHPBoard\Database;

/**
 * Tests für Anmeldestatus und Berechtigungen (Befund: Status "Deactivated"
 * war wirkungslos, deaktivierte Nutzer konnten sich anmelden und posten).
 */
final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    #[Test]
    public function onlyNormalUsersAndAdministratorsAreActive(): void
    {
        $this->assertTrue(Auth::isActive(['status' => 'Normal user']));
        $this->assertTrue(Auth::isActive(['status' => 'Administrator']));
        $this->assertFalse(Auth::isActive(['status' => 'Deactivated']));
        $this->assertFalse(Auth::isActive(['status' => '']));
        $this->assertFalse(Auth::isActive([]));
    }

    #[Test]
    public function currentUserReturnsActiveUser(): void
    {
        $_SESSION['user_id'] = 7;
        $db = $this->dbReturning(['id' => 7, 'status' => 'Normal user', 'email' => 'a@example.org']);

        $user = Auth::currentUser($db);

        $this->assertNotNull($user);
        $this->assertSame(7, $user['id']);
        $this->assertSame(7, $_SESSION['user_id']);
    }

    #[Test]
    public function deactivatedUserIsLoggedOutAndTreatedAsGuest(): void
    {
        $_SESSION['user_id'] = 4;
        $db = $this->dbReturning(['id' => 4, 'status' => 'Deactivated', 'email' => 'd@example.org']);

        $this->assertNull(Auth::currentUser($db));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    #[Test]
    public function deletedUserIsLoggedOut(): void
    {
        $_SESSION['user_id'] = 99;
        $db = $this->dbReturning(null);

        $this->assertNull(Auth::currentUser($db));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    #[Test]
    public function guestDoesNotQueryDatabase(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->never())->method('fetchOne');

        $this->assertNull(Auth::currentUser($db));
    }

    #[Test]
    public function moderatorIsMatchedByEmailCaseInsensitive(): void
    {
        $board = ['mods' => 'first@example.org, Moritz@Example.test '];

        $this->assertTrue(Auth::isModerator(['email' => 'moritz@example.test'], $board));
        $this->assertFalse(Auth::isModerator(['email' => 'other@example.test'], $board));
        $this->assertFalse(Auth::isModerator(['email' => ''], ['mods' => '']));
        $this->assertFalse(Auth::isModerator(null, $board));
    }

    #[Test]
    public function editPermissions(): void
    {
        $board = ['mods' => 'mod@example.org'];
        $post = ['author' => 5];
        $author = ['id' => 5, 'status' => 'Normal user', 'email' => 'author@example.org'];
        $stranger = ['id' => 6, 'status' => 'Normal user', 'email' => 'x@example.org'];
        $moderator = ['id' => 7, 'status' => 'Normal user', 'email' => 'mod@example.org'];
        $admin = ['id' => 1, 'status' => 'Administrator', 'email' => 'admin@example.org'];
        $deactivatedAuthor = ['id' => 5, 'status' => 'Deactivated', 'email' => 'author@example.org'];
        $deactivatedMod = ['id' => 7, 'status' => 'Deactivated', 'email' => 'mod@example.org'];

        $this->assertTrue(Auth::canEditPost($author, $post, $board));
        $this->assertTrue(Auth::canEditPost($moderator, $post, $board));
        $this->assertTrue(Auth::canEditPost($admin, $post, $board));
        $this->assertFalse(Auth::canEditPost($stranger, $post, $board));
        $this->assertFalse(Auth::canEditPost(null, $post, $board));
        $this->assertFalse(Auth::canEditPost($deactivatedAuthor, $post, $board));
        $this->assertFalse(Auth::canModerate($deactivatedMod, $board));
        $this->assertFalse(Auth::canModerate($author, $board));
        $this->assertTrue(Auth::canModerate($admin, []));
    }

    #[Test]
    public function administratorCannotDemoteOrDeactivateHimself(): void
    {
        $admin = ['id' => 1, 'status' => 'Administrator'];

        $this->assertSame('self', Auth::statusChangeError($admin, $admin, 'Normal user', 3));
        $this->assertSame('self', Auth::statusChangeError($admin, $admin, 'Deactivated', 3));
        $this->assertNull(Auth::statusChangeError($admin, $admin, 'Administrator', 1));
    }

    #[Test]
    public function lastAdministratorStaysAdministrator(): void
    {
        $actor = ['id' => 1, 'status' => 'Administrator'];
        $lastAdmin = ['id' => 2, 'status' => 'Administrator'];

        $this->assertSame('lastadmin', Auth::statusChangeError($actor, $lastAdmin, 'Deactivated', 1));
        $this->assertNull(Auth::statusChangeError($actor, $lastAdmin, 'Normal user', 2));
    }

    #[Test]
    public function otherStatusChangesAreAllowed(): void
    {
        $actor = ['id' => 1, 'status' => 'Administrator'];
        $user = ['id' => 5, 'status' => 'Normal user'];

        $this->assertNull(Auth::statusChangeError($actor, $user, 'Deactivated', 1));
        $this->assertNull(Auth::statusChangeError($actor, $user, 'Administrator', 1));
    }

    /**
     * Regressionstest: Geschlossene Boards waren für alle gesperrt, auch für
     * Administratoren – ein Ankündigungsboard war damit unbenutzbar.
     */
    #[Test]
    public function adminsAndModeratorsOfTheBoardMayWriteInClosedBoards(): void
    {
        $closed = ['id' => 3, 'status' => 'Closed', 'mods' => 'Mod@Example.org, other@example.org'];
        $open = ['id' => 4, 'status' => 'Open', 'mods' => ''];

        $admin = ['id' => 1, 'status' => 'Administrator', 'email' => 'admin@example.org'];
        $moderator = ['id' => 2, 'status' => 'Normal user', 'email' => 'mod@example.org'];
        $member = ['id' => 5, 'status' => 'Normal user', 'email' => 'member@example.org'];
        $deactivatedModerator = ['id' => 6, 'status' => 'Deactivated', 'email' => 'mod@example.org'];

        $this->assertTrue(Auth::canWriteInBoard($admin, $closed));
        $this->assertTrue(Auth::canWriteInBoard($moderator, $closed));
        $this->assertFalse(Auth::canWriteInBoard($member, $closed));
        $this->assertFalse(Auth::canWriteInBoard(null, $closed));
        $this->assertFalse(Auth::canWriteInBoard($deactivatedModerator, $closed));

        // Moderator eines anderen Boards hat hier keine Sonderrechte
        $this->assertFalse(Auth::canWriteInBoard($moderator, ['id' => 7, 'status' => 'Closed', 'mods' => 'jemand@example.org']));

        // Offene Boards: wie bisher für alle (die Anmeldung prüft das Formular)
        $this->assertTrue(Auth::canWriteInBoard($member, $open));
        $this->assertTrue(Auth::canWriteInBoard(null, $open));
    }

    #[Test]
    public function closedThreadsAcceptRepliesOnlyFromAdminsAndModerators(): void
    {
        $board = ['id' => 3, 'status' => 'Open', 'mods' => 'mod@example.org'];
        $closedBoard = ['status' => 'Closed'] + $board;
        $open = ['id' => 10, 'status' => 'Open'];
        $closed = ['id' => 11, 'status' => 'Closed'];

        $admin = ['id' => 1, 'status' => 'Administrator', 'email' => 'admin@example.org'];
        $moderator = ['id' => 2, 'status' => 'Normal user', 'email' => 'mod@example.org'];
        $member = ['id' => 5, 'status' => 'Normal user', 'email' => 'member@example.org'];

        $this->assertTrue(Auth::canReplyInThread($member, $board, $open));
        $this->assertFalse(Auth::canReplyInThread($member, $board, $closed));
        $this->assertFalse(Auth::canReplyInThread($member, $closedBoard, $open), 'Geschlossenes Board sperrt auch offene Themen');
        $this->assertFalse(Auth::canReplyInThread(null, $board, $closed));

        foreach ([$admin, $moderator] as $user) {
            $this->assertTrue(Auth::canReplyInThread($user, $board, $closed));
            $this->assertTrue(Auth::canReplyInThread($user, $closedBoard, $closed));
        }
        $this->assertFalse(Auth::canReplyInThread($moderator, ['id' => 8, 'status' => 'Open', 'mods' => ''], $closed));
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private function dbReturning(?array $row): Database
    {
        $db = $this->createMock(Database::class);
        $db->method('fetchOne')->willReturn($row);

        return $db;
    }
}
