<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Mailer;

final class MailerTest extends TestCase
{
    public function testBuildsRfc822Message(): void
    {
        $msg = Mailer::buildMessage(
            to: 'alice@example.com',
            from: 'board@example.com',
            subject: 'Test Subject',
            body: "Hallo\r\nZeile 2"
        );

        $this->assertStringContainsString("From: board@example.com\r\n", $msg);
        $this->assertStringContainsString("To: alice@example.com\r\n", $msg);
        $this->assertStringContainsString('Subject: =?UTF-8?B?', $msg);
        $this->assertStringContainsString("MIME-Version: 1.0\r\n", $msg);
        $this->assertStringContainsString("Content-Type: text/plain; charset=UTF-8\r\n", $msg);
        $this->assertStringEndsWith("Hallo\r\nZeile 2\r\n", $msg);
    }

    public function testNormalizesLineEndings(): void
    {
        $msg = Mailer::buildMessage('a@b.c', 'c@d.e', 'x', "line1\nline2\rline3");
        $this->assertStringContainsString("line1\r\nline2\r\nline3\r\n", $msg);
    }

    public function testRejectsInvalidRecipient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Mailer::buildMessage(to: 'not-an-email', from: 'a@b.c', subject: 'x', body: 'y');
    }
}
