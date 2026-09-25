<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Core Functions
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\BoardAccess;
use PowerPHPBoard\BoardUrl;
use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\DatabaseRateLimitStorage;
use PowerPHPBoard\RateLimiter;
use PowerPHPBoard\Security;
use PowerPHPBoard\TextFormatter;

/**
 * Sprachtext aus der geladenen Sprachdatei ($lang_…) mit Ersatztext.
 *
 * @param string $key Schlüssel ohne "lang_"-Präfix
 * @param string $fallback Text, falls der Schlüssel fehlt
 */
function ppb_lang(string $key, string $fallback): string
{
    $value = $GLOBALS['lang_' . $key] ?? null;

    return is_string($value) && $value !== '' ? $value : $fallback;
}

/**
 * Text der Begrüßungsmail nach der Registrierung oder nach „Benutzer
 * anlegen“ im Adminbereich. Das Passwort steht bewusst nie in der Mail.
 *
 * @param array<string, mixed> $settings Zeile aus ppb_config
 */
function ppb_welcome_mail_text(array $settings, string $username, string $email, bool $createdByAdmin): string
{
    $boardTitle = (string) ($settings['boardtitle'] ?? 'PowerPHPBoard');
    $baseUrl = BoardUrl::base($settings);
    $intro = $createdByAdmin
        ? ppb_lang('accountcreatedbyadmin', 'an account has been created for you at')
        : ppb_lang('youregisteredsuccessfull', 'you have successfully registered at');

    $lines = [
        ppb_lang('hello', 'Hello') . ' ' . $username . ',',
        '',
        $intro . ' ' . $boardTitle . ($baseUrl !== null ? ' (' . $baseUrl . ')' : '') . '.',
        '',
        ppb_lang('hereisyourlogininformation', 'Here is your login information:'),
        '',
        '    ' . ppb_lang('username', 'Username') . ': ' . $username,
        '    ' . ppb_lang('email', 'Email') . ': ' . $email,
        '',
    ];
    if ($createdByAdmin) {
        $lines[] = ppb_lang('passwordfromadmin', 'You will receive your password from the board administrator. You can set your own password at any time via "Forgot password?".');
        $lines[] = '';
    }
    if ($baseUrl !== null) {
        $lines[] = ppb_lang('youcanloginhere', 'You can log in here:') . ' ' . BoardUrl::link($baseUrl, 'login.php');
        $lines[] = '';
    }
    $lines[] = ppb_lang('donotanswertoautomail', 'Please do not reply to this automatically generated email.');

    return implode("\n", $lines);
}

/**
 * Zugang zu einem Board prüfen. Ein per Formular abgeschicktes
 * Board-Passwort wird dabei geprüft (Sperre nach 10 Fehlversuchen in
 * 15 Minuten je IP-Adresse).
 *
 * @param array<string, mixed> $board Board-Zeile (mindestens id, status, password)
 * @param array<string, mixed> $ppbuser Angemeldeter Benutzer oder []
 *
 * @return string BoardAccess::GRANTED, REQUIRED, WRONG_PASSWORD oder LOCKED
 */
function ppb_board_access(array $board, array $ppbuser, Database $db): string
{
    if ($board === []) {
        return BoardAccess::GRANTED;
    }

    $submitted = null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['boardpassword'])) {
        $submitted = Security::getString('boardpassword', 'POST');
    }
    $limiter = new RateLimiter(
        new DatabaseRateLimitStorage($db),
        maxAttempts: 10,
        windowSeconds: 900,
        lockSeconds: 900
    );

    return BoardAccess::check(
        $board,
        $ppbuser !== [] ? $ppbuser : null,
        $db,
        $submitted,
        $limiter,
        (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
    );
}

/**
 * Passwortabfrage für ein privates Board als Bootstrap-Karte.
 *
 * @param string $action Formularziel (in der Regel die aufgerufene Seite)
 * @param string $state Ergebnis von ppb_board_access()
 * @param string $heading Überschrift der Karte
 *
 * @return string HTML
 */
function ppb_board_password_form(string $action, string $state, string $heading): string
{
    $error = match ($state) {
        BoardAccess::WRONG_PASSWORD => ppb_lang('bpwdnotcorrect', 'The board password is not correct.'),
        BoardAccess::LOCKED => ppb_lang('toomanyattempts', 'Too many attempts. Please try again later.'),
        default => '',
    };

    $html = '<section class="card shadow-sm mb-4 border-warning">'
        . '<header class="card-header bg-warning-subtle"><h2 class="h6 mb-0">'
        . '<i class="bi bi-shield-lock-fill" aria-hidden="true"></i> ' . Security::escape($heading)
        . '</h2></header><div class="card-body">';
    if ($error !== '') {
        $html .= '<div class="alert alert-danger" role="alert">'
            . '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> '
            . Security::escape($error) . '</div>';
    }

    return $html
        . '<form action="' . Security::escape($action) . '" method="post" class="needs-validation row g-2" novalidate>'
        . CSRF::getTokenField()
        . '<div class="col-sm-8">'
        . '<label for="boardpassword" class="form-label fw-semibold">'
        . Security::escape(ppb_lang('boardpassword', 'Board password')) . '</label>'
        . '<input id="boardpassword" name="boardpassword" type="password" class="form-control"'
        . ' maxlength="100" required autocomplete="off" aria-describedby="boardpasswordHelp">'
        . '<div class="invalid-feedback">' . Security::escape(ppb_lang('insertboardpwd', 'Please enter the board password.')) . '</div>'
        . '</div>'
        . '<div class="col-sm-4 d-flex align-items-start pt-sm-4 mt-sm-2">'
        . '<button type="submit" class="btn btn-primary w-100">'
        . '<i class="bi bi-unlock" aria-hidden="true"></i> '
        . Security::escape(ppb_lang('requestaccess', 'Unlock')) . '</button></div>'
        . '<div class="col-12"><div id="boardpasswordHelp" class="form-text mt-0">'
        . Security::escape(ppb_lang('boardpasswordhelp', 'Please enter the board password to access this area.'))
        . '</div></div></form></div></section>';
}

/**
 * Display default error message as Bootstrap alert with "back" link.
 *
 * Color parameters are kept for backwards compatibility but ignored.
 *
 * @param string $message Error message text
 * @param string $backUrl URL for back link
 * @param string $backText Text for back link
 * @param string $headerBg Legacy header background color (ignored)
 * @param string $contentBg Legacy content background color (ignored)
 * @param string $footerBg Legacy footer background color (ignored)
 */
function default_error(
    string $message,
    string $backUrl,
    string $backText,
    string $headerBg = '',
    string $contentBg = '',
    string $footerBg = ''
): void {
    echo '<div class="card shadow-sm border-danger mb-3">'
        . '<div class="card-header bg-danger text-white"><strong>'
        . Security::escape('Fehler')
        . '</strong></div>'
        . '<div class="card-body">'
        . '<p class="mb-3">' . Security::escape($message) . '</p>'
        . '<a href="' . Security::escape($backUrl) . '" class="btn btn-outline-secondary btn-sm">'
        . '<i class="bi bi-arrow-left" aria-hidden="true"></i> '
        . Security::escape($backText)
        . '</a>'
        . '</div></div>';
}

/**
 * Render a Bootstrap alert (success/info/warning/danger).
 *
 * @param string $message Alert body
 * @param string $type Bootstrap context (primary/success/info/warning/danger/...)
 * @param string|null $title Optional alert heading
 *
 * @return string HTML
 */
function ppb_alert(string $message, string $type = 'info', ?string $title = null): string
{
    $allowed = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }
    $body = '';
    if ($title !== null && $title !== '') {
        $body .= '<h2 class="h6 alert-heading mb-1">' . Security::escape($title) . '</h2>';
    }
    $body .= '<div>' . Security::escape($message) . '</div>';
    return '<div class="alert alert-' . $type . '" role="alert">' . $body . '</div>';
}

/**
 * Replace BBCode and smilies in post content.
 *
 * Legacy wrapper for {@see TextFormatter::formatPost()}.
 *
 * @param string $text Text to process (passed by reference for legacy compatibility)
 * @param string $bbcode Enable BBCode ('ON' or 'OFF')
 * @param string $smilies Enable smilies ('ON' or 'OFF')
 * @param string $htmlcode Allow HTML ('ON' or 'OFF')
 *
 * @return string Formatted text
 */
function posting_replace(string &$text, string $bbcode, string $smilies, string $htmlcode): string
{
    $text = TextFormatter::formatPost($text, $bbcode, $smilies, $htmlcode);
    return $text;
}

/**
 * Get user rank based on post count.
 *
 * @param int $userId User ID
 * @param Database $db Database instance
 *
 * @return string User rank title
 */
function getrank(int $userId, Database $db): string
{
    $result = $db->fetchOne(
        'SELECT COUNT(*) as count FROM ppb_posts WHERE author = ?',
        [$userId]
    );
    $postCount = (int) ($result['count'] ?? 0);

    return match (true) {
        $postCount > 8192 => 'Admiral',
        $postCount > 4096 => 'Vice Admiral',
        $postCount > 2048 => 'Rear Admiral',
        $postCount > 1024 => 'Fleet Captain',
        $postCount > 512 => 'Captain',
        $postCount > 256 => 'Commander',
        $postCount > 128 => 'Lt. Commander',
        $postCount > 64 => 'Lieutenant',
        $postCount > 32 => 'Lt. Junior Grade',
        $postCount > 16 => 'Ensign',
        $postCount > 8 => 'Cadet',
        default => 'Civilian',
    };
}

/**
 * Get pagination links for a thread as Bootstrap pagination.
 *
 * @param int $threadId Thread ID
 * @param Database $db Database instance
 * @param int $current Currently active offset (post index, 0-based on page boundaries)
 *
 * @return string HTML pagination block, or '' if only one page
 */
function getpages(int $threadId, Database $db, int $current = 0): string
{
    $result = $db->fetchOne(
        'SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ? OR id = ?',
        [$threadId, $threadId]
    );
    $postCount = (int) ($result['count'] ?? 0);

    $postsPerPage = 25;
    $pageNum = (int) ceil($postCount / $postsPerPage);

    if ($pageNum <= 1) {
        return '';
    }

    $output = '<nav aria-label="Seiten"><ul class="pagination pagination-sm mb-0">';
    for ($i = 0; $i < $pageNum; $i++) {
        $pageDisplay = $i + 1;
        $offset = $i * $postsPerPage;
        $isActive = ($offset === $current);
        $output .= '<li class="page-item' . ($isActive ? ' active' : '') . '"'
            . ($isActive ? ' aria-current="page"' : '') . '>'
            . '<a class="page-link" href="showthread.php?threadid=' . $threadId
            . '&current=' . $offset . '">' . $pageDisplay . '</a></li>';
    }
    $output .= '</ul></nav>';

    return $output;
}

/**
 * Format timestamp for display.
 *
 * @param int $timestamp Unix timestamp
 * @param string $format Date format string
 *
 * @return string Formatted date
 */
function format_date(int $timestamp, string $format = 'd.m.Y H:i'): string
{
    return date($format, $timestamp);
}

/**
 * Truncate text to specified length.
 *
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to append if truncated
 *
 * @return string Truncated text
 */
function truncate_text(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Convert legacy ON/OFF / YES/NO setting value to a German label "an"/"aus".
 *
 * @param string|null $value Setting value (typically 'ON', 'OFF', 'YES', 'NO')
 *
 * @return string 'an' or 'aus'
 */
function ppb_onoff_label(?string $value): string
{
    return in_array(strtoupper((string) $value), ['ON', 'YES', '1', 'AN'], true) ? 'an' : 'aus';
}

/**
 * Render an action button as Bootstrap button.
 *
 * Legacy parameters $imagePath and $buttonBg are kept for backwards compatibility
 * with existing call sites; $imagePath is ignored. If $buttonBg starts with
 * 'btn-' it is used as the Bootstrap button variant class, otherwise the default
 * 'btn-primary' is applied.
 *
 * @param string $href Link URL
 * @param string $imagePath Legacy image path (ignored)
 * @param string $altText Button label
 * @param string $buttonBg Either a Bootstrap variant ('btn-primary'/'btn-success'/...) or legacy color (ignored)
 *
 * @return string HTML for the button
 */
function render_action_button(
    string $href,
    string $imagePath = '',
    string $altText = '',
    string $buttonBg = ''
): string {
    $btnClass = 'btn btn-primary btn-sm';
    if (str_starts_with($buttonBg, 'btn-')) {
        $btnClass = 'btn ' . $buttonBg . ' btn-sm';
    }
    return '<a href="' . Security::escape($href) . '" class="' . $btnClass . '">'
        . Security::escape($altText) . '</a>';
}
