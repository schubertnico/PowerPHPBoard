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

final class Mailer
{
    public function __construct(
        private string $smtpHost = 'mailpit',
        private int $smtpPort = 1025,
        private int $timeoutSeconds = 5,
    ) {
    }

    public function send(string $to, string $from, string $subject, string $body): bool
    {
        if (!Security::isValidEmail($to) || !Security::isValidEmail($from)) {
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
            $msg = self::buildMessage($to, $from, $subject, $body) . ".\r\n";
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

    public static function buildMessage(string $to, string $from, string $subject, string $body): string
    {
        if (!Security::isValidEmail($to)) {
            throw new InvalidArgumentException('Invalid recipient');
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
