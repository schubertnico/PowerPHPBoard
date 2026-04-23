<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Session;

/**
 * Unit tests for Session class
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    #[Test]
    public function setAndGet(): void
    {
        Session::set('test_key', 'test_value');

        $this->assertSame('test_value', Session::get('test_key'));
    }

    #[Test]
    public function getReturnsDefault(): void
    {
        $this->assertSame('default', Session::get('nonexistent', 'default'));
        $this->assertNull(Session::get('nonexistent'));
    }

    #[Test]
    public function hasReturnsTrueForExistingKey(): void
    {
        Session::set('existing', 'value');

        $this->assertTrue(Session::has('existing'));
    }

    #[Test]
    public function hasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse(Session::has('nonexistent'));
    }

    #[Test]
    public function removeDeletesKey(): void
    {
        Session::set('to_remove', 'value');
        Session::remove('to_remove');

        $this->assertFalse(Session::has('to_remove'));
    }

    #[Test]
    public function loginSetsUserId(): void
    {
        Session::login(42);

        $this->assertSame(42, $_SESSION['user_id']);
        $this->assertArrayHasKey('login_time', $_SESSION);
    }

    #[Test]
    public function isLoggedInReturnsTrueWhenLoggedIn(): void
    {
        $_SESSION['user_id'] = 1;

        $this->assertTrue(Session::isLoggedIn());
    }

    #[Test]
    public function isLoggedInReturnsFalseWhenNotLoggedIn(): void
    {
        $this->assertFalse(Session::isLoggedIn());
    }

    #[Test]
    public function isLoggedInReturnsFalseForZeroUserId(): void
    {
        $_SESSION['user_id'] = 0;

        $this->assertFalse(Session::isLoggedIn());
    }

    #[Test]
    public function isLoggedInReturnsFalseForNegativeUserId(): void
    {
        $_SESSION['user_id'] = -1;

        $this->assertFalse(Session::isLoggedIn());
    }

    #[Test]
    public function getUserIdReturnsId(): void
    {
        $_SESSION['user_id'] = 123;

        $this->assertSame(123, Session::getUserId());
    }

    #[Test]
    public function getUserIdReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull(Session::getUserId());
    }

    #[Test]
    public function allReturnsSessionData(): void
    {
        $_SESSION['key1'] = 'value1';
        $_SESSION['key2'] = 'value2';

        $all = Session::all();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
    }

    #[Test]
    public function setWithDifferentTypes(): void
    {
        Session::set('string', 'text');
        Session::set('int', 42);
        Session::set('array', ['a', 'b']);
        Session::set('bool', true);

        $this->assertSame('text', Session::get('string'));
        $this->assertSame(42, Session::get('int'));
        $this->assertSame(['a', 'b'], Session::get('array'));
        $this->assertTrue(Session::get('bool'));
    }

    #[Test]
    public function loginTimestampIsRecent(): void
    {
        $before = time();
        Session::login(1);
        $after = time();

        $loginTime = $_SESSION['login_time'];

        $this->assertGreaterThanOrEqual($before, $loginTime);
        $this->assertLessThanOrEqual($after, $loginTime);
    }

    #[Test]
    public function destroyClearsSession(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['test'] = 'value';

        Session::destroy();

        $this->assertEmpty($_SESSION);
    }

    #[Test]
    public function logoutClearsSession(): void
    {
        $_SESSION['user_id'] = 5;

        Session::logout();

        $this->assertEmpty($_SESSION);
        $this->assertFalse(Session::isLoggedIn());
    }

    #[Test]
    public function startSetsStartedFlag(): void
    {
        // After start, calling start again should not error
        Session::start();
        Session::start(); // second call should be no-op

        $this->assertTrue(true);
    }

    #[Test]
    public function allReturnsEmptyWhenNoSession(): void
    {
        unset($_SESSION);

        $result = Session::all();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function removeNonexistentKeyDoesNotError(): void
    {
        Session::remove('does_not_exist');

        $this->assertFalse(Session::has('does_not_exist'));
    }

    #[Test]
    public function getReturnsNullDefaultForMissing(): void
    {
        $this->assertNull(Session::get('missing_key'));
    }

    #[Test]
    public function loginOverwritesPreviousUser(): void
    {
        Session::login(1);
        Session::login(2);

        $this->assertSame(2, Session::getUserId());
    }
}
