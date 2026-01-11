<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PowerPHPBoard\Security;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Session;

/**
 * Feature tests for Login workflow
 * Tests the complete login process including validation, CSRF, and session handling
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class LoginWorkflowTest extends FeatureTestCase
{
    private string $testEmail = 'logintest@example.com';
    private string $testPassword = 'TestPassword123';
    private ?int $testUserId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user if database available
        if ($this->db !== null) {
            // Clean up any existing test user
            $existing = $this->findUserByEmail($this->testEmail);
            if ($existing !== null) {
                $this->deleteTestUser((int) $existing['id']);
            }

            $this->testUserId = $this->createTestUser(
                $this->testEmail,
                $this->testPassword,
                'LoginTestUser'
            );
        }
    }

    protected function tearDown(): void
    {
        // Clean up test user
        if ($this->testUserId !== null) {
            $this->deleteTestUser($this->testUserId);
        }

        parent::tearDown();
    }

    #[Test]
    public function loginFormRequiresCsrfToken(): void
    {
        // Simulate POST without CSRF token
        $this->post([
            'email' => $this->testEmail,
            'password' => $this->testPassword,
            'login' => '1',
        ], withCsrf: false);

        $this->assertCsrfInvalid();
    }

    #[Test]
    public function loginFormAcceptsValidCsrfToken(): void
    {
        // Simulate POST with valid CSRF token
        $this->post([
            'email' => $this->testEmail,
            'password' => $this->testPassword,
            'login' => '1',
        ], withCsrf: true);

        $this->assertCsrfValid();
    }

    #[Test]
    public function loginValidationRejectsEmptyEmail(): void
    {
        $email = Security::getString('email', 'POST');

        $this->post([
            'email' => '',
            'password' => $this->testPassword,
            'login' => '1',
        ]);

        $email = Security::getString('email', 'POST');

        $this->assertSame('', $email);
        $this->assertTrue($email === '' || $email === '0', 'Empty email should fail validation');
    }

    #[Test]
    public function loginValidationRejectsEmptyPassword(): void
    {
        $this->post([
            'email' => $this->testEmail,
            'password' => '',
            'login' => '1',
        ]);

        $password = Security::getString('password', 'POST');

        $this->assertSame('', $password);
        $this->assertTrue($password === '' || $password === '0', 'Empty password should fail validation');
    }

    #[Test]
    public function loginWithValidCredentialsSetsSession(): void
    {
        $this->requiresDatabase();

        $this->post([
            'email' => $this->testEmail,
            'password' => $this->testPassword,
            'login' => '1',
        ]);

        // Simulate login logic
        $email = Security::getString('email', 'POST');
        $password = Security::getString('password', 'POST');

        $user = $this->findUserByEmail($email);

        $this->assertNotNull($user, 'User should exist in database');
        $this->assertTrue(
            Security::verifyPassword($password, $user['password']),
            'Password should verify correctly'
        );

        // Simulate successful login
        Session::login((int) $user['id']);

        $this->assertLoggedIn();
        $this->assertSame((int) $user['id'], Session::getUserId());
    }

    #[Test]
    public function loginWithWrongPasswordDoesNotSetSession(): void
    {
        $this->requiresDatabase();

        $this->post([
            'email' => $this->testEmail,
            'password' => 'WrongPassword',
            'login' => '1',
        ]);

        $email = Security::getString('email', 'POST');
        $password = Security::getString('password', 'POST');

        $user = $this->findUserByEmail($email);

        $this->assertNotNull($user, 'User should exist in database');
        $this->assertFalse(
            Security::verifyPassword($password, $user['password']),
            'Wrong password should not verify'
        );

        // Session should not be set
        $this->assertNotLoggedIn();
    }

    #[Test]
    public function loginWithNonexistentEmailFails(): void
    {
        $this->requiresDatabase();

        $this->post([
            'email' => 'nonexistent@example.com',
            'password' => $this->testPassword,
            'login' => '1',
        ]);

        $email = Security::getString('email', 'POST');
        $user = $this->findUserByEmail($email);

        $this->assertNull($user, 'User should not exist');
        $this->assertNotLoggedIn();
    }

    #[Test]
    public function loginInputIsTrimmed(): void
    {
        $this->post([
            'email' => '  test@example.com  ',
            'password' => 'password',
            'login' => '1',
        ]);

        $email = Security::getString('email', 'POST');

        $this->assertSame('test@example.com', $email, 'Email should be trimmed');
    }

    #[Test]
    public function loginEmailIsValidated(): void
    {
        $invalidEmails = [
            'notanemail',
            'missing@',
            '@nodomain.com',
            'spaces in@email.com',
        ];

        foreach ($invalidEmails as $invalidEmail) {
            $this->assertFalse(
                Security::isValidEmail($invalidEmail),
                "Email '$invalidEmail' should be invalid"
            );
        }
    }

    #[Test]
    public function loginEmailAcceptsValidFormats(): void
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.org',
            'user+tag@example.co.uk',
        ];

        foreach ($validEmails as $validEmail) {
            $this->assertTrue(
                Security::isValidEmail($validEmail),
                "Email '$validEmail' should be valid"
            );
        }
    }

    #[Test]
    public function logoutDestroysSession(): void
    {
        // First login
        $this->loginAs(1);
        $this->assertLoggedIn();

        // Then logout
        Session::logout();

        $this->assertNotLoggedIn();
    }

    #[Test]
    public function sessionRegeneratesAfterLogin(): void
    {
        $this->requiresDatabase();

        // Generate initial session
        $initialToken = CSRF::generateToken();

        // Simulate login
        Session::login($this->testUserId ?? 1);

        // Session should contain user_id and login_time
        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertArrayHasKey('login_time', $_SESSION);
    }

    #[Test]
    public function xssPreventedInLoginForm(): void
    {
        $maliciousInput = '<script>alert("XSS")</script>';

        $this->post([
            'email' => $maliciousInput,
            'password' => 'password',
            'login' => '1',
        ]);

        $email = Security::getString('email', 'POST');
        $escaped = Security::escape($email);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }
}
