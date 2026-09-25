<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Eingabeprüfung des Web-Installers
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use PowerPHPBoard\Mailer;
use PowerPHPBoard\Security;
use PowerPHPBoard\Validator;
use SensitiveParameter;

/**
 * Prüft die Formulare der Installer-Schritte 2 bis 4.
 *
 * Jede Methode liefert die bereinigten Werte und eine Liste von
 * Fehlermeldungen, deren Schlüssel dem Feldnamen im Formular entspricht.
 *
 * @phpstan-import-type MysqlConfig from LocalConfig
 * @phpstan-import-type MailConfig from LocalConfig
 *
 * @phpstan-type ForumSettings array{boardtitle: string, boardurl: string, adminemail: string, language: string, mail: MailConfig|null}
 * @phpstan-type AdminInput array{username: string, email: string, password: string}
 */
final class FormValidator
{
    public const int BOARDTITLE_MAX = 200;

    public const int BOARDURL_MAX = 250;

    public const int EMAIL_MAX = 100;

    public const int DB_NAME_MAX = 64;

    public const int DB_USER_MAX = 80;

    public const int HOST_MAX = 255;

    public const int DEFAULT_DB_PORT = 3306;

    public const int SMTP_USER_MAX = 255;

    public const int SMTP_PASSWORD_MAX = 255;

    /**
     * Verschlüsselung des Mailversands (Wert => Beschriftung), Werte wie in $mail['encryption'].
     */
    public const array ENCRYPTIONS = [
        Mailer::ENCRYPTION_NONE => 'Keine',
        Mailer::ENCRYPTION_STARTTLS => 'STARTTLS (meist Port 587)',
        Mailer::ENCRYPTION_SSL => 'SSL/TLS (meist Port 465)',
    ];

    /**
     * Forumsprachen wie in ppb_config.language (Wert => Beschriftung).
     */
    public const array LANGUAGES = [
        'Deutsch-Sie' => 'Deutsch (Sie-Form)',
        'Deutsch-Du' => 'Deutsch (Du-Form)',
        'English' => 'English',
    ];

    /**
     * Schritt 2: Datenbankzugang.
     *
     * Das Passwort wird bewusst nicht getrimmt – Datenbankpasswörter werden
     * genau so verwendet, wie sie eingegeben wurden.
     *
     * @param array<array-key, mixed> $input
     *
     * @return array{values: MysqlConfig, errors: array<string, string>}
     */
    public static function database(#[SensitiveParameter] array $input): array
    {
        $host = self::text($input, 'db_host');
        $port = self::port(self::text($input, 'db_port'), self::DEFAULT_DB_PORT);
        $name = self::text($input, 'db_name');
        $user = self::text($input, 'db_user');
        $password = is_string($input['db_password'] ?? null) ? $input['db_password'] : '';

        $errors = array_filter([
            'db_host' => self::fieldError(
                $host,
                self::isHostname($host),
                'Bitte geben Sie den Datenbank-Server an (oft „localhost“).',
                'Der Servername enthält ungültige Zeichen.'
            ),
            'db_port' => $port === null ? 'Der Port muss eine Zahl zwischen 1 und 65535 sein.' : null,
            'db_name' => self::fieldError(
                $name,
                preg_match('/^[A-Za-z0-9_$-]{1,' . self::DB_NAME_MAX . '}$/', $name) === 1,
                'Bitte geben Sie den Namen der Datenbank an.',
                'Der Datenbankname darf nur Buchstaben, Ziffern sowie _ - $ enthalten (höchstens 64 Zeichen).'
            ),
            'db_user' => self::fieldError(
                $user,
                self::isPlainText($user, self::DB_USER_MAX),
                'Bitte geben Sie den Datenbank-Benutzer an.',
                'Der Benutzername ist zu lang oder enthält ungültige Zeichen.'
            ),
        ]);

        return [
            'values' => [
                'server' => $host,
                'port' => $port ?? self::DEFAULT_DB_PORT,
                'user' => $user,
                'password' => $password,
                'database' => $name,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * Schritt 3: Forum-Einstellungen und optional SMTP.
     *
     * @param array<array-key, mixed> $input
     * @param MailConfig|null $previousMail bereits gespeicherte SMTP-Angaben – ein leeres
     *                                      Passwortfeld behält das Passwort desselben Benutzers
     *
     * @return array{values: ForumSettings, errors: array<string, string>}
     */
    public static function forum(#[SensitiveParameter] array $input, #[SensitiveParameter] ?array $previousMail = null): array
    {
        $title = self::text($input, 'board_title');
        $url = rtrim(self::text($input, 'board_url'), '/');
        $email = self::text($input, 'board_email');
        $language = self::text($input, 'board_language');

        $errors = array_filter([
            'board_title' => self::fieldError(
                $title,
                self::isPlainText($title, self::BOARDTITLE_MAX),
                'Bitte geben Sie einen Namen für das Forum an.',
                'Der Forumname darf höchstens 200 Zeichen lang sein und keine Steuer- oder ungültigen Zeichen enthalten.'
            ),
            'board_url' => self::fieldError(
                $url,
                self::isWebUrl($url) && Validator::withinLength($url, self::BOARDURL_MAX),
                'Bitte geben Sie die Adresse des Forums an.',
                'Bitte geben Sie eine vollständige Adresse mit http:// oder https:// an (höchstens 250 Zeichen).'
            ),
            'board_email' => self::isEmail($email) ? null : 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
            'board_language' => array_key_exists($language, self::LANGUAGES)
                ? null
                : 'Bitte wählen Sie eine Sprache aus der Liste.',
        ]);

        [$mail, $mailErrors] = self::mail($input, $previousMail);

        return [
            'values' => [
                'boardtitle' => $title,
                'boardurl' => $url,
                'adminemail' => $email,
                'language' => $language,
                'mail' => $mail,
            ],
            'errors' => $errors + $mailErrors,
        ];
    }

    /**
     * Schritt 4: Administrator-Konto – dieselben Regeln wie bei der Registrierung.
     *
     * Das Passwort wird wie in register.php/login.php getrimmt, damit die
     * spätere Anmeldung mit exakt derselben Eingabe funktioniert.
     *
     * @param array<array-key, mixed> $input
     *
     * @return array{values: AdminInput, errors: array<string, string>}
     */
    public static function admin(#[SensitiveParameter] array $input): array
    {
        $errors = [];

        $username = self::text($input, 'admin_username');
        if (!Validator::isValidUsername($username)) {
            $errors['admin_username'] = 'Der Benutzername muss 2 bis 50 Zeichen lang sein und darf nur Buchstaben, Ziffern sowie . _ - enthalten.';
        }

        $email = self::text($input, 'admin_email');
        if (!self::isEmail($email)) {
            $errors['admin_email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse an (höchstens 100 Zeichen).';
        }

        $password = self::text($input, 'admin_password');
        $confirm = self::text($input, 'admin_password_confirm');
        if (!Validator::isStrongPassword($password)) {
            $errors['admin_password'] = 'Das Passwort muss mindestens ' . Validator::PASSWORD_MIN . ' Zeichen lang sein.';
        } elseif ($password !== $confirm) {
            $errors['admin_password_confirm'] = 'Die beiden Passwörter stimmen nicht überein.';
        }

        return [
            'values' => [
                'username' => $username,
                'email' => $email,
                'password' => $password,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * Einfacher Hostname, IPv4 oder IPv6 in eckigen Klammern. Semikolon und
     * Gleichheitszeichen sind ausgeschlossen, damit nichts in den PDO-DSN
     * eingeschleust werden kann.
     */
    public static function isHostname(string $host): bool
    {
        return strlen($host) <= self::HOST_MAX
            && preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9_.-]*[A-Za-z0-9])?|\[[0-9A-Fa-f:.]+\])$/', $host) === 1;
    }

    public static function isWebUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * SMTP ist optional: Ohne Host bleiben die Vorgaben bzw. Umgebungsvariablen aktiv.
     * Ein leerer Port ergibt den üblichen Port der gewählten Verschlüsselung,
     * ein leerer Absender die E-Mail-Adresse des Forums (Mailer::senderAddress()).
     *
     * @param array<array-key, mixed> $input
     * @param MailConfig|null $previous
     *
     * @return array{0: MailConfig|null, 1: array<string, string>}
     */
    private static function mail(#[SensitiveParameter] array $input, #[SensitiveParameter] ?array $previous): array
    {
        $host = self::text($input, 'smtp_host');
        $user = self::text($input, 'smtp_user');
        $encryption = self::encryption($input);

        if ($host === '') {
            return [null, $user !== '' || $encryption !== Mailer::ENCRYPTION_NONE
                ? ['smtp_host' => 'Bitte geben Sie den SMTP-Server an – Benutzername und Verschlüsselung gelten nur zusammen mit ihm.']
                : []];
        }

        $errors = [];
        if (!self::isHostname($host)) {
            $errors['smtp_host'] = 'Der SMTP-Server enthält ungültige Zeichen.';
        }

        $from = self::text($input, 'smtp_from');
        if ($from !== '' && !self::isEmail($from)) {
            $errors['smtp_from'] = 'Bitte geben Sie eine gültige Absenderadresse an oder lassen Sie das Feld leer.';
        }

        [$encryption, $port, $transportErrors] = self::smtpTransport($input, $encryption);
        [$password, $credentialErrors] = self::smtpPassword($input, $user, $previous);

        return [
            [
                'host' => $host,
                'port' => $port,
                'from' => $from,
                'user' => $user,
                'password' => $password,
                'encryption' => $encryption,
            ],
            $errors + $transportErrors + $credentialErrors,
        ];
    }

    /**
     * Verschlüsselung und Port. Ein leerer Port ergibt den üblichen Port
     * der Verschlüsselung (25, 587 bzw. 465).
     *
     * @param array<array-key, mixed> $input
     *
     * @return array{0: string, 1: int, 2: array<string, string>} Verschlüsselung, Port und Fehler
     */
    private static function smtpTransport(array $input, ?string $encryption): array
    {
        $errors = [];
        if ($encryption === null) {
            $errors['smtp_encryption'] = 'Bitte wählen Sie eine Verschlüsselung aus der Liste.';
            $encryption = Mailer::ENCRYPTION_NONE;
        }

        $defaultPort = Mailer::DEFAULT_PORTS[$encryption];
        $port = self::port(self::text($input, 'smtp_port'), $defaultPort);
        if ($port === null) {
            $errors['smtp_port'] = 'Der SMTP-Port muss eine Zahl zwischen 1 und 65535 sein.';
        }

        return [$encryption, $port ?? $defaultPort, $errors];
    }

    /**
     * Gewählte Verschlüsselung; null bei einem Wert außerhalb der Liste.
     * Fehlt das Feld (Formular von vor 2.3.0), gilt wie bisher: keine.
     *
     * @param array<array-key, mixed> $input
     *
     * @return 'none'|'starttls'|'ssl'|null
     */
    private static function encryption(#[SensitiveParameter] array $input): ?string
    {
        $value = self::text($input, 'smtp_encryption');

        return match ($value) {
            '', Mailer::ENCRYPTION_NONE => Mailer::ENCRYPTION_NONE,
            Mailer::ENCRYPTION_STARTTLS, Mailer::ENCRYPTION_SSL => $value,
            default => null,
        };
    }

    /**
     * Zugangsdaten des Postfachs: mit Benutzer ist ein Passwort Pflicht. Das
     * Passwort wird nicht getrimmt; ohne Benutzer wird keines gespeichert.
     *
     * @param array<array-key, mixed> $input
     * @param MailConfig|null $previous
     *
     * @return array{0: string, 1: array<string, string>} Passwort und Fehler
     */
    private static function smtpPassword(#[SensitiveParameter] array $input, string $user, #[SensitiveParameter] ?array $previous): array
    {
        if ($user === '') {
            return ['', []];
        }

        $errors = [];
        if (!self::isPlainText($user, self::SMTP_USER_MAX)) {
            $errors['smtp_user'] = 'Der Benutzername ist zu lang oder enthält ungültige Zeichen.';
        }

        $password = is_string($input['smtp_password'] ?? null) ? $input['smtp_password'] : '';
        if ($password === '' && $previous !== null && $previous['user'] === $user) {
            $password = $previous['password'];
        }

        if ($password === '') {
            $errors['smtp_password'] = 'Bitte geben Sie das Passwort des E-Mail-Postfachs an.';
        } elseif (!Validator::withinLength($password, self::SMTP_PASSWORD_MAX)) {
            $errors['smtp_password'] = 'Das Passwort darf höchstens ' . self::SMTP_PASSWORD_MAX . ' Zeichen lang sein.';
        }

        return [$password, $errors];
    }

    /**
     * Leerer Wert ergibt den Standardport, ungültiger Wert null.
     */
    private static function port(string $value, int $default): ?int
    {
        if ($value === '') {
            return $default;
        }

        $port = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);

        return $port === false ? null : $port;
    }

    private static function isEmail(string $email): bool
    {
        return $email !== ''
            && Validator::withinLength($email, self::EMAIL_MAX)
            && Security::isValidEmail($email);
    }

    /**
     * Fehlermeldung für ein Pflichtfeld: eigene Meldung für leere Eingaben,
     * null bei gültigem Wert.
     */
    private static function fieldError(string $value, bool $valid, string $emptyMessage, string $invalidMessage): ?string
    {
        if ($value === '') {
            return $emptyMessage;
        }

        return $valid ? null : $invalidMessage;
    }

    /**
     * Nicht leer, gültiges UTF-8, höchstens $max Zeichen, keine Steuerzeichen.
     */
    private static function isPlainText(string $value, int $max): bool
    {
        return $value !== ''
            && mb_check_encoding($value, 'UTF-8')
            && Validator::withinLength($value, $max)
            && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }

    /**
     * @param array<array-key, mixed> $input
     */
    private static function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
