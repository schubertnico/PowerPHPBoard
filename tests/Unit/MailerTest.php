<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\LocalConfig;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\Tests\Helpers\MockSmtpServer;

final class MailerTest extends TestCase
{
    use MockSmtpServer;

    private const string USER = 'forum@example.org';

    private const string PASSWORD = 'Geh3im!Pässwort';

    private string $logFile = '';

    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        // Fehlerprotokoll des Mailers in eine eigene Datei, damit die Tests
        // prüfen können, was dort landet (und die Ausgabe sauber bleibt).
        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'ppb-mailer-log-');
        $this->previousErrorLog = ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);
        @unlink($this->logFile);
    }

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

    public function testAddsReplyToHeader(): void
    {
        $msg = Mailer::buildMessage('a@example.org', 'board@example.org', 'x', 'y', 'anna@example.org');

        $this->assertStringContainsString("Reply-To: anna@example.org\r\n", $msg);
        $this->assertStringContainsString("From: board@example.org\r\n", $msg);
    }

    public function testRejectsInvalidReplyTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Mailer::buildMessage('a@example.org', 'board@example.org', 'x', 'y', "anna@example.org\r\nBcc: victim@example.org");
    }

    public function testSubjectCannotInjectHeaders(): void
    {
        $msg = Mailer::buildMessage('a@example.org', 'b@example.org', "Hallo\r\nBcc: victim@example.org", 'y');

        $this->assertStringNotContainsString("\r\nBcc:", $msg);
    }

    public function testDotStuffingPreventsSmtpCommandInjection(): void
    {
        // Eine Zeile "." würde die Nachricht beenden, danach folgende Zeilen
        // führte der SMTP-Server als Befehle aus.
        $msg = Mailer::buildMessage('a@example.org', 'b@example.org', 's', "Hallo\n.\nRCPT TO:<victim@example.org>\n.Punkt");
        $stuffed = Mailer::dotStuff($msg);

        $this->assertStringNotContainsString("\r\n.\r\n", $stuffed);
        $this->assertStringContainsString("\r\n..\r\nRCPT TO:<victim@example.org>\r\n..Punkt", $stuffed);
    }

    public function testFactoryCreatesMailer(): void
    {
        $this->assertInstanceOf(Mailer::class, Mailer::fromConfig(['host' => 'mailpit', 'port' => 1025]));
    }

    /**
     * Regressionstest: Alle Mails gingen mit der Admin-E-Mail als From,
     * auch wenn ein Absender eingestellt war. Hoster und SPF/DMARC verlangen
     * oft, dass der Absender zum SMTP-Postfach bzw. zur Domain passt.
     */
    public function testConfiguredSenderIsFromAndAdminEmailIsReplyTo(): void
    {
        $settings = ['adminemail' => 'admin@example.org'];
        $mail = ['from' => 'forum@example.org'];

        $this->assertSame('forum@example.org', Mailer::senderAddress($settings, $mail));
        $this->assertSame('admin@example.org', Mailer::replyToAddress($settings, $mail));
    }

    public function testWithoutConfiguredSenderTheAdminEmailIsFromWithoutReplyTo(): void
    {
        $settings = ['adminemail' => 'admin@example.org'];

        foreach ([[], ['from' => ''], ['from' => '  '], ['from' => 'kaputt@'], ['from' => Mailer::PLACEHOLDER_SENDER], ['from' => 42]] as $mail) {
            $this->assertNull(Mailer::configuredSender($mail));
            $this->assertSame('admin@example.org', Mailer::senderAddress($settings, $mail));
            $this->assertNull(Mailer::replyToAddress($settings, $mail), 'Kein Reply-To, wenn es der Absender selbst ist');
        }
        // Gleiche Adresse in anderer Schreibweise: kein doppelter Reply-To
        $this->assertNull(Mailer::replyToAddress($settings, ['from' => 'Admin@Example.org']));
    }

    public function testSenderFallbacksWithoutAdminEmail(): void
    {
        $this->assertSame('forum@example.org', Mailer::senderAddress(['adminemail' => ''], ['from' => 'forum@example.org']));
        $this->assertNull(Mailer::replyToAddress(['adminemail' => ''], ['from' => 'forum@example.org']));
        $this->assertSame(Mailer::PLACEHOLDER_SENDER, Mailer::senderAddress([], []));
        $this->assertSame('forum@example.org', Mailer::configuredSender(['from' => ' forum@example.org ']));
    }

    public function testPlaceholderMatchesTheConfigurationDefault(): void
    {
        $this->assertSame(LocalConfig::DEFAULT_MAIL['from'], Mailer::PLACEHOLDER_SENDER);
    }

    public function testConfiguredSenderAndReplyToGoOverTheWire(): void
    {
        $settings = ['adminemail' => 'admin@example.org'];
        $mail = ['from' => 'forum@example.org'];

        [$result, $transcript] = $this->converse('ok', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5)->send(
            'anna@example.com',
            Mailer::senderAddress($settings, $mail),
            'Willkommen',
            'Hallo',
            Mailer::replyToAddress($settings, $mail)
        ));

        $this->assertTrue($result);
        $this->assertContains('MAIL FROM:<forum@example.org>', $this->commands($transcript));
        $this->assertStringContainsString("D: From: forum@example.org\n", $transcript);
        $this->assertStringContainsString("D: Reply-To: admin@example.org\n", $transcript);
    }

    public function testSendReturnsFalseForInvalidReplyTo(): void
    {
        $mailer = new Mailer('127.0.0.1', 1, 1);
        $this->assertFalse($mailer->send('to@example.com', 'from@example.com', 'x', 'y', 'not-an-email'));
        $this->assertSame('Ungültige Antwortadresse.', $mailer->lastError());
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
        // Timeout 1s begrenzt die Wartezeit.
        $mailer = new Mailer('127.0.0.1', 1, 1);
        $this->assertFalse(
            $mailer->send('to@example.com', 'from@example.com', 'subject', 'body')
        );
        $this->assertStringStartsWith('Verbindungsaufbau fehlgeschlagen: ', $mailer->lastError());
        $this->assertStringContainsString('[Mailer] Versand über 127.0.0.1:1 (Verschlüsselung none, ohne Anmeldung) fehlgeschlagen', $this->log());
    }

    public function testSendCompletesSuccessfulSmtpConversation(): void
    {
        [$result, $transcript] = $this->converse('ok', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5)->send(
            'to@example.com',
            'from@example.com',
            'Hallo Welt',
            "Line1\nLine2"
        ));

        $this->assertTrue($result);
        $this->assertSame(
            ['EHLO', 'MAIL FROM:<from@example.com>', 'RCPT TO:<to@example.com>', 'DATA', 'QUIT'],
            $this->commands($transcript)
        );
        $this->assertSame('', $this->log());
    }

    public function testEhloUsesHostnameOrAddressLiteral(): void
    {
        [, $transcript] = $this->converse('ok', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5)->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertMatchesRegularExpression('/^C: EHLO (?:[a-z0-9.-]+\.[a-z0-9-]+|\[[0-9.]+\]|\[IPv6:[0-9a-f:]+\])$/m', $transcript);
    }

    public function testDotStuffingIsAppliedOnTheWire(): void
    {
        [$result, $transcript] = $this->converse('ok', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5)->send(
            'to@example.com',
            'from@example.com',
            's',
            "Hallo\n.\nRCPT TO:<victim@example.org>"
        ));

        $this->assertTrue($result);
        $this->assertStringContainsString("D: ..\nD: RCPT TO:<victim@example.org>\n", $transcript);
        $this->assertStringNotContainsString('C: RCPT TO:<victim@example.org>', $transcript);
    }

    public function testSendHandlesMultilineGreeting(): void
    {
        [$result] = $this->converse('multiline-220', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5)->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertTrue($result);
    }

    public function testSendReturnsFalseOnUnexpectedSmtpResponse(): void
    {
        $mailer = null;
        [$result] = $this->converse('reject-helo', static function (int $port) use (&$mailer): bool {
            $mailer = new Mailer('127.0.0.1', $port, 5);

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertNotSame('', $mailer->lastError());
    }

    public function testEhloFallsBackToHeloForOldServers(): void
    {
        [$result, $transcript] = $this->converse('ehlo-unknown', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5)->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertTrue($result);
        $this->assertSame(['EHLO', 'HELO', 'MAIL FROM:<from@example.com>', 'RCPT TO:<to@example.com>', 'DATA', 'QUIT'], $this->commands($transcript));
    }

    public function testEhloRejectionIsFatalWhenLoginIsNeeded(): void
    {
        [$result, $transcript] = $this->converse('ehlo-unknown', fn (int $port): bool => $this->mailer($port, 'none')->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertFalse($result);
        $this->assertSame(['EHLO'], $this->commands($transcript));
        $this->assertStringContainsString('EHLO: Server antwortet 502 5.5.1 Command not implemented', $this->log());
    }

    public function testMissingQuitReplyDoesNotFailAnAcceptedMail(): void
    {
        [$result] = $this->converse('no-quit', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5)->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertTrue($result);
    }

    public function testSilentServerRunsIntoTimeout(): void
    {
        $mailer = null;
        [$result] = $this->converse('silent', static function (int $port) use (&$mailer): bool {
            $mailer = new Mailer('127.0.0.1', $port, 1);

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertSame('Begrüßung: Zeitüberschreitung – der Server antwortet nicht.', $mailer->lastError());
    }

    // ---------------------------------------------------------------
    //  Anmeldung (AUTH PLAIN / AUTH LOGIN)
    // ---------------------------------------------------------------

    public function testAuthPlainSendsCredentialsAfterEhlo(): void
    {
        [$result, $transcript] = $this->converse('auth-plain', fn (int $port): bool => $this->mailer($port, 'none')->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertTrue($result);
        $this->assertSame(
            ['EHLO', 'AUTH PLAIN ' . base64_encode("\0" . self::USER . "\0" . self::PASSWORD), 'MAIL FROM:<from@example.com>', 'RCPT TO:<to@example.com>', 'DATA', 'QUIT'],
            $this->commands($transcript)
        );
    }

    public function testAuthLoginIsUsedWhenPlainIsNotOffered(): void
    {
        [$result, $transcript] = $this->converse('auth-login', fn (int $port): bool => $this->mailer($port, 'none')->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertTrue($result);
        $this->assertSame(
            ['EHLO', 'AUTH LOGIN', base64_encode(self::USER), base64_encode(self::PASSWORD), 'MAIL FROM:<from@example.com>', 'RCPT TO:<to@example.com>', 'DATA', 'QUIT'],
            $this->commands($transcript)
        );
    }

    public function testLegacyAuthAnnouncementIsUnderstood(): void
    {
        [$result, $transcript] = $this->converse('auth-legacy', fn (int $port): bool => $this->mailer($port, 'none')->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertTrue($result);
        $this->assertContains('AUTH LOGIN', $this->commands($transcript));
    }

    public function testNoAuthWithoutUserEvenIfServerOffersIt(): void
    {
        [$result, $transcript] = $this->converse('auth-optional', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5, 'none', '', 'ungenutzt')->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertTrue($result);
        $this->assertStringNotContainsString('AUTH', $transcript);
        $this->assertSame(['EHLO', 'MAIL FROM:<from@example.com>', 'RCPT TO:<to@example.com>', 'DATA', 'QUIT'], $this->commands($transcript));
    }

    public function testRejectedLoginIsLoggedWithCodeButWithoutCredentials(): void
    {
        $mailer = null;
        [$result, $transcript] = $this->converse('auth-rejected', function (int $port) use (&$mailer): bool {
            $mailer = $this->mailer($port, 'none');

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertStringNotContainsString('MAIL FROM', $transcript);
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertSame(
            'Anmeldung (AUTH PLAIN): Server antwortet 535 5.7.8 Authentication credentials invalid – Benutzername und Passwort prüfen.',
            $mailer->lastError()
        );

        $log = $this->log();
        $this->assertStringContainsString('(Verschlüsselung none, mit Anmeldung) fehlgeschlagen – Anmeldung (AUTH PLAIN): Server antwortet 535', $log);
        $this->assertCredentialsNotIn($log);
        $this->assertCredentialsNotIn($mailer->lastError());
    }

    public function testUnsupportedAuthMethodIsReported(): void
    {
        $mailer = null;
        [$result, $transcript] = $this->converse('auth-cram', function (int $port) use (&$mailer): bool {
            $mailer = $this->mailer($port, 'none');

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertSame(['EHLO'], $this->commands($transcript));
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertSame('Anmeldung: Der Server bietet nur CRAM-MD5 an, unterstützt werden PLAIN und LOGIN.', $mailer->lastError());
    }

    public function testUserWithoutAuthSupportIsAnError(): void
    {
        $mailer = null;
        [$result, $transcript] = $this->converse('no-auth', function (int $port) use (&$mailer): bool {
            $mailer = $this->mailer($port, 'none');

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertSame(['EHLO'], $this->commands($transcript));
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertStringStartsWith('Anmeldung: Der Server bietet keine Anmeldung (AUTH) an', $mailer->lastError());
    }

    // ---------------------------------------------------------------
    //  Verschlüsselung (STARTTLS / SSL)
    // ---------------------------------------------------------------

    public function testStarttlsNotOfferedAbortsBeforeCredentialsAreSent(): void
    {
        $mailer = null;
        [$result, $transcript] = $this->converse('no-starttls', function (int $port) use (&$mailer): bool {
            $mailer = $this->mailer($port, 'starttls');

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        // Kein Rückfall auf eine unverschlüsselte Anmeldung
        $this->assertSame(['EHLO'], $this->commands($transcript));
        $this->assertCredentialsNotIn($transcript);
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertStringStartsWith('STARTTLS: Der Server bietet keine Verschlüsselung per STARTTLS an', $mailer->lastError());
        $this->assertStringContainsString('(Verschlüsselung starttls, mit Anmeldung) fehlgeschlagen – STARTTLS:', $this->log());
    }

    public function testStarttlsIsRequestedAndFailedHandshakeIsHandled(): void
    {
        $mailer = null;
        [$result, $transcript] = $this->converse('starttls-broken', function (int $port) use (&$mailer): bool {
            $mailer = $this->mailer($port, 'starttls');

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertSame(['EHLO', 'STARTTLS'], $this->commands($transcript));
        $this->assertCredentialsNotIn($transcript);
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertStringStartsWith('STARTTLS: TLS-Aushandlung fehlgeschlagen: ', $mailer->lastError());
        $this->assertCredentialsNotIn($this->log());
    }

    public function testStarttlsRefusedByServer(): void
    {
        $mailer = null;
        [$result, $transcript] = $this->converse('starttls-refused', function (int $port) use (&$mailer): bool {
            $mailer = $this->mailer($port, 'starttls');

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertSame(['EHLO', 'STARTTLS'], $this->commands($transcript));
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertSame('STARTTLS: Server antwortet 454 4.7.0 TLS not available due to temporary reason', $mailer->lastError());
    }

    public function testStarttlsIsUsedWithoutLoginToo(): void
    {
        [$result, $transcript] = $this->converse('starttls-broken', static fn (int $port): bool => new Mailer('127.0.0.1', $port, 5, 'starttls')->send('to@example.com', 'from@example.com', 's', 'b'));

        $this->assertFalse($result);
        $this->assertSame(['EHLO', 'STARTTLS'], $this->commands($transcript));
    }

    public function testImplicitTlsAgainstPlainServerFailsCleanly(): void
    {
        $mailer = null;
        [$result, $transcript] = $this->converse('ok', function (int $port) use (&$mailer): bool {
            $mailer = $this->mailer($port, 'ssl');

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertStringNotContainsString('EHLO', $transcript);
        $this->assertCredentialsNotIn($transcript);
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertStringStartsWith('SSL/TLS-Verbindung fehlgeschlagen: ', $mailer->lastError());
        $this->assertStringContainsString('(Verschlüsselung ssl, mit Anmeldung) fehlgeschlagen', $this->log());
    }

    public function testUnknownEncryptionIsNeverSentUnencrypted(): void
    {
        $mailer = new Mailer('127.0.0.1', 1, 1, 'tls-irgendwie', self::USER, self::PASSWORD);

        $this->assertFalse($mailer->send('to@example.com', 'from@example.com', 's', 'b'));
        $this->assertSame('Unbekannte Verschlüsselung „tls-irgendwie“ – erlaubt sind none, starttls und ssl.', $mailer->lastError());
        $this->assertCredentialsNotIn($this->log());
    }

    public function testFromConfigReadsCredentialsAndEncryption(): void
    {
        $mailer = null;
        [$result, $transcript] = $this->converse('no-starttls', static function (int $port) use (&$mailer): bool {
            $mailer = Mailer::fromConfig([
                'host' => '127.0.0.1',
                'port' => (string) $port,
                'user' => self::USER,
                'password' => self::PASSWORD,
                'encryption' => ' STARTTLS ',
            ]);

            return $mailer->send('to@example.com', 'from@example.com', 's', 'b');
        });

        $this->assertFalse($result);
        $this->assertSame(['EHLO'], $this->commands($transcript));
        $this->assertInstanceOf(Mailer::class, $mailer);
        $this->assertStringStartsWith('STARTTLS:', $mailer->lastError());
    }

    public function testFromConfigWithInvalidEncryptionTypeDoesNotSend(): void
    {
        $mailer = Mailer::fromConfig(['host' => '127.0.0.1', 'port' => 1, 'encryption' => true]);

        $this->assertFalse($mailer->send('to@example.com', 'from@example.com', 's', 'b'));
        $this->assertStringStartsWith('Unbekannte Verschlüsselung', $mailer->lastError());
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function encryptions(): array
    {
        return [
            'none' => ['none', 'none'],
            'leer' => ['', 'none'],
            'starttls' => ['starttls', 'starttls'],
            'Großbuchstaben und Leerzeichen' => [' STARTTLS ', 'starttls'],
            'ssl' => ['ssl', 'ssl'],
            'tls ist mehrdeutig' => ['tls', null],
            'Tippfehler' => ['startls', null],
        ];
    }

    #[DataProvider('encryptions')]
    public function testNormalizeEncryption(string $value, ?string $expected): void
    {
        $this->assertSame($expected, Mailer::normalizeEncryption($value));
    }

    public function testDefaultPortsMatchTheEncryptions(): void
    {
        $this->assertSame(['none' => 25, 'starttls' => 587, 'ssl' => 465], Mailer::DEFAULT_PORTS);
    }

    public function testParseExtensions(): void
    {
        $extensions = Mailer::parseExtensions([
            '250-mail.example.com Hello [10.0.0.5]',
            '250-SIZE 35882577',
            '250-auth login plain',
            '250-AUTH=LOGIN',
            '250-STARTTLS',
            '250 8BITMIME',
        ]);

        $this->assertSame(['SIZE', 'AUTH', 'STARTTLS', '8BITMIME'], array_keys($extensions));
        $this->assertSame(['LOGIN', 'PLAIN'], $extensions['AUTH']);
        $this->assertSame([], $extensions['STARTTLS']);
        $this->assertSame([], Mailer::parseExtensions(['250 mail.example.com']));
    }

    /**
     * @return array<string, array{array<string, string>, string|false, string|false, string}>
     */
    public static function heloNames(): array
    {
        return [
            'Servername der Website' => [['SERVER_NAME' => 'Forum.Example.com'], 'web01', '10.0.0.5:41234', 'forum.example.com'],
            'Hostname des Servers' => [['SERVER_NAME' => 'localhost'], 'web01.hoster.example', '10.0.0.5:41234', 'web01.hoster.example'],
            'IPv4-Adressliteral' => [[], 'a1b2c3d4', '10.0.0.5:41234', '[10.0.0.5]'],
            'IPv6-Adressliteral' => [[], false, '[2001:db8::5]:41234', '[IPv6:2001:db8::5]'],
            'Schadcode im Host-Header' => [['SERVER_NAME' => "evil.example\r\nRCPT TO:<x@y.z>"], false, false, 'localhost.localdomain'],
            'nichts bekannt' => [[], false, false, 'localhost.localdomain'],
        ];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('heloNames')]
    public function testHeloName(array $server, string|false $hostname, string|false $localAddress, string $expected): void
    {
        $this->assertSame($expected, Mailer::heloName($server, $hostname, $localAddress));
    }

    public function testWithTlsOptionsReturnsCopy(): void
    {
        $mailer = new Mailer('127.0.0.1', 1, 1);

        $this->assertNotSame($mailer, $mailer->withTlsOptions(['cafile' => '/tmp/ca.pem']));
    }

    private function mailer(int $port, string $encryption): Mailer
    {
        return new Mailer('127.0.0.1', $port, 5, $encryption, self::USER, self::PASSWORD);
    }

    private function log(): string
    {
        return (string) file_get_contents($this->logFile);
    }

    private function assertCredentialsNotIn(string $text): void
    {
        $this->assertStringNotContainsString(self::PASSWORD, $text);
        $this->assertStringNotContainsString(base64_encode(self::PASSWORD), $text);
        $this->assertStringNotContainsString(base64_encode("\0" . self::USER . "\0" . self::PASSWORD), $text);
        $this->assertStringNotContainsString('AUTH PLAIN ', $text);
    }
}
