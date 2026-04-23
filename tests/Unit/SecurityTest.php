<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Security;

/**
 * Unit tests for Security class
 */
class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear superglobals before each test
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    #[Test]
    public function escapePreventXSS(): void
    {
        $maliciousInput = '<script>alert("XSS")</script>';
        $escaped = Security::escape($maliciousInput);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    #[Test]
    public function escapeHandlesNull(): void
    {
        $this->assertSame('', Security::escape(null));
    }

    #[Test]
    public function escapeHandlesQuotes(): void
    {
        $input = 'test "double" and \'single\' quotes';
        $escaped = Security::escape($input);

        $this->assertStringContainsString('&quot;', $escaped);
        // HTML5 uses &apos; for single quotes
        $this->assertTrue(
            str_contains($escaped, '&#039;') || str_contains($escaped, '&apos;'),
            'Single quotes should be escaped'
        );
    }

    #[Test]
    public function eIsAliasForEscape(): void
    {
        $input = '<b>test</b>';
        $this->assertSame(Security::escape($input), Security::e($input));
    }

    #[Test]
    public function hashPasswordReturnsHash(): void
    {
        $password = 'testPassword123';
        $hash = Security::hashPassword($password);

        $this->assertNotEmpty($hash);
        $this->assertNotSame($password, $hash);
        // Hash should start with $2y$ (bcrypt) or $argon2 (argon2id)
        $this->assertTrue(
            str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'),
            'Hash should be bcrypt or argon2id format'
        );
    }

    #[Test]
    public function verifyPasswordValidReturnsTrue(): void
    {
        $password = 'correctPassword';
        $hash = Security::hashPassword($password);

        $this->assertTrue(Security::verifyPassword($password, $hash));
    }

    #[Test]
    public function verifyPasswordInvalidReturnsFalse(): void
    {
        $password = 'correctPassword';
        $hash = Security::hashPassword($password);

        $this->assertFalse(Security::verifyPassword('wrongPassword', $hash));
    }

    #[Test]
    public function verifyLegacyBase64Password(): void
    {
        $password = 'legacyPassword';
        $legacyHash = base64_encode($password);

        $this->assertTrue(Security::verifyPassword($password, $legacyHash));
        $this->assertFalse(Security::verifyPassword('wrongPassword', $legacyHash));
    }

    #[Test]
    public function needsRehashReturnsTrueForLegacy(): void
    {
        $legacyHash = base64_encode('password');

        $this->assertTrue(Security::needsRehash($legacyHash));
    }

    #[Test]
    public function needsRehashReturnsFalseForModern(): void
    {
        $modernHash = Security::hashPassword('password');

        $this->assertFalse(Security::needsRehash($modernHash));
    }

    #[Test]
    public function isLegacyHashDetectsBase64(): void
    {
        $legacyHash = base64_encode('password');
        $modernHash = Security::hashPassword('password');

        $this->assertTrue(Security::isLegacyHash($legacyHash));
        $this->assertFalse(Security::isLegacyHash($modernHash));
    }

    #[Test]
    public function getIntFromGet(): void
    {
        $_GET['test'] = '42';

        $this->assertSame(42, Security::getInt('test'));
        $this->assertSame(42, Security::getInt('test', 'GET'));
    }

    #[Test]
    public function getIntFromPost(): void
    {
        $_POST['test'] = '123';

        $this->assertSame(123, Security::getInt('test', 'POST'));
    }

    #[Test]
    public function getIntReturnsDefaultForMissing(): void
    {
        $this->assertSame(0, Security::getInt('nonexistent'));
        $this->assertSame(99, Security::getInt('nonexistent', 'GET', 99));
    }

    #[Test]
    public function getIntReturnsDefaultForInvalid(): void
    {
        $_GET['test'] = 'notanumber';

        $this->assertSame(0, Security::getInt('test'));
    }

    #[Test]
    public function getStringFromGet(): void
    {
        $_GET['test'] = '  hello world  ';

        $this->assertSame('hello world', Security::getString('test'));
    }

    #[Test]
    public function getStringFromPost(): void
    {
        $_POST['test'] = 'posted value';

        $this->assertSame('posted value', Security::getString('test', 'POST'));
    }

    #[Test]
    public function getStringReturnsDefaultForMissing(): void
    {
        $this->assertSame('', Security::getString('nonexistent'));
        $this->assertSame('default', Security::getString('nonexistent', 'GET', 'default'));
    }

    #[Test]
    public function isValidEmailAcceptsValid(): void
    {
        $this->assertTrue(Security::isValidEmail('test@example.com'));
        $this->assertTrue(Security::isValidEmail('user.name@domain.org'));
        $this->assertTrue(Security::isValidEmail('user+tag@example.co.uk'));
    }

    #[Test]
    public function isValidEmailRejectsInvalid(): void
    {
        $this->assertFalse(Security::isValidEmail(''));
        $this->assertFalse(Security::isValidEmail('notanemail'));
        $this->assertFalse(Security::isValidEmail('missing@'));
        $this->assertFalse(Security::isValidEmail('@nodomain.com'));
    }

    #[Test]
    public function generateTokenReturnsHexString(): void
    {
        $token = Security::generateToken();

        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    #[Test]
    public function generateTokenWithCustomLength(): void
    {
        $token = Security::generateToken(16);

        $this->assertSame(32, strlen($token)); // 16 bytes = 32 hex chars
    }

    #[Test]
    public function generateTokenIsUnique(): void
    {
        $token1 = Security::generateToken();
        $token2 = Security::generateToken();

        $this->assertNotSame($token1, $token2);
    }

    #[Test]
    public function getClientIpReturnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);

        $this->assertSame('192.168.1.100', Security::getClientIp());
    }

    #[Test]
    public function getClientIpReturnsDefaultWhenEmpty(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);

        $this->assertSame('0.0.0.0', Security::getClientIp());
    }

    #[Test]
    public function escapeAttrEscapesHtml(): void
    {
        $this->assertSame('', Security::escapeAttr(null));
        $this->assertSame('&lt;div class=&quot;test&quot;&gt;', Security::escapeAttr('<div class="test">'));
    }

    #[Test]
    public function getIntFromRequest(): void
    {
        $_REQUEST['test'] = '77';

        $this->assertSame(77, Security::getInt('test', 'REQUEST'));
    }

    #[Test]
    public function getStringFromRequest(): void
    {
        $_REQUEST['test'] = '  request value  ';

        $this->assertSame('request value', Security::getString('test', 'REQUEST'));
    }

    #[Test]
    public function getClientIpUsesXForwardedFor(): void
    {
        // Public IP should be returned
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 70.41.3.18';
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);

        $this->assertSame('203.0.113.50', Security::getClientIp());
    }

    #[Test]
    public function getClientIpIgnoresPrivateRangeProxy(): void
    {
        // Private IP in proxy header should be skipped, falls back to REMOTE_ADDR
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $this->assertSame('10.0.0.1', Security::getClientIp());
    }

    #[Test]
    public function getClientIpUsesXRealIp(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.25';
        unset($_SERVER['HTTP_CLIENT_IP']);

        $this->assertSame('198.51.100.25', Security::getClientIp());
    }

    #[Test]
    public function getClientIpUsesClientIp(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.99';

        $this->assertSame('203.0.113.99', Security::getClientIp());
    }

    #[Test]
    public function generateTokenEnforcesMinimumLength(): void
    {
        // Even with 0 or negative, max(1, $length) should produce at least 2 hex chars
        $token = Security::generateToken(0);
        $this->assertSame(2, strlen($token));
    }

    #[Test]
    public function escapeHandlesEmptyString(): void
    {
        $this->assertSame('', Security::escape(''));
    }

    #[Test]
    public function escapeHandlesSpecialChars(): void
    {
        $input = '&<>"\'';
        $escaped = Security::escape($input);
        $this->assertStringNotContainsString('&<>', $escaped);
        $this->assertStringContainsString('&amp;', $escaped);
    }
}
