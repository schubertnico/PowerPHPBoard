<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Feature;

use Error;
use PDOException;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use Throwable;

/**
 * Base test case for feature/integration tests
 * Provides helper methods for simulating HTTP requests and database operations
 */
abstract class FeatureTestCase extends TestCase
{
    protected ?Database $db = null;

    protected array $settings = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Reset superglobals
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Try to connect to test database if available
        $this->setupDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up superglobals
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
    }

    /**
     * Setup database connection for tests
     */
    protected function setupDatabase(): void
    {
        // Check if MySQL PDO extension is available
        if (!extension_loaded('pdo_mysql')) {
            $this->db = null;
            return;
        }

        // Load config if available
        $configFile = __DIR__ . '/../../config.inc.php';
        if (file_exists($configFile)) {
            // Use output buffering to prevent any output
            ob_start();
            try {
                require_once $configFile;
            } catch (Throwable $e) {
                ob_end_clean();
                $this->db = null;
                return;
            }
            ob_end_clean();

            if (isset($mysql)) {
                try {
                    $this->db = Database::getInstance($mysql);
                    $this->settings = $this->db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
                } catch (PDOException $e) {
                    // Database not available, tests will skip DB operations
                    $this->db = null;
                } catch (Error $e) {
                    // PDO constants not available
                    $this->db = null;
                }
            }
        }
    }

    /**
     * Simulate a GET request
     */
    protected function get(array $params = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $params;
    }

    /**
     * Simulate a POST request with CSRF token
     */
    protected function post(array $params = [], bool $withCsrf = true): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        if ($withCsrf) {
            $token = CSRF::generateToken();
            $params['csrf_token'] = $token;
        }

        $_POST = $params;
    }

    /**
     * Login a user by setting session
     */
    protected function loginAs(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_time'] = time();
    }

    /**
     * Assert user is logged in
     */
    protected function assertLoggedIn(): void
    {
        $this->assertTrue(
            isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0,
            'User should be logged in'
        );
    }

    /**
     * Assert user is not logged in
     */
    protected function assertNotLoggedIn(): void
    {
        $this->assertTrue(
            !isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0,
            'User should not be logged in'
        );
    }

    /**
     * Assert CSRF validation passes
     */
    protected function assertCsrfValid(): void
    {
        $this->assertTrue(CSRF::validateFromPost(), 'CSRF token should be valid');
    }

    /**
     * Assert CSRF validation fails
     */
    protected function assertCsrfInvalid(): void
    {
        $this->assertFalse(CSRF::validateFromPost(), 'CSRF token should be invalid');
    }

    /**
     * Create a test user in database (if available)
     * Returns user ID or null if database not available
     */
    protected function createTestUser(string $email, string $password, string $username = 'TestUser'): ?int
    {
        if ($this->db === null) {
            return null;
        }

        $hashedPassword = Security::hashPassword($password);

        try {
            $this->db->query(
                "INSERT INTO ppb_users (email, password, username, status, regdate) VALUES (?, ?, ?, 'User', NOW())",
                [$email, $hashedPassword, $username]
            );
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Delete a test user from database
     */
    protected function deleteTestUser(int $userId): void
    {
        if ($this->db !== null) {
            try {
                $this->db->query('DELETE FROM ppb_users WHERE id = ?', [$userId]);
            } catch (PDOException $e) {
                // Ignore errors
            }
        }
    }

    /**
     * Find user by email
     */
    protected function findUserByEmail(string $email): ?array
    {
        if ($this->db === null) {
            return null;
        }

        return $this->db->fetchOne('SELECT * FROM ppb_users WHERE email = ?', [$email]);
    }

    /**
     * Check if database is available for testing
     */
    protected function requiresDatabase(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('Database connection required for this test');
        }
    }
}
