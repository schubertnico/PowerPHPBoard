<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PowerPHPBoard\BoardUrl;

final class BoardUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);
        parent::tearDown();
    }

    public function testUsesConfiguredBoardUrl(): void
    {
        $this->assertSame('https://forum.example.org', BoardUrl::base(['boardurl' => 'https://forum.example.org/']));
        $this->assertSame('http://localhost:8216/ppb', BoardUrl::base(['boardurl' => ' http://localhost:8216/ppb ']));
    }

    public function testEmptyBoardUrlNeverFallsBackToHostHeader(): void
    {
        // Regression: Früher wurde der Reset-Link aus dem Host-Header gebaut.
        $_SERVER['HTTP_HOST'] = 'evil.example';
        $_SERVER['HTTPS'] = 'on';

        $this->assertNull(BoardUrl::base(['boardurl' => '']));
        $this->assertNull(BoardUrl::base([]));
    }

    public function testRejectsNonHttpBoardUrls(): void
    {
        $this->assertNull(BoardUrl::base(['boardurl' => 'javascript:alert(1)']));
        $this->assertNull(BoardUrl::base(['boardurl' => '//evil.example']));
        $this->assertNull(BoardUrl::base(['boardurl' => 'forum']));
        $this->assertNull(BoardUrl::base(['boardurl' => ['https://example.org']]));
    }

    public function testBuildsLinksWithQuery(): void
    {
        $this->assertSame(
            'https://forum.example.org/resetpassword.php?token=abc123',
            BoardUrl::link('https://forum.example.org/', '/resetpassword.php', ['token' => 'abc123'])
        );
        $this->assertSame('https://forum.example.org/login.php', BoardUrl::link('https://forum.example.org', 'login.php'));
    }
}
