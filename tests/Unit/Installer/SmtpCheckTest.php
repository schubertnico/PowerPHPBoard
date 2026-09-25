<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\SmtpCheck;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\Tests\Helpers\MockSmtpServer;

final class SmtpCheckTest extends TestCase
{
    use MockSmtpServer;

    private string|false $previousErrorLog = false;

    private string $logFile = '';

    protected function setUp(): void
    {
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'ppb-smtpcheck-log-');
        $this->previousErrorLog = ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);
        @unlink($this->logFile);
    }

    public function testDescribeNamesEverythingButThePassword(): void
    {
        $this->assertSame(
            'smtp.example.com:587, STARTTLS, Anmeldung als forum@example.com',
            SmtpCheck::describe($this->mail())
        );
        $this->assertSame(
            'localhost:25, ohne Verschlüsselung, ohne Anmeldung',
            SmtpCheck::describe(['encryption' => 'none', 'port' => 25, 'host' => 'localhost', 'user' => '', 'password' => ''] + $this->mail())
        );
        $this->assertStringContainsString('SSL/TLS', SmtpCheck::describe(['encryption' => 'ssl'] + $this->mail()));
    }

    public function testBodyContainsSettingsButNoPassword(): void
    {
        $body = SmtpCheck::body($this->mail(), 'forum@example.com');

        $this->assertStringContainsString('Verbindung: smtp.example.com:587, STARTTLS, Anmeldung als forum@example.com', $body);
        $this->assertStringContainsString('Absender: forum@example.com', $body);
        $this->assertStringNotContainsString('geheim', $body);
    }

    public function testAcceptedTestMailIsReportedHonestly(): void
    {
        [$outcome, $transcript] = $this->converse('ok', static fn (int $port): array => SmtpCheck::send(
            new Mailer('127.0.0.1', $port, 5),
            ['host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none', 'user' => '', 'password' => '', 'from' => 'forum@example.com'],
            'admin@example.com',
            'forum@example.com'
        ));

        $this->assertTrue($outcome['ok']);
        $this->assertStringStartsWith('Der Mailserver hat die Test-Mail an admin@example.com angenommen.', $outcome['message']);
        $this->assertStringContainsString('Spam-Ordner', $outcome['message']);
        $this->assertContains('RCPT TO:<admin@example.com>', $this->commands($transcript));
        $this->assertStringContainsString('D: Subject: =?UTF-8?B?' . base64_encode(SmtpCheck::SUBJECT) . '?=', $transcript);
    }

    public function testFailureShowsTheReasonWithoutCredentials(): void
    {
        [$outcome] = $this->converse('auth-rejected', fn (int $port): array => SmtpCheck::send(
            new Mailer('127.0.0.1', $port, 5, 'none', 'forum@example.com', 'geheim'),
            $this->mail(),
            'admin@example.com',
            'forum@example.com'
        ));

        $this->assertFalse($outcome['ok']);
        $this->assertSame(
            'Die Test-Mail konnte nicht verschickt werden. Anmeldung (AUTH PLAIN): Server antwortet 535 5.7.8 '
            . 'Authentication credentials invalid – Benutzername und Passwort prüfen.',
            $outcome['message']
        );
        $this->assertStringNotContainsString('geheim', $outcome['message']);
    }

    /**
     * @return array{host: string, port: int, from: string, user: string, password: string, encryption: string}
     */
    private function mail(): array
    {
        return [
            'host' => 'smtp.example.com',
            'port' => 587,
            'from' => 'forum@example.com',
            'user' => 'forum@example.com',
            'password' => 'geheim',
            'encryption' => 'starttls',
        ];
    }
}
