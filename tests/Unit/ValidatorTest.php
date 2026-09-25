<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Validator;

final class ValidatorTest extends TestCase
{
    public function testUsernameAcceptsAlphanumeric(): void
    {
        $this->assertTrue(Validator::isValidUsername('testuser_01'));
        $this->assertTrue(Validator::isValidUsername('Alice.Bob'));
        $this->assertTrue(Validator::isValidUsername('user-name'));
    }

    public function testUsernameRejectsTagsAndSpecialChars(): void
    {
        $this->assertFalse(Validator::isValidUsername('<script>alert(1)</script>'));
        $this->assertFalse(Validator::isValidUsername('user@example'));
        $this->assertFalse(Validator::isValidUsername('user space'));
        $this->assertFalse(Validator::isValidUsername(''));
        $this->assertFalse(Validator::isValidUsername('a'));
        $this->assertFalse(Validator::isValidUsername(str_repeat('x', 51)));
    }

    public function testMaxLengthRespectsUtf8(): void
    {
        $this->assertTrue(Validator::withinLength('Hallo', 5));
        $this->assertFalse(Validator::withinLength('Hallo', 4));
        $this->assertTrue(Validator::withinLength('aeoeue', 6));
    }

    public function testPasswordRulesRequireMinEight(): void
    {
        $this->assertFalse(Validator::isStrongPassword('short'));
        $this->assertFalse(Validator::isStrongPassword('1234567'));
        $this->assertTrue(Validator::isStrongPassword('Password1'));
    }

    public function testHomepageEmptyValuesBecomeEmptyString(): void
    {
        $this->assertSame('', Validator::normalizeHomepage(''));
        $this->assertSame('', Validator::normalizeHomepage('   '));
        $this->assertSame('', Validator::normalizeHomepage('https://'));
        $this->assertSame('', Validator::normalizeHomepage('http://'));
    }

    public function testHomepageWithoutSchemeGetsHttps(): void
    {
        $this->assertSame('https://example.org', Validator::normalizeHomepage('example.org'));
        $this->assertSame('https://www.example.org/pfad?a=1', Validator::normalizeHomepage(' www.example.org/pfad?a=1 '));
        $this->assertSame('http://example.org', Validator::normalizeHomepage('http://example.org'));
    }

    public function testHomepageRejectsDangerousSchemes(): void
    {
        $this->assertNull(Validator::normalizeHomepage('javascript:alert(1)'));
        $this->assertNull(Validator::normalizeHomepage('JavaScript://%0aalert(1)'));
        $this->assertNull(Validator::normalizeHomepage('data:text/html,<script>alert(1)</script>'));
        $this->assertNull(Validator::normalizeHomepage('vbscript:msgbox(1)'));
        $this->assertNull(Validator::normalizeHomepage('https://exa mple.org'));
        $this->assertNull(Validator::normalizeHomepage('https://example.org/"onmouseover="alert(1)'));
    }

    public function testHomepageRespectsMaxLength(): void
    {
        $this->assertNull(Validator::normalizeHomepage('https://example.org/' . str_repeat('a', Validator::HOMEPAGE_MAX)));
    }
}
