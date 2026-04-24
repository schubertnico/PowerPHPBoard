<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Core Functions
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 */

use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\TextFormatter;

/**
 * Display default error message
 *
 * @param string $message Error message
 * @param string $backUrl URL for back link
 * @param string $backText Text for back link
 * @param string $headerBg Header background color
 * @param string $contentBg Content background color
 * @param string $footerBg Footer background color
 */
function default_error(
    string $message,
    string $backUrl,
    string $backText,
    string $headerBg,
    string $contentBg,
    string $footerBg
): void {
    echo '
      <tr><td bgcolor="' . Security::escape($headerBg) . '">
      <b>Error message</b>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($contentBg) . '">
      <br>
      ' . Security::escape($message) . '<br><br>
      </td></tr>
      <tr><td bgcolor="' . Security::escape($footerBg) . '" align="center">
      <a href="' . Security::escape($backUrl) . '">' . Security::escape($backText) . '</a>
      </td></tr>
    ';
}

/**
 * Replace BBCode and smilies in post content
 * Legacy wrapper for TextFormatter::formatPost()
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
 * Get user rank based on post count
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
 * Get pagination links for a thread
 *
 * @param int $threadId Thread ID
 * @param Database $db Database instance
 *
 * @return string HTML pagination links
 */
function getpages(int $threadId, Database $db): string
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

    $output = '[ ';
    for ($i = 0; $i < $pageNum; $i++) {
        $pageDisplay = $i + 1;
        $current = $i * $postsPerPage;
        $output .= '<a href="showthread.php?threadid=' . $threadId . '&current=' . $current . '">' . $pageDisplay . '</a> ';
    }

    return $output . ']';
}

/**
 * Format timestamp for display
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
 * Truncate text to specified length
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
 * Render action button (image if exists, otherwise text button)
 *
 * @param string $href Link URL
 * @param string $imagePath Path to image file
 * @param string $altText Alt text / button label
 * @param string $buttonBg Background color for fallback button
 *
 * @return string HTML for the button
 */
function render_action_button(string $href, string $imagePath, string $altText, string $buttonBg = '#cccccc'): string
{
    $fullPath = __DIR__ . '/' . $imagePath;

    if ($imagePath !== '' && file_exists($fullPath)) {
        return '<a href="' . Security::escape($href) . '"><img src="' . Security::escape($imagePath) . '" border="0" width="120" height="20" alt="' . Security::escape($altText) . '"></a>';
    }

    return '<a href="' . Security::escape($href) . '" style="display:inline-block;padding:2px 10px;background:' . Security::escape($buttonBg) . ';text-decoration:none;color:#000;border:1px solid #999;font-size:12px;">' . Security::escape($altText) . '</a>';
}
