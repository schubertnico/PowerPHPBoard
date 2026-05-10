<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Core Functions
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\TextFormatter;

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
