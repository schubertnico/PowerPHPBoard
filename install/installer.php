<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Web-Installer (Ablaufsteuerung)
 *
 * Wird von install/index.php eingebunden, nachdem die PHP-Version geprüft ist.
 * Schritte: 1 Systemprüfung, 2 Datenbank, 3 Forum, 4 Administrator, 5 Abschluss.
 *
 * Grundsätze:
 *  - Schreibende Aktionen nur per POST mit CSRF-Token, danach Redirect (PRG).
 *    GET-Parameter wählen nur den angezeigten Schritt.
 *  - Zugangsdaten erscheinen weder in Logs noch in Fehlermeldungen.
 *  - Nach Abschluss (oder bei bestehender Installation) ist der Installer
 *    gesperrt, siehe InstallState.
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\ErrorHandler;
use PowerPHPBoard\Installer\DatabaseSetup;
use PowerPHPBoard\Installer\FormValidator;
use PowerPHPBoard\Installer\InstallState;
use PowerPHPBoard\Installer\LocalConfig;
use PowerPHPBoard\Installer\Requirements;
use PowerPHPBoard\Installer\Schema;
use PowerPHPBoard\Installer\SmtpCheck;
use PowerPHPBoard\Installer\Wizard;
use PowerPHPBoard\Mailer;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

$rootDir = dirname(__DIR__);

require_once $rootDir . '/config.inc.php';
require_once $rootDir . '/includes/Installer/Schema.php';
require_once $rootDir . '/includes/Installer/FormValidator.php';
require_once $rootDir . '/includes/Installer/Requirements.php';
require_once $rootDir . '/includes/Installer/AdminAccount.php';
require_once $rootDir . '/includes/Installer/DatabaseSetup.php';
require_once $rootDir . '/includes/Installer/InstallState.php';
require_once $rootDir . '/includes/Installer/Wizard.php';
require_once $rootDir . '/includes/Installer/Html.php';
require_once $rootDir . '/includes/Installer/SmtpCheck.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

Session::start();

$wizard = Wizard::fromSession($_SESSION[Wizard::SESSION_KEY] ?? null);
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action = $isPost ? Security::getString('action', 'POST') : '';

// ---------------------------------------------------------------------
//  1. Diese Sitzung hat die Installation abgeschlossen: Abschlussseite
// ---------------------------------------------------------------------
$done = $wizard->done();
if ($done !== null) {
    $configPresent = is_file($rootDir . '/' . LocalConfig::FILENAME);
    if ($configPresent && $done['config_source'] !== '') {
        $wizard->forgetConfigSource();
        $done['config_source'] = '';
    }

    if ($action === 'download_config' && $done['config_source'] !== '' && CSRF::validateFromPost()) {
        installer_save($wizard);
        installer_download($done['config_source']);
    }

    installer_page($wizard, 'Installation abgeschlossen', 0, 'done', [
        'done' => $done,
        'configPresent' => $configPresent,
    ]);
}

// ---------------------------------------------------------------------
//  2. Sperre: bestehende Installation niemals überschreiben
// ---------------------------------------------------------------------
$lockReason = InstallState::detectLockReason($rootDir, $mysql, installer_probe($mysql));
if ($lockReason !== null) {
    http_response_code(403);
    installer_page($wizard, 'Installer gesperrt', 0, 'locked', ['reason' => $lockReason]);
}

// ---------------------------------------------------------------------
//  3. Assistent
// ---------------------------------------------------------------------
$requestedStep = Security::getInt('step', 'GET', $wizard->completed() + 1);
$step = $wizard->allowedStep($requestedStep);
if (!$isPost && $requestedStep !== $step) {
    installer_redirect($wizard, $step);
}

$result = installer_failure('');

if ($isPost) {
    $actionStep = Wizard::ACTIONS[$action] ?? null;
    if ($actionStep === null) {
        $result = installer_failure('Unbekannte Aktion.');
    } elseif (!$wizard->canEnter($actionStep)) {
        installer_redirect($wizard, $wizard->allowedStep($actionStep));
    } elseif (!CSRF::validateFromPost()) {
        $result = installer_failure('Ihre Sitzung ist abgelaufen oder Cookies sind blockiert. Bitte senden Sie das Formular erneut ab.');
        $step = $actionStep;
    } else {
        $step = $actionStep;
        $result = match ($action) {
            'requirements' => installer_handle_requirements($wizard, $rootDir),
            'database' => installer_handle_database($wizard),
            'forum' => installer_handle_forum($wizard),
            Wizard::ACTION_SMTP_TEST => installer_handle_smtp_test($wizard),
            'admin' => installer_handle_admin($wizard),
            default => installer_handle_finish($wizard, $rootDir),
        };
    }
}

$title = Wizard::STEPS[$step] ?? 'Installation';

// installer_page() beendet das Skript – es wird genau eine Seite ausgegeben.
if ($step === Wizard::STEP_DATABASE) {
    installer_page($wizard, $title, $step, 'database', [
        'old' => installer_old_database($wizard, $isPost),
        'errors' => $result['errors'],
        'message' => $result['message'],
        'tables' => $result['tables'],
    ]);
}

if ($step === Wizard::STEP_FORUM) {
    installer_page($wizard, $title, $step, 'forum', [
        'old' => installer_old_forum($wizard, $isPost),
        'errors' => $result['errors'],
        'message' => $result['message'],
        'passwordStored' => ($wizard->forum()['mail']['password'] ?? '') !== '',
        'notice' => $isPost ? null : $wizard->takeNotice(),
    ]);
}

if ($step === Wizard::STEP_ADMIN) {
    installer_page($wizard, $title, $step, 'admin', [
        'old' => installer_old_admin($wizard, $isPost),
        'errors' => $result['errors'],
        'message' => $result['message'],
    ]);
}

if ($step === Wizard::STEP_FINISH) {
    installer_page($wizard, $title, $step, 'finish', [
        'wizard' => $wizard,
        'configWritable' => Requirements::canWriteLocalConfig($rootDir),
        'message' => $result['message'],
    ]);
}

$checks = Requirements::check($rootDir, $_SERVER);
installer_page($wizard, $title, Wizard::STEP_REQUIREMENTS, 'requirements', [
    'checks' => $checks,
    'allOk' => Requirements::allRequiredMet($checks),
    'message' => $result['message'],
]);

// =====================================================================
//  Schritt-Handler: bei Erfolg Redirect, sonst Fehler für die Anzeige
// =====================================================================

/**
 * @return array{errors: array<string, string>, message: string, tables: list<string>}
 */
function installer_handle_requirements(Wizard $wizard, string $rootDir): array
{
    if (!Requirements::allRequiredMet(Requirements::check($rootDir, $_SERVER))) {
        return installer_failure('Bitte beheben Sie zuerst die rot markierten Punkte und laden Sie die Seite dann neu.');
    }

    $wizard->completeRequirements();
    installer_redirect($wizard, Wizard::STEP_DATABASE);
}

/**
 * @return array{errors: array<string, string>, message: string, tables: list<string>}
 */
function installer_handle_database(Wizard $wizard): array
{
    $result = FormValidator::database($_POST);
    if ($result['errors'] !== []) {
        return installer_failure('Bitte prüfen Sie die markierten Felder.', $result['errors']);
    }

    try {
        $tables = DatabaseSetup::existingTables(DatabaseSetup::connect($result['values']));
    } catch (PDOException $e) {
        ErrorHandler::logSecurityEvent('INSTALLER_DB_CONNECT_FAILED', ['code' => DatabaseSetup::driverCode($e)]);
        return installer_failure(DatabaseSetup::friendlyError($e));
    }

    if ($tables !== []) {
        return [
            'errors' => [],
            'message' => 'Diese Datenbank enthält bereits PowerPHPBoard-Tabellen. Der Installer überschreibt keine bestehenden Daten.',
            'tables' => $tables,
        ];
    }

    $wizard->storeDatabase($result['values']);
    installer_redirect($wizard, Wizard::STEP_FORUM);
}

/**
 * @return array{errors: array<string, string>, message: string, tables: list<string>}
 */
function installer_handle_forum(Wizard $wizard): array
{
    $result = FormValidator::forum($_POST, $wizard->forum()['mail'] ?? null);
    if ($result['errors'] !== []) {
        return installer_failure('Bitte prüfen Sie die markierten Felder.', $result['errors']);
    }

    $wizard->storeForum($result['values']);
    installer_redirect($wizard, Wizard::STEP_ADMIN);
}

/**
 * „Test-Mail senden“: speichert die geprüften Angaben aus Schritt 3 und
 * schickt eine Test-Mail an die E-Mail-Adresse des Forums. Das Ergebnis
 * erscheint nach der Weiterleitung wieder in Schritt 3.
 *
 * @return array{errors: array<string, string>, message: string, tables: list<string>}
 */
function installer_handle_smtp_test(Wizard $wizard): array
{
    $result = FormValidator::forum($_POST, $wizard->forum()['mail'] ?? null);
    if ($result['errors'] !== []) {
        return installer_failure('Bitte prüfen Sie die markierten Felder.', $result['errors']);
    }
    if (!$wizard->countSmtpTest()) {
        return installer_failure(
            'In dieser Sitzung wurden bereits ' . Wizard::MAX_SMTP_TESTS . ' Test-Mails verschickt. '
            . 'Bitte prüfen Sie die Angaben ohne weiteren Test oder fahren Sie fort.'
        );
    }

    $forum = $result['values'];
    $wizard->storeForum($forum);

    // Ohne eigenen SMTP-Server gelten später die Umgebungsvariablen bzw. Vorgaben – genau die werden getestet
    $mail = $forum['mail'] ?? LocalConfig::mailFromEnvironment(static fn (string $name): string|false => getenv($name));
    $outcome = SmtpCheck::send(
        Mailer::fromConfig($mail),
        $mail,
        $forum['adminemail'],
        Mailer::senderAddress(['adminemail' => $forum['adminemail']], $mail)
    );
    ErrorHandler::logSecurityEvent('INSTALLER_SMTP_TEST', ['accepted' => $outcome['ok']]);

    $wizard->setNotice($outcome['ok'] ? 'success' : 'danger', $outcome['message']);
    installer_redirect($wizard, Wizard::STEP_FORUM);
}

/**
 * @return array{errors: array<string, string>, message: string, tables: list<string>}
 */
function installer_handle_admin(Wizard $wizard): array
{
    $result = FormValidator::admin($_POST);
    if ($result['errors'] !== []) {
        return installer_failure('Bitte prüfen Sie die markierten Felder.', $result['errors']);
    }

    $values = $result['values'];
    $wizard->storeAdmin($values['username'], $values['email'], Security::hashPassword($values['password']));
    installer_redirect($wizard, Wizard::STEP_FINISH);
}

/**
 * Spielt Schema, Einstellungen und Administrator ein, schreibt
 * config.local.php (falls möglich) und setzt die Sperre.
 *
 * @return array{errors: array<string, string>, message: string, tables: list<string>}
 */
function installer_handle_finish(Wizard $wizard, string $rootDir): array
{
    $database = $wizard->database();
    $forum = $wizard->forum();
    $admin = $wizard->admin();
    if ($database === null || $forum === null || $admin === null) {
        installer_redirect($wizard, $wizard->allowedStep(Wizard::STEP_FINISH));
    }

    try {
        $statements = Schema::fromFile($rootDir . '/' . Schema::FILENAME);
        $pdo = DatabaseSetup::connect($database);
        if (DatabaseSetup::existingTables($pdo) !== []) {
            return installer_failure('Die Datenbank enthält inzwischen PowerPHPBoard-Tabellen. Es wurde nichts verändert.');
        }
        $adminId = DatabaseSetup::install($pdo, $statements, $forum, $admin, time());
    } catch (PDOException $e) {
        ErrorHandler::logSecurityEvent('INSTALLER_FAILED', [
            'code' => DatabaseSetup::driverCode($e),
            'sqlstate' => DatabaseSetup::sqlState($e),
        ]);
        return installer_failure(
            'Die Installation ist fehlgeschlagen. ' . DatabaseSetup::friendlyError($e)
            . ' Bereits angelegte Tabellen wurden wieder entfernt.'
        );
    } catch (RuntimeException $e) {
        return installer_failure($e->getMessage());
    }

    $now = date('Y-m-d H:i:s');
    $source = LocalConfig::render($database, $forum['mail'], $now);
    $written = LocalConfig::writeFile($rootDir . '/' . LocalConfig::FILENAME, $source);
    InstallState::writeLockFile($rootDir, $now);

    $wizard->finish($written, $source);
    ErrorHandler::logSecurityEvent('INSTALLER_COMPLETED', ['admin_user_id' => $adminId, 'config_written' => $written]);

    CSRF::regenerate();
    Session::regenerate();
    installer_redirect($wizard, null);
}

// =====================================================================
//  Hilfsfunktionen
// =====================================================================

/**
 * @param array<string, string> $errors
 *
 * @return array{errors: array<string, string>, message: string, tables: list<string>}
 */
function installer_failure(string $message, array $errors = []): array
{
    return ['errors' => $errors, 'message' => $message, 'tables' => []];
}

/**
 * Prüft die aktuell wirksame Datenbank-Konfiguration (Umgebungsvariablen
 * bzw. config.inc.php) für die Sperrlogik.
 *
 * @return Closure(): ?bool
 */
function installer_probe(mixed $mysql): Closure
{
    return static function () use ($mysql): ?bool {
        if (!is_array($mysql)) {
            return null;
        }

        $config = LocalConfig::apply(LocalConfig::DEFAULT_MYSQL, LocalConfig::DEFAULT_MAIL, ['mysql' => $mysql]);
        try {
            return DatabaseSetup::hasConfigRow(DatabaseSetup::connect($config['mysql']));
        } catch (PDOException) {
            return null;
        }
    };
}

/**
 * Formularwerte für Schritt 2 (ohne Passwort – das wird nie ausgegeben).
 *
 * @return array<string, string>
 */
function installer_old_database(Wizard $wizard, bool $isPost): array
{
    if ($isPost) {
        return installer_post_values(['db_host', 'db_port', 'db_name', 'db_user']);
    }

    $database = $wizard->database();

    return [
        'db_host' => $database['server'] ?? 'localhost',
        'db_port' => (string) ($database['port'] ?? FormValidator::DEFAULT_DB_PORT),
        'db_name' => $database['database'] ?? '',
        'db_user' => $database['user'] ?? '',
    ];
}

/**
 * @return array<string, string>
 */
function installer_old_forum(Wizard $wizard, bool $isPost): array
{
    if ($isPost) {
        // Das SMTP-Passwort wird nie wieder ausgegeben
        return installer_post_values([
            'board_title', 'board_url', 'board_email', 'board_language',
            'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_from',
        ]);
    }

    $forum = $wizard->forum();
    if ($forum !== null) {
        return [
            'board_title' => $forum['boardtitle'],
            'board_url' => $forum['boardurl'],
            'board_email' => $forum['adminemail'],
            'board_language' => $forum['language'],
            'smtp_host' => $forum['mail']['host'] ?? '',
            'smtp_port' => isset($forum['mail']) ? (string) $forum['mail']['port'] : '25',
            'smtp_encryption' => $forum['mail']['encryption'] ?? Mailer::ENCRYPTION_NONE,
            'smtp_user' => $forum['mail']['user'] ?? '',
            'smtp_from' => $forum['mail']['from'] ?? '',
        ];
    }

    $boardUrl = Wizard::suggestBoardUrl($_SERVER);

    return [
        'board_title' => 'PowerPHPBoard',
        'board_url' => $boardUrl,
        'board_email' => '',
        'board_language' => 'Deutsch-Du',
        'smtp_host' => 'localhost',
        'smtp_port' => '25',
        'smtp_encryption' => Mailer::ENCRYPTION_NONE,
        'smtp_user' => '',
        'smtp_from' => Wizard::suggestSender($boardUrl),
    ];
}

/**
 * Formularwerte für Schritt 4 (ohne Passwörter).
 *
 * @return array<string, string>
 */
function installer_old_admin(Wizard $wizard, bool $isPost): array
{
    if ($isPost) {
        return installer_post_values(['admin_username', 'admin_email']);
    }

    $admin = $wizard->admin();

    return [
        'admin_username' => $admin['username'] ?? '',
        'admin_email' => $admin['email'] ?? ($wizard->forum()['adminemail'] ?? ''),
    ];
}

/**
 * @param list<string> $keys
 *
 * @return array<string, string>
 */
function installer_post_values(array $keys): array
{
    $values = [];
    foreach ($keys as $key) {
        $values[$key] = Security::getString($key, 'POST');
    }

    return $values;
}

function installer_save(Wizard $wizard): void
{
    $_SESSION[Wizard::SESSION_KEY] = $wizard->toSession();
}

/**
 * Speichert den Zustand und leitet per 303 weiter (Post/Redirect/Get).
 */
function installer_redirect(Wizard $wizard, ?int $step): never
{
    installer_save($wizard);
    header('Location: index.php' . ($step !== null ? '?step=' . $step : ''), true, 303);
    exit;
}

/**
 * Liefert config.local.php als Download (nur im Abschlusszustand dieser
 * Sitzung, wenn die Datei nicht geschrieben werden konnte).
 */
function installer_download(#[SensitiveParameter] string $source): never
{
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . LocalConfig::FILENAME . '"');
    header('Content-Length: ' . strlen($source));
    echo $source;
    exit;
}

/**
 * Rendert eine Seite aus Layout und Schritt-Template und beendet das Skript.
 *
 * @param array<string, mixed> $args benannte Argumente für das Template
 */
function installer_page(Wizard $wizard, string $title, int $step, string $template, array $args): never
{
    installer_save($wizard);

    $layout = require __DIR__ . '/templates/layout.php';
    $body = require __DIR__ . '/templates/' . $template . '.php';
    if (!is_callable($layout) || !is_callable($body)) {
        http_response_code(500);
        echo 'Installer-Vorlage fehlt oder ist beschädigt. Bitte laden Sie das Verzeichnis install/ erneut hoch.';
        exit;
    }

    $layout($title, $step, $wizard->completed(), static function () use ($body, $args): void {
        $body(...$args);
    });
    exit;
}
