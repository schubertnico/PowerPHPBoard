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
use SensitiveParameter;

/**
 * Versand über SMTP (in der Entwicklung Mailpit). Alle Mails des Forums
 * laufen über diese Klasse; PHPs mail() wird nicht verwendet, weil es
 * ohne lokales sendmail stillschweigend scheitert.
 *
 * Verbindung unverschlüsselt (Mailpit, interne Relays), per STARTTLS (meist
 * Port 587) oder von Beginn an per SSL/TLS (meist Port 465). Mit Benutzer
 * meldet sich der Mailer per AUTH PLAIN oder AUTH LOGIN an – über eine
 * unverschlüsselte Verbindung nur, wenn ausdrücklich "none" eingestellt ist.
 * Das Zertifikat des Servers wird immer geprüft. Fehler landen mit
 * Antwortcode und Kurztext im Fehlerprotokoll, Passwort und AUTH-Zeilen nie.
 */
final class Mailer
{
    public const string ENCRYPTION_NONE = 'none';

    public const string ENCRYPTION_STARTTLS = 'starttls';

    public const string ENCRYPTION_SSL = 'ssl';

    /**
     * Platzhalter-Absender aus den Vorgaben (LocalConfig::DEFAULT_MAIL) und
     * aus config.inc.php bis 2.2.x; er gilt als „kein Absender eingestellt“.
     */
    public const string PLACEHOLDER_SENDER = 'noreply@powerphpboard.local';

    /**
     * Erlaubte Verschlüsselungen und ihr üblicher Port.
     */
    public const array DEFAULT_PORTS = [
        self::ENCRYPTION_NONE => 25,
        self::ENCRYPTION_STARTTLS => 587,
        self::ENCRYPTION_SSL => 465,
    ];

    /** @var array<string, mixed> zusätzliche SSL-Kontextoptionen, siehe withTlsOptions() */
    private array $tlsOptions = [];

    private string $lastError = '';

    public function __construct(
        private string $smtpHost = 'mailpit',
        private int $smtpPort = 1025,
        private int $timeoutSeconds = 5,
        private string $encryption = self::ENCRYPTION_NONE,
        private string $username = '',
        #[SensitiveParameter]
        private string $password = '',
    ) {
    }

    /**
     * Mailer aus der Konfiguration ($mail in config.inc.php)
     *
     * @param array<string, mixed> $mailConfig
     */
    public static function fromConfig(#[SensitiveParameter] array $mailConfig): self
    {
        $host = $mailConfig['host'] ?? 'mailpit';
        $port = $mailConfig['port'] ?? 1025;
        $encryption = $mailConfig['encryption'] ?? self::ENCRYPTION_NONE;
        $user = $mailConfig['user'] ?? '';
        $password = $mailConfig['password'] ?? '';

        return new self(
            is_string($host) && $host !== '' ? $host : 'mailpit',
            is_int($port) || (is_string($port) && ctype_digit($port)) ? (int) $port : 1025,
            5,
            // Kein Text: ungültig, damit nie stillschweigend unverschlüsselt gesendet wird
            is_string($encryption) ? $encryption : '?',
            is_string($user) ? $user : '',
            is_string($password) ? $password : ''
        );
    }

    /**
     * In der Konfiguration eingestellter Absender ($mail['from'] bzw.
     * PPB_MAIL_FROM oder das Installer-Feld „Absenderadresse“), sonst null.
     * Leer, ungültig oder der Platzhalter aus den Vorgaben gilt als „nicht
     * eingestellt“.
     *
     * @param array<string, mixed> $mailConfig $mail aus config.inc.php
     */
    public static function configuredSender(#[SensitiveParameter] array $mailConfig): ?string
    {
        $from = $mailConfig['from'] ?? '';
        if (!is_string($from)) {
            return null;
        }
        $from = trim($from);

        return Security::isValidEmail($from) && strcasecmp($from, self::PLACEHOLDER_SENDER) !== 0 ? $from : null;
    }

    /**
     * Absenderadresse (From) aller Mails des Forums.
     *
     * Viele Hoster verlangen – und SPF/DMARC prüfen –, dass der Absender zum
     * angemeldeten SMTP-Postfach bzw. zur eigenen Domain passt. Deshalb:
     * 1. der eingestellte Absender aus der Konfiguration (configuredSender()),
     * 2. sonst die Admin-E-Mail aus den Einstellungen,
     * 3. sonst der Platzhalter.
     *
     * @param array<string, mixed> $settings Zeile aus ppb_config
     * @param array<string, mixed> $mailConfig $mail aus config.inc.php
     */
    public static function senderAddress(array $settings, #[SensitiveParameter] array $mailConfig): string
    {
        return self::configuredSender($mailConfig) ?? self::adminAddress($settings) ?? self::PLACEHOLDER_SENDER;
    }

    /**
     * Antwortadresse (Reply-To) für Mails des Forums: die Admin-E-Mail, wenn
     * ein anderer Absender eingestellt ist – Antworten erreichen so den
     * Administrator statt des Versandpostfachs. Sonst null (kein Reply-To).
     * Mails von Benutzer zu Benutzer setzen stattdessen die Adresse des
     * schreibenden Mitglieds (sendmail.php).
     *
     * @param array<string, mixed> $settings Zeile aus ppb_config
     * @param array<string, mixed> $mailConfig $mail aus config.inc.php
     */
    public static function replyToAddress(array $settings, #[SensitiveParameter] array $mailConfig): ?string
    {
        $admin = self::adminAddress($settings);
        if ($admin === null || strcasecmp($admin, self::senderAddress($settings, $mailConfig)) === 0) {
            return null;
        }

        return $admin;
    }

    /**
     * Verschlüsselung aus der Konfiguration: none, starttls oder ssl
     * (Groß-/Kleinschreibung egal, leer = none). Unbekannte Werte ergeben
     * null – dann wird nicht gesendet.
     */
    public static function normalizeEncryption(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return self::ENCRYPTION_NONE;
        }

        return array_key_exists($value, self::DEFAULT_PORTS) ? $value : null;
    }

    /**
     * Zusätzliche SSL-Kontextoptionen, z. B. ['cafile' => '/pfad/ca.pem'] für
     * einen Mailserver mit eigener Zertifizierungsstelle (Testumgebung).
     * Die Zertifikatsprüfung bleibt eingeschaltet, solange sie hier nicht
     * ausdrücklich abgeschaltet wird.
     *
     * @param array<string, mixed> $options
     */
    public function withTlsOptions(array $options): self
    {
        $clone = clone $this;
        $clone->tlsOptions = $options + $this->tlsOptions;

        return $clone;
    }

    /**
     * Grund des letzten Fehlschlags von send() ohne Zugangsdaten, sonst leer.
     */
    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * @param string|null $replyTo Optionale Antwortadresse (z. B. Absender einer Benutzer-Mail)
     *
     * @return bool true nur, wenn der SMTP-Server die Mail angenommen hat
     */
    public function send(string $to, string $from, string $subject, string $body, ?string $replyTo = null): bool
    {
        $this->lastError = self::addressError($to, $from, $replyTo);
        if ($this->lastError !== '') {
            return false;
        }

        $message = self::dotStuff(self::buildMessage($to, $from, $subject, $body, $replyTo)) . ".\r\n";

        try {
            [$connection, $mode] = $this->connect();
            try {
                $this->converse($connection, $mode, $from, $to, $message);
            } finally {
                $connection->close();
            }
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            error_log(sprintf(
                '[Mailer] Versand über %s:%d (Verschlüsselung %s, %s) fehlgeschlagen – %s',
                SmtpConnection::clean($this->smtpHost),
                $this->smtpPort,
                SmtpConnection::clean($this->encryption === '' ? self::ENCRYPTION_NONE : $this->encryption),
                $this->username !== '' ? 'mit Anmeldung' : 'ohne Anmeldung',
                $this->lastError
            ));

            return false;
        }

        return true;
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
     * Erweiterungen aus der EHLO-Antwort, z. B. ['STARTTLS' => [], 'AUTH' => ['PLAIN', 'LOGIN']].
     * Versteht auch die alte Schreibweise "AUTH=LOGIN".
     *
     * @param list<string> $lines Antwortzeilen ohne Zeilenende
     *
     * @return array<string, list<string>>
     */
    public static function parseExtensions(array $lines): array
    {
        $extensions = [];
        foreach (array_slice($lines, 1) as $line) {
            $words = preg_split('/[\s=]+/', strtoupper(trim(substr($line, 4))), -1, PREG_SPLIT_NO_EMPTY);
            if ($words === false || $words === []) {
                continue;
            }
            $keyword = array_shift($words);
            $extensions[$keyword] = array_values(array_unique(array_merge($extensions[$keyword] ?? [], $words)));
        }

        return $extensions;
    }

    /**
     * Name für EHLO/HELO (RFC 5321, 4.1.3): vollständiger Hostname des
     * Webservers, sonst die eigene IP-Adresse als Adressliteral.
     *
     * @param array<array-key, mixed> $server $_SERVER
     * @param string|false $hostname gethostname()
     * @param string|false $localAddress eigene Adresse der Verbindung, z. B. "10.0.0.5:41234"
     */
    public static function heloName(array $server, string|false $hostname, string|false $localAddress): string
    {
        foreach ([$server['SERVER_NAME'] ?? null, $hostname] as $candidate) {
            if (is_string($candidate) && self::isFqdn($candidate)) {
                return strtolower($candidate);
            }
        }

        $ip = is_string($localAddress) ? trim((string) preg_replace('/:\d+$/', '', $localAddress), '[]') : '';
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return '[' . $ip . ']';
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[IPv6:' . $ip . ']' : 'localhost.localdomain';
    }

    /**
     * @return array{0: SmtpConnection, 1: string} Verbindung und Verschlüsselung
     */
    private function connect(): array
    {
        $mode = self::normalizeEncryption($this->encryption);
        if ($mode === null) {
            throw new RuntimeException(
                'Unbekannte Verschlüsselung „' . SmtpConnection::clean($this->encryption) . '“ – erlaubt sind none, starttls und ssl.'
            );
        }
        if ($mode !== self::ENCRYPTION_NONE && !extension_loaded('openssl')) {
            throw new RuntimeException('Für verschlüsselten Versand fehlt die PHP-Erweiterung openssl.');
        }

        $connection = SmtpConnection::open(
            $this->smtpHost,
            $this->smtpPort,
            $mode === self::ENCRYPTION_SSL,
            $this->timeoutSeconds,
            $this->tlsOptions
        );

        return [$connection, $mode];
    }

    /**
     * Der eigentliche SMTP-Dialog; wirft bei jeder unerwarteten Antwort.
     */
    private function converse(SmtpConnection $connection, string $mode, string $from, string $to, string $message): void
    {
        $connection->expect('Begrüßung', [220]);
        $extensions = $this->hello($connection, $mode !== self::ENCRYPTION_NONE || $this->username !== '');

        if ($mode === self::ENCRYPTION_STARTTLS) {
            if (!array_key_exists('STARTTLS', $extensions)) {
                throw new RuntimeException(
                    'STARTTLS: Der Server bietet keine Verschlüsselung per STARTTLS an – '
                    . 'bitte Port und Verschlüsselung prüfen (STARTTLS meist Port 587, SSL/TLS meist Port 465).'
                );
            }
            $connection->command('STARTTLS', 'STARTTLS', [220]);
            $connection->enableTls('STARTTLS');
            // Nach STARTTLS gilt nichts mehr aus der unverschlüsselten Sitzung (RFC 3207, 4.2)
            $extensions = $this->hello($connection, true);
        }
        if ($this->username !== '') {
            $this->authenticate($connection, $mode, $extensions);
        }

        $connection->command('MAIL FROM:<' . $from . '>', 'Absender (MAIL FROM)', [250]);
        $connection->command('RCPT TO:<' . $to . '>', 'Empfänger (RCPT TO)', [250, 251]);
        $connection->command('DATA', 'DATA', [354]);
        $connection->write($message, 'Nachricht');
        $connection->expect('Nachricht', [250]);
        $connection->quit();
    }

    /**
     * EHLO, bei alten Servern ohne ESMTP ersatzweise HELO.
     *
     * @return array<string, list<string>> angebotene Erweiterungen
     */
    private function hello(SmtpConnection $connection, bool $needsEsmtp): array
    {
        $name = self::heloName($_SERVER, gethostname(), $connection->localAddress());
        $connection->write('EHLO ' . $name . "\r\n", 'EHLO');
        $reply = $connection->readReply('EHLO');
        if ($reply['code'] === 250) {
            return self::parseExtensions($reply['lines']);
        }
        if ($needsEsmtp || $reply['code'] < 500) {
            throw new RuntimeException(SmtpConnection::replyError('EHLO', $reply));
        }
        $connection->command('HELO ' . $name, 'HELO', [250]);

        return [];
    }

    /**
     * Anmeldung per AUTH PLAIN (bevorzugt) oder AUTH LOGIN. Fehlermeldungen
     * enthalten nur die Antworten des Servers, nie die gesendeten Zeilen.
     *
     * @param array<string, list<string>> $extensions
     */
    private function authenticate(SmtpConnection $connection, string $mode, array $extensions): void
    {
        if ($mode !== self::ENCRYPTION_NONE && !$connection->isEncrypted()) {
            throw new RuntimeException('Anmeldung: abgebrochen, weil die Verbindung nicht verschlüsselt ist.');
        }

        $methods = $extensions['AUTH'] ?? null;
        if ($methods === null) {
            throw new RuntimeException(
                'Anmeldung: Der Server bietet keine Anmeldung (AUTH) an'
                . ($mode === self::ENCRYPTION_NONE ? ' – viele Server erlauben sie erst nach STARTTLS.' : '.')
            );
        }

        if (in_array('PLAIN', $methods, true)) {
            $stage = 'Anmeldung (AUTH PLAIN)';
            $connection->command('AUTH PLAIN ' . base64_encode("\0" . $this->username . "\0" . $this->password), $stage, [235]);
            return;
        }

        if (in_array('LOGIN', $methods, true)) {
            $stage = 'Anmeldung (AUTH LOGIN)';
            $connection->command('AUTH LOGIN', $stage, [334]);
            $connection->command(base64_encode($this->username), $stage, [334]);
            $connection->command(base64_encode($this->password), $stage, [235]);
            return;
        }

        throw new RuntimeException(
            'Anmeldung: Der Server bietet nur ' . SmtpConnection::clean(implode(', ', $methods))
            . ' an, unterstützt werden PLAIN und LOGIN.'
        );
    }

    /**
     * Admin-E-Mail aus den Einstellungen, wenn gültig
     *
     * @param array<string, mixed> $settings
     */
    private static function adminAddress(array $settings): ?string
    {
        $admin = $settings['adminemail'] ?? '';

        return is_string($admin) && Security::isValidEmail($admin) ? $admin : null;
    }

    /**
     * Fehlermeldung für ungültige Adressen, sonst leer.
     */
    private static function addressError(string $to, string $from, ?string $replyTo): string
    {
        if (!Security::isValidEmail($to) || !Security::isValidEmail($from)) {
            return 'Ungültige Empfänger- oder Absenderadresse.';
        }

        return $replyTo !== null && !Security::isValidEmail($replyTo) ? 'Ungültige Antwortadresse.' : '';
    }

    private static function isFqdn(string $name): bool
    {
        return strlen($name) <= 253
            && preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/', $name) === 1;
    }
}
