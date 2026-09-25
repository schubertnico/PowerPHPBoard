<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Administrator anlegen (CLI-Notfallwerkzeug)
 *
 * Für den Fall, dass kein Administrator mehr Zugang hat (z. B. Passwort
 * vergessen und kein funktionierender Mailversand). Die Erstinstallation
 * übernimmt der Web-Installer unter install/.
 *
 * Aufruf (im Forumverzeichnis):
 *   php bin/create-admin.php --user=Name --email=adresse@example.com
 *   php bin/create-admin.php --email=adresse@example.com --update
 *   printf '%s\n' "$PASS" | php bin/create-admin.php --user=Name --email=... --password-stdin
 *
 *   --update          bestehendes Konto (per E-Mail) zum Administrator machen
 *                     und ein neues Passwort setzen
 *   --password-stdin  Passwort aus der Standardeingabe lesen (für Skripte)
 *
 * Das Passwort wird nie als Argument übergeben, damit es nicht in der
 * Shell-History oder der Prozessliste landet. Die Datenbank-Zugangsdaten
 * stammen aus config.local.php bzw. den Umgebungsvariablen PPB_DB_*.
 *
 * Exit-Codes: 0 = Erfolg, 1 = Fehler, 2 = falscher Aufruf.
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\ErrorHandler;
use PowerPHPBoard\Installer\AdminAccount;
use PowerPHPBoard\Installer\DatabaseSetup;
use PowerPHPBoard\Installer\FormValidator;
use PowerPHPBoard\Installer\LocalConfig;
use PowerPHPBoard\Security;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Dieses Werkzeug ist nur über die Kommandozeile nutzbar.');
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config.inc.php';
require_once $rootDir . '/includes/Installer/FormValidator.php';
require_once $rootDir . '/includes/Installer/AdminAccount.php';
require_once $rootDir . '/includes/Installer/Schema.php';
require_once $rootDir . '/includes/Installer/DatabaseSetup.php';

$options = getopt('', ['user:', 'email:', 'update', 'password-stdin', 'help']);
$options = is_array($options) ? $options : [];

if (isset($options['help'])) {
    cli_usage();
    exit(0);
}

$update = isset($options['update']);
$email = is_string($options['email'] ?? null) ? trim($options['email']) : '';
$username = is_string($options['user'] ?? null) ? trim($options['user']) : '';

if ($email === '' || (!$update && $username === '')) {
    cli_usage();
    exit(2);
}

$fromStdin = isset($options['password-stdin']);
$password = cli_read_password('Neues Passwort: ', $fromStdin);
$confirm = $fromStdin ? $password : cli_read_password('Passwort wiederholen: ', false);

$check = FormValidator::admin([
    'admin_username' => $update ? 'platzhalter' : $username,
    'admin_email' => $email,
    'admin_password' => $password,
    'admin_password_confirm' => $confirm,
]);
if ($check['errors'] !== []) {
    cli_fail(implode(PHP_EOL, $check['errors']));
}

try {
    $config = LocalConfig::apply(LocalConfig::DEFAULT_MYSQL, LocalConfig::DEFAULT_MAIL, ['mysql' => $mysql]);
    $pdo = DatabaseSetup::connect($config['mysql']);
    $hash = Security::hashPassword($check['values']['password']);

    $userId = $update
        ? cli_promote($pdo, $check['values']['email'], $hash)
        : cli_create($pdo, $check['values']['username'], $check['values']['email'], $hash);
} catch (PDOException $e) {
    cli_fail(DatabaseSetup::friendlyError($e));
}

ErrorHandler::logSecurityEvent($update ? 'CLI_ADMIN_PROMOTED' : 'CLI_ADMIN_CREATED', ['user_id' => $userId]);
fwrite(STDOUT, PHP_EOL . 'Fertig. Anmeldung mit ' . $check['values']['email'] . ' und dem neuen Passwort.' . PHP_EOL);
exit(0);

// =====================================================================

function cli_create(PDO $pdo, string $username, string $email, #[SensitiveParameter] string $hash): int
{
    if (AdminAccount::findByEmail($pdo, $email) !== null) {
        cli_fail('Die E-Mail-Adresse ist bereits registriert. Mit --update wird dieses Konto zum Administrator.');
    }
    if (AdminAccount::usernameTaken($pdo, $username)) {
        cli_fail('Der Benutzername ist bereits vergeben.');
    }

    $userId = AdminAccount::create($pdo, $username, $email, $hash, time());
    fwrite(STDOUT, sprintf('Administrator „%s“ angelegt (ID %d).', $username, $userId) . PHP_EOL);

    return $userId;
}

function cli_promote(PDO $pdo, string $email, #[SensitiveParameter] string $hash): int
{
    $user = AdminAccount::findByEmail($pdo, $email);
    if ($user === null) {
        cli_fail('Es gibt kein Konto mit dieser E-Mail-Adresse.');
    }

    AdminAccount::promote($pdo, $user['id'], $hash);
    fwrite(STDOUT, sprintf('Konto „%s“ (ID %d) ist jetzt Administrator, das Passwort wurde neu gesetzt.', $user['username'], $user['id']) . PHP_EOL);

    return $user['id'];
}

/**
 * Liest das Passwort; im Terminal unter Linux/macOS verdeckt.
 */
function cli_read_password(string $prompt, bool $fromStdin): string
{
    if ($fromStdin) {
        $line = fgets(STDIN);
        return $line === false ? '' : rtrim($line, "\r\n");
    }

    fwrite(STDOUT, $prompt);
    $hide = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($hide) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hide) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }

    return $line === false ? '' : rtrim($line, "\r\n");
}

function cli_fail(string $message): never
{
    fwrite(STDERR, 'Fehler: ' . $message . PHP_EOL);
    exit(1);
}

function cli_usage(): void
{
    fwrite(STDOUT, <<<'TXT'
        PowerPHPBoard – Administrator anlegen (Notfallwerkzeug)

        Neuen Administrator anlegen:
          php bin/create-admin.php --user=Name --email=adresse@example.com

        Bestehendes Konto zum Administrator machen und Passwort neu setzen:
          php bin/create-admin.php --email=adresse@example.com --update

        Optionen:
          --password-stdin  Passwort aus der Standardeingabe lesen (für Skripte)
          --help            Diese Hilfe

        Das Passwort wird abgefragt und nie als Argument übergeben.

        TXT);
}
