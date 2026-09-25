<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Core Functions
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Auth;
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
 * Symbol eines Themas (icon1.gif … icon14.gif) als <img>, sonst ''.
 */
function ppb_thread_icon(string $icon): string
{
    if (preg_match('/^icon([1-9]|1[0-4])\.gif$/', $icon) !== 1) {
        return '';
    }

    return '<img src="images/' . $icon . '" width="15" height="15" alt="" class="ppb-thread-icon me-1">';
}

/**
 * Pfad eines eigenen Header-/Footer-Templates aus dem Ordner inc/.
 *
 * Erlaubt sind nur Dateinamen ohne Verzeichnisanteil, damit über die
 * Einstellung keine beliebige Datei eingebunden werden kann.
 *
 * @return string|null Absoluter Pfad oder null, wenn der Name unzulässig ist oder die Datei fehlt
 */
function ppb_template_path(string $name): ?string
{
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) !== 1) {
        return null;
    }
    $path = __DIR__ . '/inc/' . $name;

    return is_file($path) ? $path : null;
}

/**
 * Gültiger Wert für die Einstellung „Eigenes Header-/Footer-Template“:
 * leer oder ein vorhandener Dateiname aus inc/
 */
function ppb_valid_template_setting(string $name): bool
{
    return $name === '' || ppb_template_path($name) !== null;
}

/**
 * Die alten Design-Felder (Templates, Farben, Button-Bilder) für die
 * Formulare im Adminbereich. Sie wirken im Bootstrap-Layout nicht mehr,
 * sind optional und bleiben nur aus Kompatibilitätsgründen erhalten.
 *
 * @param array<string, mixed> $values Aktuelle Werte (header, footer, bordercolor, …)
 * @param string $note Hinweistext über den Feldern
 *
 * @return string HTML
 */
function ppb_admin_design_fields(array $values, string $note): string
{
    $value = static fn (string $key): string => Security::escape((string) ($values[$key] ?? ''));
    $label = static fn (string $key, string $fallback): string => Security::escape(ppb_lang($key, $fallback));

    $html = '<div class="alert alert-info small d-flex align-items-start gap-2 mb-3" role="alert">'
        . '<i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i><div><strong>'
        . $label('notice', 'Notice') . ':</strong> ' . Security::escape($note) . '</div></div>'
        . '<div class="row g-3">';

    foreach (['header' => ['adm_headertemplate', 'Custom header template'], 'footer' => ['adm_footertemplate', 'Custom footer template']] as $field => [$key, $fallback]) {
        $html .= '<div class="col-md-6"><label for="' . $field . '" class="form-label">' . $label($key, $fallback) . '</label>'
            . '<input id="' . $field . '" name="' . $field . '" type="text" class="form-control" maxlength="250"'
            . ' value="' . $value($field) . '" aria-describedby="' . $field . 'Help">'
            . '<div id="' . $field . 'Help" class="form-text">' . $label('adm_templatehelp', 'File name from the inc/ folder; empty = default.') . '</div></div>';
    }

    $colors = [
        'bordercolor' => ['adm_bordercolor', 'Border colour', 'adm_colorhelp', 'Hex colour code, e.g. #000000'],
        'tablebg1' => ['adm_tablebg1', 'Table background 1', 'adm_tablebg1help', 'Light row'],
        'tablebg2' => ['adm_tablebg2', 'Table background 2', 'adm_tablebg2help', 'Alternating row'],
        'tablebg3' => ['adm_tablebg3', 'Table background 3', 'adm_tablebg3help', 'Table header'],
    ];
    foreach ($colors as $field => [$key, $fallback, $helpKey, $helpFallback]) {
        // Nur gültige Hex-Farben als Vorschau, damit kein CSS in das style-Attribut gelangt
        $color = (string) ($values[$field] ?? '');
        $swatch = preg_match('/^#[0-9A-Fa-f]{3,6}$/', $color) === 1 ? $color : 'transparent';
        $html .= '<div class="col-md-3"><label for="' . $field . '" class="form-label">' . $label($key, $fallback) . '</label>'
            . '<div class="input-group"><input id="' . $field . '" name="' . $field . '" type="text" class="form-control" maxlength="7"'
            . ' value="' . $value($field) . '">'
            . '<span class="input-group-text" style="background:' . $swatch . ';width:38px;" aria-hidden="true">&nbsp;</span></div>'
            . '<div class="form-text">' . $label($helpKey, $helpFallback) . '</div></div>';
    }

    foreach (['newthread' => ['adm_newthreadimage', 'Image for the "New thread" button'], 'newpost' => ['adm_newpostimage', 'Image for the "New post" button']] as $field => [$key, $fallback]) {
        $html .= '<div class="col-md-6"><label for="' . $field . '" class="form-label">' . $label($key, $fallback) . '</label>'
            . '<input id="' . $field . '" name="' . $field . '" type="text" class="form-control" maxlength="250"'
            . ' value="' . $value($field) . '" aria-describedby="' . $field . 'Help">'
            . '<div id="' . $field . 'Help" class="form-text">' . $label('adm_buttonimagehelp', 'Path to a 120 × 20 pixel image, e.g. images/newthread.gif') . '</div></div>';
    }

    return $html . '</div>';
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
 * Schaltflächen „Neues Thema“ und „Neuer Beitrag“ bzw. die Hinweise
 * „Forum geschlossen“ und „Thema geschlossen“ (Kopf von Themenliste und
 * Thema, Fuß des Themas).
 *
 * Geschlossene Boards und Themen sind für normale Mitglieder gesperrt.
 * Administratoren und die Moderatoren dieses Boards sehen die
 * Schaltflächen weiterhin, mit einem dezenten Hinweis, dass sie mit
 * Moderationsrechten schreiben.
 *
 * @param array<string, mixed> $board Board-Zeile
 * @param array<string, mixed> $thread Thema oder [] (Themenliste, Thema ohne Zugang)
 * @param array<string, mixed>|null $user Angemeldeter Benutzer oder null
 * @param int $current Aktuelle Seite im Thema (für den Rückweg aus dem Antwortformular)
 * @param bool $small Kleine Schaltflächen (Kopfbereich)
 *
 * @return string HTML
 */
function ppb_write_actions(array $board, array $thread, ?array $user, int $current, bool $small): string
{
    $size = $small ? ' btn-sm' : '';
    $badge = static fn (string $key, string $fallback): string => '<span class="badge text-bg-secondary align-self-center">'
        . Security::escape(ppb_lang($key, $fallback)) . '</span>';
    $hint = static fn (string $key, string $fallback): string => '<span class="ppb-mod-hint small text-body-secondary align-self-center">'
        . '<i class="bi bi-shield-check" aria-hidden="true"></i> ' . Security::escape(ppb_lang($key, $fallback)) . '</span>';

    if (!Auth::canWriteInBoard($user, $board)) {
        return $badge('boardclosed', 'Board closed');
    }

    $html = Auth::isBoardClosed($board)
        ? $hint('boardclosedmodhint', 'Board closed – you are posting with moderator rights.')
        : '';
    $html .= '<a class="btn btn-primary' . $size . '" href="newthread.php?boardid=' . (int) ($board['id'] ?? 0) . '">'
        . '<i class="bi bi-plus-circle" aria-hidden="true"></i> '
        . Security::escape(ppb_lang('newthread', 'New thread')) . '</a>';

    if ($thread === []) {
        return $html;
    }
    if (!Auth::canReplyInThread($user, $board, $thread)) {
        return $html . $badge('threadclosed', 'Thread closed');
    }
    if (($thread['status'] ?? '') === 'Closed' && !Auth::isBoardClosed($board)) {
        $html = $hint('threadclosedmodhint', 'Thread closed – you are replying with moderator rights.') . $html;
    }

    return $html . '<a class="btn btn-success' . $size . '" href="newpost.php?threadid=' . (int) ($thread['id'] ?? 0)
        . '&current=' . $current . '">'
        . '<i class="bi bi-reply" aria-hidden="true"></i> '
        . Security::escape(ppb_lang('newpost', 'New post')) . '</a>';
}

/**
 * Hinweis im Formular „Neues Thema“ bzw. „Neuer Beitrag“, wenn ein
 * Administrator oder Moderator in einem geschlossenen Board oder Thema
 * schreibt; sonst ''.
 *
 * @param array<string, mixed> $board
 * @param array<string, mixed> $thread [] beim Formular „Neues Thema“
 *
 * @return string HTML
 */
function ppb_closed_write_notice(array $board, array $thread): string
{
    if (Auth::isBoardClosed($board)) {
        $text = ppb_lang('boardclosedmodhint', 'Board closed – you are posting with moderator rights.');
    } elseif ($thread !== [] && ($thread['status'] ?? '') === 'Closed') {
        $text = ppb_lang('threadclosedmodhint', 'Thread closed – you are replying with moderator rights.');
    } else {
        return '';
    }

    return '<div class="alert alert-secondary small d-flex align-items-center gap-2 ppb-mod-hint" role="note">'
        . '<i class="bi bi-shield-check" aria-hidden="true"></i><div>' . Security::escape($text) . '</div></div>';
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
        . '<form action="' . Security::escape($action) . '" method="post" class="needs-validation" novalidate>'
        . CSRF::getTokenField()
        . '<label for="boardpassword" class="form-label fw-semibold">'
        . Security::escape(ppb_lang('boardpassword', 'Board password')) . '</label>'
        . '<div class="input-group has-validation">'
        . '<input id="boardpassword" name="boardpassword" type="password" class="form-control"'
        . ' maxlength="100" required autocomplete="off" aria-describedby="boardpasswordHelp">'
        . '<button type="submit" class="btn btn-primary">'
        . '<i class="bi bi-unlock" aria-hidden="true"></i> '
        . Security::escape(ppb_lang('requestaccess', 'Unlock')) . '</button>'
        . '<div class="invalid-feedback">' . Security::escape(ppb_lang('insertboardpwd', 'Please enter the board password.')) . '</div>'
        . '</div>'
        . '<div id="boardpasswordHelp" class="form-text">'
        . Security::escape(ppb_lang('boardpasswordhelp', 'Please enter the board password to access this area.'))
        . '</div></form></div></section>';
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
        . Security::escape(ppb_lang('errormessage', 'Error'))
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

    // Neutrale Forenränge aus der Sprachdatei; die Schwellen verdoppeln sich je Stufe
    [$key, $fallback] = match (true) {
        $postCount > 8192 => ['rank_legend', 'Legend'],
        $postCount > 4096 => ['rank_oldhand', 'Old hand'],
        $postCount > 2048 => ['rank_veteran', 'Veteran'],
        $postCount > 1024 => ['rank_professional', 'Professional'],
        $postCount > 512 => ['rank_expert', 'Expert'],
        $postCount > 256 => ['rank_seasoned', 'Seasoned member'],
        $postCount > 128 => ['rank_experienced', 'Experienced member'],
        $postCount > 64 => ['rank_regular', 'Regular'],
        $postCount > 32 => ['rank_active', 'Active member'],
        $postCount > 16 => ['rank_member', 'Member'],
        $postCount > 8 => ['rank_beginner', 'Beginner'],
        default => ['rank_newcomer', 'Newcomer'],
    };

    return ppb_lang($key, $fallback);
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

    $output = '<nav aria-label="' . Security::escape(ppb_lang('pages', 'Pages')) . '"><ul class="pagination pagination-sm mb-0">';
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
 * Convert legacy ON/OFF / YES/NO setting value to a localized label ("an"/"aus", "on"/"off").
 *
 * @param string|null $value Setting value (typically 'ON', 'OFF', 'YES', 'NO')
 *
 * @return string Label from the language file ($lang_on / $lang_off)
 */
function ppb_onoff_label(?string $value): string
{
    return in_array(strtoupper((string) $value), ['ON', 'YES', '1', 'AN'], true)
        ? ppb_lang('on', 'on')
        : ppb_lang('off', 'off');
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
