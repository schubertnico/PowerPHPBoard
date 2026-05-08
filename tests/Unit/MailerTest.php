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

    public function testSendReturnsFalseForInvalidRecipient(): void
    {
        $mailer = new Mailer('127.0.0.1', 1, 1);
        $this->assertFalse(
            $mailer->send('not-an-email', 'sender@example.com', 'x', 'y')
        );
    }

    public function testSendReturnsFalseForInvalidSender(): void
    {
        $mailer = new Mailer('127.0.0.1', 1, 1);
        $this->assertFalse(
            $mailer->send('to@example.com', 'not-an-email', 'x', 'y')
        );
    }

    public function testSendReturnsFalseWhenSmtpHostUnreachable(): void
    {
        // Port 1 ist auf 127.0.0.1 mit hoher Wahrscheinlichkeit nicht belegt.
        // Wenn doch, gibt der Test einen False-Negative; Timeout 1s begrenzt
        // die Wartezeit. Der Test loggt die error_log-Nachricht in den
        // PHP-Errorlog des Test-Prozesses, das ist hier akzeptabel.
        $mailer = new Mailer('127.0.0.1', 1, 1);
        $this->assertFalse(
            $mailer->send('to@example.com', 'from@example.com', 'subject', 'body')
        );
    }

    public function testSendCompletesSuccessfulSmtpConversation(): void
    {
        [$port, $proc] = $this->startMockServer('ok');
        try {
            $mailer = new Mailer('127.0.0.1', $port, 5);
            $result = $mailer->send(
                'to@example.com',
                'from@example.com',
                'Hallo Welt',
                "Line1\nLine2"
            );
            $this->assertTrue($result);
        } finally {
            $this->stopMockServer($proc);
        }
    }

    public function testSendHandlesMultilineGreeting(): void
    {
        [$port, $proc] = $this->startMockServer('multiline-220');
        try {
            $mailer = new Mailer('127.0.0.1', $port, 5);
            $this->assertTrue(
                $mailer->send('to@example.com', 'from@example.com', 's', 'b')
            );
        } finally {
            $this->stopMockServer($proc);
        }
    }

    public function testSendReturnsFalseOnUnexpectedSmtpResponse(): void
    {
        [$port, $proc] = $this->startMockServer('reject-helo');
        try {
            $mailer = new Mailer('127.0.0.1', $port, 5);
            $this->assertFalse(
                $mailer->send('to@example.com', 'from@example.com', 's', 'b')
            );
        } finally {
            $this->stopMockServer($proc);
        }
    }

    /**
     * @return array{0:int, 1:resource}
     */
    private function startMockServer(string $scenario): array
    {
        $script = __DIR__ . '/../Helpers/mock-smtp-server.php';
        $proc = proc_open(
            [PHP_BINARY, $script, '0', $scenario],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );
        if (!is_resource($proc)) {
            $this->fail('Konnte Mock-SMTP-Server nicht starten');
        }

        // Erste Zeile aus stdout enthaelt den effektiven Port. fgets blockt,
        // bis der Server den Port ausgegeben hat - also nach erfolgreichem
        // stream_socket_server-Bind. Damit ist der Server beim Verbinden
        // garantiert lauschbereit.
        $portLine = fgets($pipes[1]);
        if ($portLine === false) {
            $stderr = stream_get_contents($pipes[2]);
            proc_terminate($proc);
            proc_close($proc);
            $this->fail('Mock-Server lieferte keinen Port. STDERR: ' . (string) $stderr);
        }
        $port = (int) trim($portLine);
        if ($port <= 0) {
            proc_terminate($proc);
            proc_close($proc);
            $this->fail('Mock-Server lieferte ungueltigen Port: ' . $portLine);
        }

        return [$port, $proc];
    }

    /**
     * @param resource $proc
     */
    private function stopMockServer($proc): void
    {
        // Server beendet sich nach einer Konversation selbst. Falls nicht
        // (Test-Fehlpfad), gewaltsam abbrechen.
        $status = proc_get_status($proc);
        if ($status['running']) {
            proc_terminate($proc);
        }
        proc_close($proc);
    }
}
