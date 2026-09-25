<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - SMTP-Verbindung
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

use RuntimeException;

/**
 * Eine SMTP-Verbindung für den Mailer: Zeilen senden, auch mehrzeilige
 * Antworten lesen, TLS einschalten.
 *
 * Fehler werfen eine RuntimeException mit Arbeitsschritt, Antwortcode und
 * Kurztext des Servers. Die gesendeten Zeilen erscheinen darin nie – sie
 * können Zugangsdaten enthalten (AUTH).
 */
final class SmtpConnection
{
    /**
     * Nur TLS 1.2 und 1.3 – ältere Versionen gelten als unsicher (RFC 8996).
     */
    public const int CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;

    private const int MAX_REPLY_LINES = 100;

    private const int MAX_DETAIL_LENGTH = 200;

    /**
     * @param resource $socket
     */
    private function __construct(private $socket)
    {
    }

    /**
     * Baut die Verbindung auf – bei $implicitTls von Beginn an verschlüsselt
     * (SSL/TLS, meist Port 465). Das Zertifikat wird geprüft; $tlsOptions
     * ergänzen oder ersetzen die SSL-Kontextoptionen (z. B. cafile).
     *
     * @param array<string, mixed> $tlsOptions
     */
    public static function open(string $host, int $port, bool $implicitTls, int $timeout, array $tlsOptions = []): self
    {
        $context = stream_context_create(['ssl' => $tlsOptions + [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => trim($host, '[]'),
            'SNI_enabled' => true,
            'crypto_method' => self::CRYPTO_METHOD,
        ]]);
        $address = sprintf('%s://%s:%d', $implicitTls ? 'ssl' : 'tcp', $host, $port);

        $errno = 0;
        $errstr = '';

        [$socket, $warnings] = self::quietly(
            static function () use ($address, $timeout, $context, &$errno, &$errstr) {
                return stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
            }
        );
        if (!is_resource($socket)) {
            // Die Warnungen nennen den Grund, bei ssl:// auch den von OpenSSL (z. B. Zertifikat)
            $reasons = $warnings !== [] ? $warnings : [sprintf('%s (%d)', $errstr, $errno)];
            throw new RuntimeException(
                ($implicitTls ? 'SSL/TLS-Verbindung' : 'Verbindungsaufbau') . ' fehlgeschlagen: ' . self::warningDetail($reasons)
            );
        }
        stream_set_timeout($socket, $timeout);

        return new self($socket);
    }

    /**
     * Sendet einen Befehl und prüft den Antwortcode.
     *
     * @param list<int> $expected
     *
     * @return list<string> Antwortzeilen
     */
    public function command(string $line, string $stage, array $expected): array
    {
        $this->write($line . "\r\n", $stage);

        return $this->expect($stage, $expected);
    }

    public function write(string $data, string $stage): void
    {
        while ($data !== '') {
            $written = @fwrite($this->socket, $data);
            if ($written === false || $written === 0) {
                throw $this->lost($stage);
            }
            $data = substr($data, $written);
        }
    }

    /**
     * @param list<int> $expected
     *
     * @return list<string> Antwortzeilen
     */
    public function expect(string $stage, array $expected): array
    {
        $reply = $this->readReply($stage);
        if (!in_array($reply['code'], $expected, true)) {
            throw new RuntimeException(self::replyError($stage, $reply));
        }

        return $reply['lines'];
    }

    /**
     * Liest eine (auch mehrzeilige) Antwort. code = 0 bei einer Antwort,
     * die nicht mit einem dreistelligen SMTP-Code beginnt.
     *
     * @return array{code: int, lines: list<string>}
     */
    public function readReply(string $stage): array
    {
        $lines = [];
        $count = 0;
        do {
            $line = @fgets($this->socket, 1024);
            if ($line === false) {
                throw $this->lost($stage);
            }
            $line = rtrim($line, "\r\n");
            $lines[] = $line;
            ++$count;
        } while (preg_match('/^\d{3}-/', $line) === 1 && $count < self::MAX_REPLY_LINES);

        return [
            'code' => preg_match('/^(\d{3})(?:[ -]|$)/', $line, $match) === 1 ? (int) $match[1] : 0,
            'lines' => $lines,
        ];
    }

    /**
     * Schaltet nach STARTTLS die Verschlüsselung ein (Zertifikat wird geprüft).
     */
    public function enableTls(string $stage): void
    {
        $socket = $this->socket;
        [$result, $warnings] = self::quietly(
            static fn () => stream_socket_enable_crypto($socket, true, self::CRYPTO_METHOD)
        );
        if ($result !== true) {
            throw new RuntimeException($stage . ': TLS-Aushandlung fehlgeschlagen: ' . self::warningDetail($warnings));
        }
    }

    public function isEncrypted(): bool
    {
        return array_key_exists('crypto', stream_get_meta_data($this->socket));
    }

    /**
     * Eigene Adresse der Verbindung, z. B. "10.0.0.5:41234".
     */
    public function localAddress(): string|false
    {
        return stream_socket_get_name($this->socket, false);
    }

    /**
     * Verabschiedung ohne Fehlerprüfung – die Nachricht ist dann schon
     * angenommen, eine fehlende Antwort auf QUIT ändert daran nichts.
     */
    public function quit(): void
    {
        if (@fwrite($this->socket, "QUIT\r\n") !== false) {
            @fgets($this->socket, 1024);
        }
    }

    public function close(): void
    {
        $socket = $this->socket;
        @fclose($socket);
    }

    /**
     * Einzeiliger, gekürzter Text für Fehlerprotokoll und Anzeige.
     */
    public static function clean(string $text): string
    {
        $text = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', mb_scrub($text, 'UTF-8')));

        return mb_strlen($text) > self::MAX_DETAIL_LENGTH ? mb_substr($text, 0, self::MAX_DETAIL_LENGTH) . ' …' : $text;
    }

    private function lost(string $stage): RuntimeException
    {
        return new RuntimeException($stage . ': ' . (stream_get_meta_data($this->socket)['timed_out']
            ? 'Zeitüberschreitung – der Server antwortet nicht.'
            : 'Der Server hat die Verbindung beendet.'));
    }

    /**
     * Fehlermeldung zu einer unerwarteten Antwort: Arbeitsschritt, Code und Kurztext.
     *
     * @param array{code: int, lines: list<string>} $reply
     */
    public static function replyError(string $stage, array $reply): string
    {
        if ($reply['code'] === 0) {
            return $stage . ': unerwartete Antwort – ist das ein SMTP-Server?';
        }

        $text = self::clean(substr((string) end($reply['lines']), 4));
        $hint = $reply['code'] === 535 ? ' – Benutzername und Passwort prüfen.' : '';

        return $stage . ': Server antwortet ' . $reply['code'] . ($text !== '' ? ' ' . $text : '') . $hint;
    }

    /**
     * Gesammelte PHP-Warnungen ohne Funktionsnamen, z. B. die OpenSSL-Meldung
     * "certificate verify failed".
     *
     * @param list<string> $warnings
     */
    private static function warningDetail(array $warnings): string
    {
        $messages = array_values(array_unique(array_map(
            static fn (string $warning): string => (string) preg_replace('/^\w+\(\): /', '', $warning),
            $warnings
        )));

        return $messages === [] ? 'unbekannter Fehler' : self::clean(implode(' / ', $messages));
    }

    /**
     * Führt $operation aus und sammelt PHP-Warnungen (z. B. von OpenSSL),
     * statt sie auszugeben oder ins allgemeine Fehlerprotokoll zu schreiben.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return array{0: T, 1: list<string>}
     */
    private static function quietly(callable $operation): array
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        });
        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        return [$result, $warnings];
    }
}
