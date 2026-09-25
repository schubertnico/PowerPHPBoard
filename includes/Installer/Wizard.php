<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Ablaufzustand des Web-Installers
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard\Installer;

use SensitiveParameter;

/**
 * Hält den Fortschritt des Installers in der Session.
 *
 * Gespeichert werden nur die Eingaben, die für den letzten Schritt nötig
 * sind. Das Administrator-Passwort liegt ausschließlich als Argon2id-Hash
 * vor; Datenbank- und SMTP-Passwort werden nach Abschluss aus der Session
 * entfernt.
 *
 * @phpstan-import-type MysqlConfig from LocalConfig
 * @phpstan-import-type MailConfig from LocalConfig
 * @phpstan-import-type ForumSettings from FormValidator
 *
 * @phpstan-type AdminData array{username: string, email: string, password_hash: string}
 * @phpstan-type DoneInfo array{config_written: bool, config_source: string, admin_username: string, admin_email: string, board_url: string}
 */
final class Wizard
{
    public const string SESSION_KEY = 'ppb_installer';

    public const int STEP_REQUIREMENTS = 1;

    public const int STEP_DATABASE = 2;

    public const int STEP_FORUM = 3;

    public const int STEP_ADMIN = 4;

    public const int STEP_FINISH = 5;

    public const array STEPS = [
        self::STEP_REQUIREMENTS => 'Systemprüfung',
        self::STEP_DATABASE => 'Datenbank',
        self::STEP_FORUM => 'Forum',
        self::STEP_ADMIN => 'Administrator',
        self::STEP_FINISH => 'Abschluss',
    ];

    /**
     * POST-Aktionen (Formularfeld "action") und der Schritt, zu dem sie gehören.
     */
    public const array ACTIONS = [
        'requirements' => self::STEP_REQUIREMENTS,
        'database' => self::STEP_DATABASE,
        'forum' => self::STEP_FORUM,
        'admin' => self::STEP_ADMIN,
        'finish' => self::STEP_FINISH,
    ];

    private int $completed = 0;

    /** @var MysqlConfig|null */
    private ?array $database = null;

    /** @var ForumSettings|null */
    private ?array $forum = null;

    /** @var AdminData|null */
    private ?array $admin = null;

    /** @var DoneInfo|null */
    private ?array $done = null;

    /**
     * Stellt den Zustand aus der Session wieder her. Unvollständige oder
     * manipulierte Daten führen zu einem früheren Schritt, nie zu einem Fehler.
     */
    public static function fromSession(#[SensitiveParameter] mixed $data): self
    {
        $wizard = new self();
        if (!is_array($data)) {
            return $wizard;
        }

        $completed = $data['completed'] ?? 0;
        $wizard->completed = is_int($completed) ? max(0, min($completed, self::STEP_FINISH)) : 0;
        $wizard->database = self::parseDatabase($data['database'] ?? null);
        $wizard->forum = self::parseForum($data['forum'] ?? null);
        $wizard->admin = self::parseAdmin($data['admin'] ?? null);
        $wizard->done = self::parseDone($data['done'] ?? null);

        return $wizard;
    }

    /**
     * @return array{completed: int, database: MysqlConfig|null, forum: ForumSettings|null, admin: AdminData|null, done: DoneInfo|null}
     */
    public function toSession(): array
    {
        return [
            'completed' => $this->completed,
            'database' => $this->database,
            'forum' => $this->forum,
            'admin' => $this->admin,
            'done' => $this->done,
        ];
    }

    /**
     * Höchster Schritt, dessen Daten vollständig vorliegen.
     */
    public function completed(): int
    {
        $completed = $this->completed;
        if ($this->database === null) {
            $completed = min($completed, self::STEP_REQUIREMENTS);
        }
        if ($this->forum === null) {
            $completed = min($completed, self::STEP_DATABASE);
        }
        if ($this->admin === null) {
            $completed = min($completed, self::STEP_FORUM);
        }

        return $completed;
    }

    /**
     * Begrenzt einen angefragten Schritt auf die bereits erreichbaren.
     */
    public function allowedStep(int $requested): int
    {
        $maxStep = min($this->completed() + 1, self::STEP_FINISH);

        return max(self::STEP_REQUIREMENTS, min($requested, $maxStep));
    }

    public function canEnter(int $step): bool
    {
        return $this->allowedStep($step) === $step;
    }

    public function completeRequirements(): void
    {
        $this->completed = max($this->completed, self::STEP_REQUIREMENTS);
    }

    /**
     * @param MysqlConfig $database
     */
    public function storeDatabase(#[SensitiveParameter] array $database): void
    {
        $this->database = $database;
        $this->completed = max($this->completed, self::STEP_DATABASE);
    }

    /**
     * @param ForumSettings $forum
     */
    public function storeForum(array $forum): void
    {
        $this->forum = $forum;
        $this->completed = max($this->completed, self::STEP_FORUM);
    }

    public function storeAdmin(string $username, string $email, #[SensitiveParameter] string $passwordHash): void
    {
        $this->admin = ['username' => $username, 'email' => $email, 'password_hash' => $passwordHash];
        $this->completed = max($this->completed, self::STEP_ADMIN);
    }

    /**
     * Schließt die Installation ab und verwirft alle Zugangsdaten. Nur wenn
     * config.local.php von Hand angelegt werden muss, bleibt ihr Inhalt bis
     * dahin in der Session.
     */
    public function finish(bool $configWritten, #[SensitiveParameter] string $configSource): void
    {
        $this->done = [
            'config_written' => $configWritten,
            'config_source' => $configWritten ? '' : $configSource,
            'admin_username' => $this->admin['username'] ?? '',
            'admin_email' => $this->admin['email'] ?? '',
            'board_url' => $this->forum['boardurl'] ?? '',
        ];
        $this->completed = self::STEP_FINISH;
        $this->database = null;
        $this->forum = null;
        $this->admin = null;
    }

    /**
     * Die von Hand anzulegende config.local.php liegt inzwischen vor –
     * ihr Inhalt wird in der Session nicht mehr gebraucht.
     */
    public function forgetConfigSource(): void
    {
        if ($this->done !== null) {
            $this->done['config_source'] = '';
        }
    }

    /**
     * @return MysqlConfig|null
     */
    public function database(): ?array
    {
        return $this->database;
    }

    /**
     * @return ForumSettings|null
     */
    public function forum(): ?array
    {
        return $this->forum;
    }

    /**
     * @return AdminData|null
     */
    public function admin(): ?array
    {
        return $this->admin;
    }

    /**
     * @return DoneInfo|null
     */
    public function done(): ?array
    {
        return $this->done;
    }

    /**
     * Vorschlag für die Board-URL aus der aktuellen Anfrage, z. B.
     * https://example.com/forum für /forum/install/index.php.
     *
     * @param array<array-key, mixed> $server $_SERVER
     */
    public static function suggestBoardUrl(array $server): string
    {
        $host = is_string($server['HTTP_HOST'] ?? null) ? $server['HTTP_HOST'] : '';
        if (preg_match('/^[A-Za-z0-9.-]+(?::\d{1,5})?$|^\[[0-9A-Fa-f:.]+\](?::\d{1,5})?$/', $host) !== 1) {
            return '';
        }

        $scheme = Requirements::isHttps($server) ? 'https' : 'http';
        $script = is_string($server['SCRIPT_NAME'] ?? null) ? $server['SCRIPT_NAME'] : '/install/index.php';
        $path = rtrim(str_replace('\\', '/', dirname($script, 2)), '/');

        if (preg_match('#^[A-Za-z0-9._~/%-]*$#', $path) !== 1) {
            $path = '';
        }

        return $scheme . '://' . $host . $path;
    }

    /**
     * Vorschlag für die Absenderadresse: noreply@<Domain der Board-URL>.
     */
    public static function suggestSender(string $boardUrl): string
    {
        $host = parse_url($boardUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $candidate = 'noreply@' . (str_starts_with(strtolower($host), 'www.') ? substr($host, 4) : $host);

        return filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false ? $candidate : '';
    }

    /**
     * @return MysqlConfig|null
     */
    private static function parseDatabase(mixed $data): ?array
    {
        if (!is_array($data) || !is_int($data['port'] ?? null)) {
            return null;
        }

        $strings = self::strings($data, ['server', 'user', 'password', 'database']);
        if ($strings === null) {
            return null;
        }

        return [
            'server' => $strings['server'],
            'port' => $data['port'],
            'user' => $strings['user'],
            'password' => $strings['password'],
            'database' => $strings['database'],
        ];
    }

    /**
     * @return ForumSettings|null
     */
    private static function parseForum(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $strings = self::strings($data, ['boardtitle', 'boardurl', 'adminemail', 'language']);
        if ($strings === null) {
            return null;
        }

        return [
            'boardtitle' => $strings['boardtitle'],
            'boardurl' => $strings['boardurl'],
            'adminemail' => $strings['adminemail'],
            'language' => $strings['language'],
            'mail' => self::parseMail($data['mail'] ?? null),
        ];
    }

    /**
     * @return MailConfig|null
     */
    private static function parseMail(mixed $data): ?array
    {
        if (!is_array($data) || !is_int($data['port'] ?? null)) {
            return null;
        }

        // Sitzungen von vor der SMTP-Anmeldung kennen user, password und encryption noch nicht
        $data += ['user' => '', 'password' => '', 'encryption' => 'none'];
        $strings = self::strings($data, ['host', 'from', 'user', 'password', 'encryption']);
        if ($strings === null) {
            return null;
        }

        return [
            'host' => $strings['host'],
            'port' => $data['port'],
            'from' => $strings['from'],
            'user' => $strings['user'],
            'password' => $strings['password'],
            'encryption' => $strings['encryption'],
        ];
    }

    /**
     * @return AdminData|null
     */
    private static function parseAdmin(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $strings = self::strings($data, ['username', 'email', 'password_hash']);
        if ($strings === null) {
            return null;
        }

        return [
            'username' => $strings['username'],
            'email' => $strings['email'],
            'password_hash' => $strings['password_hash'],
        ];
    }

    /**
     * @return DoneInfo|null
     */
    private static function parseDone(mixed $data): ?array
    {
        if (!is_array($data) || !is_bool($data['config_written'] ?? null)) {
            return null;
        }

        $strings = self::strings($data, ['config_source', 'admin_username', 'admin_email', 'board_url']);
        if ($strings === null) {
            return null;
        }

        return [
            'config_written' => $data['config_written'],
            'config_source' => $strings['config_source'],
            'admin_username' => $strings['admin_username'],
            'admin_email' => $strings['admin_email'],
            'board_url' => $strings['board_url'],
        ];
    }

    /**
     * Liefert die angegebenen Schlüssel, wenn alle Zeichenketten sind.
     *
     * @param array<array-key, mixed> $data
     * @param list<string> $keys
     *
     * @return array<string, string>|null
     */
    private static function strings(array $data, array $keys): ?array
    {
        $result = [];
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (!is_string($value)) {
                return null;
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
