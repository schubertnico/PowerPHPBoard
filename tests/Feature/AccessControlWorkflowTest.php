<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Feature;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PowerPHPBoard\Auth;
use PowerPHPBoard\BoardAccess;
use PowerPHPBoard\Security;

/**
 * Feature-Tests gegen eine echte Datenbank für die Zugriffskontrolle:
 * deaktivierte Konten und private Boards (Hash, Migration, Nachweis).
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AccessControlWorkflowTest extends FeatureTestCase
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
        $existing = $this->findUserByEmail('accesscontrol@example.com');
        if ($existing !== null) {
            $this->deleteTestUser((int) $existing['id']);
        }
        $this->userId = $this->createTestUser('accesscontrol@example.com', 'Password123', 'AccessControlUser');

        // Privates Board mit Passwort im alten Base64-Format
        $this->db->query(
            "INSERT INTO ppb_boards (title, description, type, status, password) VALUES ('Feature-Test privat', '', 'Board', 'Private', ?)",
            [base64_encode('geheim123')]
        );
        $this->boardId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            if ($this->boardId !== null) {
                $this->db->query("DELETE FROM ppb_visits WHERE vid = ? AND type = 'Board'", [$this->boardId]);
                $this->db->query('DELETE FROM ppb_boards WHERE id = ?', [$this->boardId]);
            }
            if ($this->userId !== null) {
                $this->deleteTestUser($this->userId);
            }
        }
        parent::tearDown();
    }

    #[Test]
    public function deactivatedUserIsTreatedAsLoggedOut(): void
    {
        $this->requiresDatabase();
        assert($this->db !== null && $this->userId !== null);

        $this->loginAs($this->userId);
        $this->assertNotNull(Auth::currentUser($this->db));

        $this->db->query("UPDATE ppb_users SET status = 'Deactivated' WHERE id = ?", [$this->userId]);
        $this->loginAs($this->userId);

        $this->assertNull(Auth::currentUser($this->db));
        $this->assertNotLoggedIn();
    }

    #[Test]
    public function privateBoardPasswordIsMigratedAndVisitHoldsNoPassword(): void
    {
        $this->requiresDatabase();
        assert($this->db !== null && $this->userId !== null && $this->boardId !== null);

        $user = $this->db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$this->userId]);
        $board = $this->db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$this->boardId]);
        $this->assertNotNull($user);
        $this->assertNotNull($board);

        $this->assertSame(BoardAccess::REQUIRED, BoardAccess::check($board, $user, $this->db, null));
        $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($board, $user, $this->db, 'falsch'));
        $this->assertSame(BoardAccess::GRANTED, BoardAccess::check($board, $user, $this->db, 'geheim123'));

        $migrated = $this->db->fetchOne('SELECT password FROM ppb_boards WHERE id = ?', [$this->boardId]);
        $this->assertNotNull($migrated);
        $this->assertStringStartsWith('$argon2id$', (string) $migrated['password']);
        $this->assertFalse(Security::isLegacyHash((string) $migrated['password']));

        $visit = $this->db->fetchOne(
            "SELECT password FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
            [$this->userId, $this->boardId]
        );
        $this->assertNotNull($visit);
        $this->assertNotSame(base64_encode('geheim123'), $visit['password']);
        $this->assertStringNotContainsString('geheim123', (string) $visit['password']);

        // Neue Sitzung: der gespeicherte Nachweis genügt, ein Passwortwechsel widerruft ihn
        $_SESSION = [];
        $board = $this->db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$this->boardId]);
        $this->assertNotNull($board);
        $this->assertTrue(BoardAccess::hasAccess($board, $user, $this->db));

        $this->db->query('UPDATE ppb_boards SET password = ? WHERE id = ?', [BoardAccess::hashPassword('neues-passwort'), $this->boardId]);
        $_SESSION = [];
        $board = $this->db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$this->boardId]);
        $this->assertNotNull($board);
        $this->assertFalse(BoardAccess::hasAccess($board, $user, $this->db));
    }
}
