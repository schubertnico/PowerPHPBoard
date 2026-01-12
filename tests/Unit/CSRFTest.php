<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\CSRF;

/**
 * Unit tests for CSRF class
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class CSRFTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_POST = [];
    }

    #[Test]
    public function generateTokenCreatesToken(): void
    {
        $token = CSRF::generateToken();

        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertArrayHasKey('csrf_token', $_SESSION);
    }

    #[Test]
    public function generateTokenReturnsSameToken(): void
    {
        $token1 = CSRF::generateToken();
        $token2 = CSRF::generateToken();

        $this->assertSame($token1, $token2);
    }

    #[Test]
    public function validateReturnsTrueForValidToken(): void
    {
        $token = CSRF::generateToken();

        $this->assertTrue(CSRF::validate($token));
    }

    #[Test]
    public function validateReturnsFalseForInvalidToken(): void
    {
        CSRF::generateToken();

        $this->assertFalse(CSRF::validate('invalid_token'));
    }

    #[Test]
    public function validateReturnsFalseForNullToken(): void
    {
        CSRF::generateToken();

        $this->assertFalse(CSRF::validate(null));
    }

    #[Test]
    public function validateReturnsFalseForEmptyToken(): void
    {
        CSRF::generateToken();

        $this->assertFalse(CSRF::validate(''));
    }

    #[Test]
    public function validateReturnsFalseWhenNoSessionToken(): void
    {
        $this->assertFalse(CSRF::validate('some_token'));
    }

    #[Test]
    public function validateFromPostSuccess(): void
    {
        $token = CSRF::generateToken();
        $_POST['csrf_token'] = $token;

        $this->assertTrue(CSRF::validateFromPost());
    }

    #[Test]
    public function validateFromPostFailure(): void
    {
        CSRF::generateToken();
        $_POST['csrf_token'] = 'wrong_token';

        $this->assertFalse(CSRF::validateFromPost());
    }

    #[Test]
    public function validateFromPostMissing(): void
    {
        CSRF::generateToken();
        // No $_POST['csrf_token'] set

        $this->assertFalse(CSRF::validateFromPost());
    }

    #[Test]
    public function regenerateCreatesNewToken(): void
    {
        $token1 = CSRF::generateToken();
        CSRF::regenerate();
        $token2 = CSRF::generateToken();

        $this->assertNotSame($token1, $token2);
    }

    #[Test]
    public function getTokenFieldOutputsHTML(): void
    {
        $field = CSRF::getTokenField();

        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    #[Test]
    public function getTokenNameReturnsConstant(): void
    {
        $this->assertSame('csrf_token', CSRF::getTokenName());
    }
}
