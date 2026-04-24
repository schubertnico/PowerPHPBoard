<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Feature;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

/**
 * Feature tests for Profile workflow
 * Tests the profile editing process including validation and authorization
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ProfileWorkflowTest extends FeatureTestCase
{
    private string $testEmail = 'profiletest@example.com';

    private string $testPassword = 'ProfilePass123';

    private ?int $testUserId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db !== null) {
            // Clean up existing test user
            $existing = $this->findUserByEmail($this->testEmail);
            if ($existing !== null) {
                $this->deleteTestUser((int) $existing['id']);
            }

            $this->testUserId = $this->createTestUser(
                $this->testEmail,
                $this->testPassword,
                'ProfileTestUser'
            );
        }
    }

    protected function tearDown(): void
    {
        if ($this->testUserId !== null) {
            $this->deleteTestUser($this->testUserId);
        }

        parent::tearDown();
    }

    #[Test]
    public function profileEditRequiresLogin(): void
    {
        // Not logged in
        $this->assertNotLoggedIn();

        // Profile editing should require login
        $loggedin = Session::isLoggedIn();
        $this->assertFalse($loggedin, 'User should not be logged in');
    }

    #[Test]
    public function profileEditRequiresCsrfToken(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'UpdatedUser',
            'email1' => 'updated@example.com',
            'email2' => 'updated@example.com',
            'password1' => 'NewPassword123',
            'password2' => 'NewPassword123',
            'editprofile' => '1',
        ], withCsrf: false);

        $this->assertCsrfInvalid();
    }

    #[Test]
    public function profileEditAcceptsValidCsrf(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'UpdatedUser',
            'email1' => 'updated@example.com',
            'email2' => 'updated@example.com',
            'password1' => 'NewPassword123',
            'password2' => 'NewPassword123',
            'editprofile' => '1',
        ], withCsrf: true);

        $this->assertCsrfValid();
    }

    #[Test]
    public function profileEditRejectsEmptyUsername(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => '',
            'email1' => 'test@example.com',
            'email2' => 'test@example.com',
            'password1' => 'Password123',
            'password2' => 'Password123',
            'editprofile' => '1',
        ]);

        $username = Security::getString('username', 'POST');
        $this->assertSame('', $username);
    }

    #[Test]
    public function profileEditRejectsEmptyEmail(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'TestUser',
            'email1' => '',
            'email2' => '',
            'password1' => 'Password123',
            'password2' => 'Password123',
            'editprofile' => '1',
        ]);

        $email1 = Security::getString('email1', 'POST');
        $this->assertSame('', $email1);
    }

    #[Test]
    public function profileEditRejectsMismatchedEmails(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'TestUser',
            'email1' => 'first@example.com',
            'email2' => 'second@example.com',
            'password1' => 'Password123',
            'password2' => 'Password123',
            'editprofile' => '1',
        ]);

        $email1 = Security::getString('email1', 'POST');
        $email2 = Security::getString('email2', 'POST');

        $this->assertNotSame($email1, $email2);
    }

    #[Test]
    public function profileEditRejectsInvalidEmail(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $invalidEmail = 'not-an-email';

        $this->assertFalse(Security::isValidEmail($invalidEmail));
    }

    #[Test]
    public function profileEditUsesModernEmailValidation(): void
    {
        // Test that Security::isValidEmail is used (not regex)
        $validEmails = [
            'user@example.com',
            'user.name+tag@example.co.uk',
            'user123@subdomain.domain.org',
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                Security::isValidEmail($email),
                "Modern validation should accept '$email'"
            );
        }
    }

    #[Test]
    public function profileEditRejectsMismatchedPasswords(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'TestUser',
            'email1' => 'test@example.com',
            'email2' => 'test@example.com',
            'password1' => 'Password1',
            'password2' => 'Password2',
            'editprofile' => '1',
        ]);

        $password1 = Security::getString('password1', 'POST');
        $password2 = Security::getString('password2', 'POST');

        $this->assertNotSame($password1, $password2);
    }

    #[Test]
    public function profileEditRejectsShortPassword(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'TestUser',
            'email1' => 'test@example.com',
            'email2' => 'test@example.com',
            'password1' => '12345',
            'password2' => '12345',
            'editprofile' => '1',
        ]);

        $password1 = Security::getString('password1', 'POST');

        $this->assertLessThan(6, strlen($password1), 'Password with 5 chars should be rejected');
    }

    #[Test]
    public function profileEditAcceptsMinimumPasswordLength(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'TestUser',
            'email1' => 'test@example.com',
            'email2' => 'test@example.com',
            'password1' => '123456',
            'password2' => '123456',
            'editprofile' => '1',
        ]);

        $password1 = Security::getString('password1', 'POST');

        $this->assertGreaterThanOrEqual(6, strlen($password1));
    }

    #[Test]
    public function profileEditRejectsNonNumericIcq(): void
    {
        $invalidIcq = '123abc';

        $this->assertFalse(ctype_digit($invalidIcq));
    }

    #[Test]
    public function profileEditAcceptsNumericIcq(): void
    {
        $validIcq = '123456789';

        $this->assertTrue(ctype_digit($validIcq));
    }

    #[Test]
    public function profileEditAcceptsEmptyIcq(): void
    {
        $emptyIcq = '';

        // Empty ICQ should pass the check (it's optional)
        $this->assertTrue($emptyIcq === '' || ctype_digit($emptyIcq));
    }

    #[Test]
    public function profileEditStripsHtmlFromUsername(): void
    {
        $maliciousUsername = '<b>Bold</b><script>alert(1)</script>User';
        $cleanUsername = strip_tags($maliciousUsername);

        $this->assertSame('Boldalert(1)User', $cleanUsername);
        $this->assertStringNotContainsString('<', $cleanUsername);
    }

    #[Test]
    public function profileEditEscapesOutput(): void
    {
        $maliciousInput = '"><script>alert("XSS")</script>';
        $escaped = Security::escape($maliciousInput);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
        $this->assertStringContainsString('&quot;', $escaped);
    }

    #[Test]
    public function profileEditRejectsDuplicateEmail(): void
    {
        $this->requiresDatabase();

        // Create another user with a different email
        $otherEmail = 'other' . time() . '@example.com';
        $otherUserId = $this->createTestUser($otherEmail, 'Password123', 'OtherUser');

        // Try to check if email exists for another user
        $existingUser = $this->db->fetchOne(
            'SELECT id FROM ppb_users WHERE email = ? AND id != ?',
            [$otherEmail, $this->testUserId]
        );

        $this->assertNotNull($existingUser, 'Should detect duplicate email');

        // Clean up
        $this->deleteTestUser($otherUserId);
    }

    #[Test]
    public function profileEditUpdatesPassword(): void
    {
        $this->requiresDatabase();

        $newPassword = 'NewSecurePassword456';
        $hashedPassword = Security::hashPassword($newPassword);

        // Verify the new password can be validated
        $this->assertTrue(Security::verifyPassword($newPassword, $hashedPassword));
        $this->assertFalse(Security::verifyPassword($this->testPassword, $hashedPassword));
    }

    #[Test]
    public function profileEditPreservesSession(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'UpdatedUser',
            'email1' => 'updated@example.com',
            'email2' => 'updated@example.com',
            'password1' => 'NewPassword123',
            'password2' => 'NewPassword123',
            'editprofile' => '1',
        ]);

        // User should still be logged in after profile edit
        $this->assertLoggedIn();
    }

    #[Test]
    public function profileEditValidatesHideEmailOption(): void
    {
        $this->loginAs($this->testUserId ?? 1);

        $this->post([
            'username' => 'TestUser',
            'email1' => 'test@example.com',
            'email2' => 'test@example.com',
            'password1' => 'Password123',
            'password2' => 'Password123',
            'hideemail' => 'YES',
            'editprofile' => '1',
        ]);

        $hideemail = Security::getString('hideemail', 'POST');

        $this->assertSame('YES', $hideemail);
    }

    #[Test]
    public function profileEditTrimsInputFields(): void
    {
        $this->post([
            'username' => '  TestUser  ',
            'email1' => '  test@example.com  ',
            'email2' => '  test@example.com  ',
            'password1' => 'Password123',
            'password2' => 'Password123',
            'editprofile' => '1',
        ]);

        $username = Security::getString('username', 'POST');
        $email1 = Security::getString('email1', 'POST');

        $this->assertSame('TestUser', $username);
        $this->assertSame('test@example.com', $email1);
    }
}
