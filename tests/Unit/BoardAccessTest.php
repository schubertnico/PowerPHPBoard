<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\BoardAccess;
use PowerPHPBoard\Database;
use PowerPHPBoard\RateLimiter;
use PowerPHPBoard\RateLimiterStorage;

/**
 * Regressionstests für private Boards:
 * - Board-Passwörter wurden nur Base64-kodiert gespeichert,
 * - ppb_visits enthielt das Board-Passwort dauerhaft,
 * - die Zitatfunktion zeigte Beiträge privater Boards ohne Passwort.
 */
final class BoardAccessTest extends TestCase
{
    /** @var list<array{string, array<int|string, mixed>}> */
    private array $queries = [];

    /** @var array<string, mixed>|null */
    private ?array $visitRow = null;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $this->queries = [];
        $this->visitRow = null;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    #[Test]
    public function publicAndClosedBoardsNeedNoPassword(): void
    {
        $db = $this->db();

        $this->assertSame(BoardAccess::GRANTED, BoardAccess::check(['id' => 3, 'status' => 'Open'], null, $db, null));
        $this->assertSame(BoardAccess::GRANTED, BoardAccess::check(['id' => 4, 'status' => 'Closed'], null, $db, null));
    }

    #[Test]
    public function privateBoardRequiresPasswordForGuestsAndUsers(): void
    {
        $board = $this->privateBoard('geheim123');
        $db = $this->db();

        $this->assertFalse(BoardAccess::hasAccess($board, null, $db));
        $this->assertSame(BoardAccess::REQUIRED, BoardAccess::check($board, null, $db, null));
        $this->assertSame(BoardAccess::REQUIRED, BoardAccess::check($board, $this->user(), $db, null));
    }

    #[Test]
    public function wrongPasswordIsReported(): void
    {
        $board = $this->privateBoard('geheim123');

        $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($board, null, $this->db(), 'falsch'));
        $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($board, null, $this->db(), ''));
        $this->assertSame([], $_SESSION);
    }

    #[Test]
    public function boardPasswordsAreStoredAsArgon2idHash(): void
    {
        $hash = BoardAccess::hashPassword('geheim123');

        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertStringNotContainsString(base64_encode('geheim123'), $hash);
        $this->assertTrue(BoardAccess::verifyPassword('geheim123', $hash));
        $this->assertFalse(BoardAccess::verifyPassword('geheim124', $hash));
    }

    #[Test]
    public function legacyBase64PasswordIsMigratedOnFirstCorrectEntry(): void
    {
        $board = ['id' => 5, 'status' => 'Private', 'password' => base64_encode('geheim123')];
        $db = $this->db();

        $result = BoardAccess::check($board, $this->user(), $db, 'geheim123');

        $this->assertSame(BoardAccess::GRANTED, $result);
        $boardUpdate = $this->findQuery('UPDATE ppb_boards SET password');
        $this->assertNotNull($boardUpdate, 'Board-Passwort muss neu gehasht werden');
        $this->assertStringStartsWith('$argon2id$', (string) $boardUpdate[1][0]);
        $this->assertNotNull($this->findQuery("UPDATE ppb_visits SET password = '' WHERE vid"), 'Alte Kopien in ppb_visits verwerfen');
    }

    #[Test]
    public function visitsStoreGrantTokenInsteadOfPassword(): void
    {
        $board = $this->privateBoard('geheim123');
        $db = $this->db();

        BoardAccess::check($board, $this->user(), $db, 'geheim123');

        $insert = $this->findQuery('INSERT INTO ppb_visits');
        $this->assertNotNull($insert);
        $stored = (string) $insert[1][3];
        $this->assertSame(BoardAccess::grantToken(2, 5, (string) $board['password']), $stored);
        $this->assertStringNotContainsString('geheim123', $stored);
        $this->assertNotSame(base64_encode('geheim123'), $stored);
    }

    #[Test]
    public function grantedAccessIsRememberedInSession(): void
    {
        $board = $this->privateBoard('geheim123');
        $db = $this->db();

        $this->assertSame(BoardAccess::GRANTED, BoardAccess::check($board, null, $db, 'geheim123'));
        $this->assertTrue(BoardAccess::hasAccess($board, null, $db));
    }

    #[Test]
    public function storedGrantGivesAccessWithoutPassword(): void
    {
        $board = $this->privateBoard('geheim123');
        $this->visitRow = ['id' => 9, 'password' => BoardAccess::grantToken(2, 5, (string) $board['password'])];

        $this->assertTrue(BoardAccess::hasAccess($board, $this->user(), $this->db()));
    }

    #[Test]
    public function grantBecomesInvalidWhenPasswordChanges(): void
    {
        $old = $this->privateBoard('geheim123');
        $new = $this->privateBoard('neues-passwort');
        $this->visitRow = ['id' => 9, 'password' => BoardAccess::grantToken(2, 5, (string) $old['password'])];

        $this->assertFalse(BoardAccess::hasAccess($new, $this->user(), $this->db()));
        $this->assertNotNull($this->findQuery('UPDATE ppb_visits SET password = ? WHERE id = ?'), 'Veralteter Nachweis wird gelöscht');
    }

    #[Test]
    public function legacyPasswordCopyInVisitsGivesNoAccess(): void
    {
        $board = $this->privateBoard('geheim123');
        $this->visitRow = ['id' => 9, 'password' => base64_encode('geheim123')];

        $this->assertFalse(BoardAccess::hasAccess($board, $this->user(), $this->db()));
        $cleanup = $this->findQuery('UPDATE ppb_visits SET password = ? WHERE id = ?');
        $this->assertNotNull($cleanup);
        $this->assertSame('', $cleanup[1][0]);
    }

    #[Test]
    public function deactivatedUserDoesNotGetStoredAccess(): void
    {
        $board = $this->privateBoard('geheim123');
        $this->visitRow = ['id' => 9, 'password' => BoardAccess::grantToken(2, 5, (string) $board['password'])];

        $this->assertFalse(BoardAccess::hasAccess($board, $this->user('Deactivated'), $this->db()));
    }

    #[Test]
    public function privateBoardWithoutPasswordIsNotOpen(): void
    {
        $board = ['id' => 5, 'status' => 'Private', 'password' => ''];

        $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($board, null, $this->db(), ''));
    }

    #[Test]
    public function tooManyWrongPasswordsLockTheForm(): void
    {
        $limiter = $this->limiter();
        $board = $this->privateBoard('geheim123');

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($board, null, $this->db(), 'falsch', $limiter, 'ip'));
        }
        $this->assertSame(BoardAccess::LOCKED, BoardAccess::check($board, null, $this->db(), 'geheim123', $limiter, 'ip'));
    }

    /**
     * Regressionstest: Ein richtiges Board-Passwort setzte den
     * Fehlversuchszähler nicht zurück – nach gelegentlichen Tippfehlern war
     * das Formular irgendwann gesperrt, obwohl man das Passwort kennt.
     */
    #[Test]
    public function correctPasswordResetsTheFailureCounter(): void
    {
        $limiter = $this->limiter();
        $board = $this->privateBoard('geheim123');

        for ($round = 0; $round < 3; $round++) {
            $_SESSION = [];
            $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($board, null, $this->db(), 'falsch', $limiter, 'ip'));
            $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($board, null, $this->db(), 'vertippt', $limiter, 'ip'));
            $this->assertSame(BoardAccess::GRANTED, BoardAccess::check($board, null, $this->db(), 'geheim123', $limiter, 'ip'));
        }
    }

    #[Test]
    public function correctPasswordOfAnotherBoardDoesNotResetTheCounter(): void
    {
        $limiter = $this->limiter();
        $known = $this->privateBoard('bekannt123');
        $target = ['id' => 6] + $this->privateBoard('geheim123');

        $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($target, null, $this->db(), 'rate1', $limiter, 'ip'));
        $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($target, null, $this->db(), 'rate2', $limiter, 'ip'));
        $this->assertSame(BoardAccess::GRANTED, BoardAccess::check($known, null, $this->db(), 'bekannt123', $limiter, 'ip'));
        $this->assertSame(BoardAccess::WRONG_PASSWORD, BoardAccess::check($target, null, $this->db(), 'rate3', $limiter, 'ip'));

        $this->assertSame(BoardAccess::LOCKED, BoardAccess::check($target, null, $this->db(), 'geheim123', $limiter, 'ip'));
        $this->assertSame('ip|board:6', BoardAccess::rateLimitKey('ip', 6));
    }

    private function limiter(): RateLimiter
    {
        $storage = new class () implements RateLimiterStorage {
            /** @var array<string, array{attempts:int, window_start:int, locked_until:int}> */
            private array $data = [];

            public function getState(string $action, string $identifier, int $now): array
            {
                return $this->data[$action . '|' . $identifier] ?? ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0];
            }

            public function saveState(string $action, string $identifier, array $state): void
            {
                $this->data[$action . '|' . $identifier] = $state;
            }
        };

        return new RateLimiter($storage, maxAttempts: 3, windowSeconds: 900, lockSeconds: 900);
    }

    /**
     * @return array<string, mixed>
     */
    private function privateBoard(string $password): array
    {
        return ['id' => 5, 'status' => 'Private', 'password' => BoardAccess::hashPassword($password)];
    }

    /**
     * @return array<string, mixed>
     */
    private function user(string $status = 'Normal user'): array
    {
        return ['id' => 2, 'status' => $status, 'email' => 'anna@example.test'];
    }

    private function db(): Database
    {
        $db = $this->createMock(Database::class);
        $db->method('fetchOne')->willReturnCallback(
            fn (string $sql, array $params = []): ?array => str_contains($sql, 'FROM ppb_visits') ? $this->visitRow : null
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
     * @return array{string, array<int|string, mixed>}|null
     */
    private function findQuery(string $needle): ?array
    {
        foreach ($this->queries as $query) {
            if (str_contains($query[0], $needle)) {
                return $query;
            }
        }

        return null;
    }
}
