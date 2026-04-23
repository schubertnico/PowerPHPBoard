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
}
