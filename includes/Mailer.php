<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - SMTP Mailer
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

use InvalidArgumentException;
use RuntimeException;

/**
 * Versand über SMTP (in der Entwicklung Mailpit). Alle Mails des Forums
 * laufen über diese Klasse; PHPs mail() wird nicht verwendet, weil es
 * ohne lokales sendmail stillschweigend scheitert.
 */
final class Mailer
{
    public function __construct(
        private string $smtpHost = 'mailpit',
        private int $smtpPort = 1025,
        private int $timeoutSeconds = 5,
    ) {
    }

    /**
     * Mailer aus der Konfiguration ($mail in config.inc.php)
     *
     * @param array<string, mixed> $mailConfig
     */
    public static function fromConfig(array $mailConfig): self
    {
        $host = $mailConfig['host'] ?? 'mailpit';
        $port = $mailConfig['port'] ?? 1025;

        return new self(
            is_string($host) && $host !== '' ? $host : 'mailpit',
            is_int($port) || (is_string($port) && ctype_digit($port)) ? (int) $port : 1025
        );
    }

    /**
     * Absenderadresse: Admin-E-Mail aus den Einstellungen, sonst die aus
     * der Konfiguration
     *
     * @param array<string, mixed> $settings Zeile aus ppb_config
     * @param array<string, mixed> $mailConfig $mail aus config.inc.php
     */
    public static function senderAddress(array $settings, array $mailConfig): string
    {
        $admin = $settings['adminemail'] ?? '';
        if (is_string($admin) && Security::isValidEmail($admin)) {
            return $admin;
        }
        $from = $mailConfig['from'] ?? '';

        return is_string($from) && Security::isValidEmail($from) ? $from : 'noreply@powerphpboard.local';
    }

    /**
     * @param string|null $replyTo Optionale Antwortadresse (z. B. Absender einer Benutzer-Mail)
     *
     * @return bool true nur, wenn der SMTP-Server die Mail angenommen hat
     */
    public function send(string $to, string $from, string $subject, string $body, ?string $replyTo = null): bool
    {
        if (!Security::isValidEmail($to) || !Security::isValidEmail($from)) {
            return false;
        }
        if ($replyTo !== null && !Security::isValidEmail($replyTo)) {
            return false;
        }

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->smtpHost, $this->smtpPort),
            $errno,
            $errstr,
            $this->timeoutSeconds
        );
        if ($socket === false) {
            error_log(sprintf('[Mailer] SMTP connect failed: %s (%d)', $errstr, $errno));
            return false;
        }

        try {
            $this->expect($socket, '220');
            $this->cmd($socket, 'HELO powerphpboard', '250');
            $this->cmd($socket, 'MAIL FROM:<' . $from . '>', '250');
            $this->cmd($socket, 'RCPT TO:<' . $to . '>', '250');
            $this->cmd($socket, 'DATA', '354');
            $msg = self::dotStuff(self::buildMessage($to, $from, $subject, $body, $replyTo)) . ".\r\n";
            fwrite($socket, $msg);
            $this->expect($socket, '250');
            $this->cmd($socket, 'QUIT', '221');
            return true;
        } catch (RuntimeException $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return false;
        } finally {
            fclose($socket);
        }
    }

    /**
     * SMTP-Punktverdopplung (RFC 5321, 4.5.2): Zeilen, die mit "." beginnen,
     * bekommen einen zweiten Punkt. Sonst beendet eine Zeile "." im Text die
     * Nachricht vorzeitig, und alles danach würde der Server als SMTP-Befehl
     * ausführen (z. B. weitere Empfänger – das Forum als Spam-Relais).
     */
    public static function dotStuff(string $message): string
    {
        return (string) preg_replace('/^\./m', '..', $message);
    }

    public static function buildMessage(string $to, string $from, string $subject, string $body, ?string $replyTo = null): string
    {
        if (!Security::isValidEmail($to)) {
            throw new InvalidArgumentException('Invalid recipient');
        }
        if ($replyTo !== null && !Security::isValidEmail($replyTo)) {
            throw new InvalidArgumentException('Invalid reply-to address');
        }
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $normalizedBody = (string) preg_replace("/\r\n|\r|\n/", "\r\n", $body);
        $headers = [
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Date: ' . date(DATE_RFC2822),
        ];
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        return implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody . "\r\n";
    }

    /**
     * @param resource $socket
     */
    private function cmd($socket, string $cmd, string $expect): void
    {
        fwrite($socket, $cmd . "\r\n");
        $this->expect($socket, $expect);
    }

    /**
     * @param resource $socket
     */
    private function expect($socket, string $expected): void
    {
        $line = fgets($socket, 1024);
        if ($line === false || !str_starts_with($line, $expected)) {
            throw new RuntimeException('SMTP expected ' . $expected . ', got ' . ($line === false ? '' : $line));
        }
        while (preg_match('/^' . preg_quote($expected, '/') . '-/', $line) === 1) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SMTP unexpected close');
            }
        }
    }
}
