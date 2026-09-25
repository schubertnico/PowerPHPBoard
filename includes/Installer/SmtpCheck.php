<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Test-Mail aus dem Web-Installer
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use PowerPHPBoard\Mailer;
use SensitiveParameter;

/**
 * „Test-Mail senden“ in Schritt 3: prüft die SMTP-Angaben, bevor das Forum
 * eingerichtet wird, und meldet ehrlich, was dabei herauskam.
 *
 * @phpstan-import-type MailConfig from LocalConfig
 */
final class SmtpCheck
{
    public const string SUBJECT = 'PowerPHPBoard: Test-Mail aus dem Installer';

    /**
     * Kurzbezeichnung der Verschlüsselung für Zusammenfassungen.
     */
    private const array ENCRYPTION_NAMES = [
        Mailer::ENCRYPTION_NONE => 'ohne Verschlüsselung',
        Mailer::ENCRYPTION_STARTTLS => 'STARTTLS',
        Mailer::ENCRYPTION_SSL => 'SSL/TLS',
    ];

    /**
     * Einstellungen in einer Zeile, ohne Passwort – z. B.
     * „smtp.example.com:587, STARTTLS, Anmeldung als forum@example.com“.
     *
     * @param MailConfig $mail
     */
    public static function describe(#[SensitiveParameter] array $mail): string
    {
        return $mail['host'] . ':' . $mail['port']
            . ', ' . (self::ENCRYPTION_NAMES[$mail['encryption']] ?? $mail['encryption'])
            . ', ' . ($mail['user'] !== '' ? 'Anmeldung als ' . $mail['user'] : 'ohne Anmeldung');
    }

    /**
     * @param MailConfig $mail
     */
    public static function body(#[SensitiveParameter] array $mail, string $sender, ?string $replyTo = null): string
    {
        return "Hallo,\n\n"
            . "diese Nachricht hat der Installer von PowerPHPBoard verschickt, um den E-Mail-Versand zu prüfen.\n"
            . "Wenn sie angekommen ist, stimmen die SMTP-Einstellungen.\n\n"
            . 'Verbindung: ' . self::describe($mail) . "\n"
            . 'Absender: ' . $sender . "\n"
            . ($replyTo !== null ? 'Antworten an: ' . $replyTo . "\n" : '')
            . "\nSie müssen nichts weiter tun.\n";
    }

    /**
     * Schickt die Test-Mail an $recipient – mit demselben Absender (From)
     * und derselben Antwortadresse (Reply-To) wie alle Mails des Forums.
     * Erfolg heißt: Der Mailserver hat sie angenommen – ob sie ankommt,
     * zeigt erst das Postfach.
     *
     * @param MailConfig $mail
     *
     * @return array{ok: bool, message: string}
     */
    public static function send(Mailer $mailer, #[SensitiveParameter] array $mail, string $recipient, string $sender, ?string $replyTo = null): array
    {
        if ($mailer->send($recipient, $sender, self::SUBJECT, self::body($mail, $sender, $replyTo), $replyTo)) {
            return [
                'ok' => true,
                'message' => 'Der Mailserver hat die Test-Mail an ' . $recipient . ' angenommen. Bitte sehen Sie im '
                    . 'Postfach nach (auch im Spam-Ordner) – erst dort zeigt sich, ob sie wirklich ankommt.',
            ];
        }

        return [
            'ok' => false,
            'message' => 'Die Test-Mail konnte nicht verschickt werden. ' . $mailer->lastError(),
        ];
    }
}
