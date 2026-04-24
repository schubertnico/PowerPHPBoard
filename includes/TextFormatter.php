<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Text Formatter (BBCode, Smilies)
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
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

namespace PowerPHPBoard;

/**
 * Text formatting for posts (BBCode and smilies)
 * Replaces legacy posting_replace() function with eregi_replace
 */
class TextFormatter
{
    /**
     * Smilie code to image mapping
     *
     * @var array<string, array{file: string, width: int, height: int}>
     */
    private static array $smilies = [
        ':)' => ['file' => 'smile.gif', 'width' => 15, 'height' => 15],
        ':P' => ['file' => 'tongue.gif', 'width' => 15, 'height' => 15],
        ';)' => ['file' => 'wink.gif', 'width' => 15, 'height' => 15],
        ':D' => ['file' => 'biggrin.gif', 'width' => 15, 'height' => 15],
        ':eek:' => ['file' => 'eek.gif', 'width' => 15, 'height' => 15],
        ':confused:' => ['file' => 'confused.gif', 'width' => 15, 'height' => 22],
        ':cool:' => ['file' => 'cool.gif', 'width' => 15, 'height' => 15],
        ':(' => ['file' => 'frown.gif', 'width' => 15, 'height' => 15],
        ':mad:' => ['file' => 'mad.gif', 'width' => 15, 'height' => 15],
        ':o' => ['file' => 'redface.gif', 'width' => 15, 'height' => 15],
        ':rolleyes:' => ['file' => 'rolleyes.gif', 'width' => 15, 'height' => 15],
    ];

    /**
     * Format post content with BBCode and smilies
     *
     * @param string $text The raw post text
     * @param string $bbcode Enable BBCode processing ('ON' or 'OFF')
     * @param string $smilies Enable smilie replacement ('ON' or 'OFF')
     * @param string $htmlcode Allow HTML in post ('ON' or 'OFF')
     *
     * @return string Formatted HTML output
     */
    public static function formatPost(
        string $text,
        string $bbcode = 'ON',
        string $smilies = 'ON',
        string $htmlcode = 'OFF'
    ): string {
        // Remove magic quotes escaping if present
        $text = stripslashes($text);

        // Escape HTML if not allowed (XSS prevention)
        if (strtoupper($htmlcode) !== 'ON') {
            $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Process BBCode
        if (strtoupper($bbcode) === 'ON') {
            $text = self::processBBCode($text);
        }

        // Process smilies
        if (strtoupper($smilies) === 'ON') {
            $text = self::processSmilies($text);
        }

        // Convert newlines to <br>
        $text = nl2br($text, false);

        return $text;
    }

    /**
     * Process BBCode tags
     * Uses preg_replace instead of deprecated eregi_replace
     */
    private static function processBBCode(string $text): string
    {
        // Basic formatting tags (case-insensitive)
        $patterns = [
            '/\[b\]/i' => '<b>',
            '/\[\/b\]/i' => '</b>',
            '/\[u\]/i' => '<u>',
            '/\[\/u\]/i' => '</u>',
            '/\[i\]/i' => '<i>',
            '/\[\/i\]/i' => '</i>',
            '/\[s\]/i' => '<s>',
            '/\[\/s\]/i' => '</s>',
            '/\[quote\]/i' => '<blockquote class="ppb-quote"><small>Quote:</small><hr>',
            '/\[\/quote\]/i' => '<hr></blockquote>',
            '/\[code\]/i' => '<pre class="ppb-code">',
            '/\[\/code\]/i' => '</pre>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        // URL with custom text: [url=http://example.com]text[/url]
        $text = preg_replace(
            '/\[url=([^\]]+)\](.+?)\[\/url\]/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>',
            $text
        ) ?? $text;

        // Simple URL: [url]http://example.com[/url]
        $text = preg_replace(
            '/\[url\]([^\[]+)\[\/url\]/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $text
        ) ?? $text;

        // Image: [img]http://example.com/image.jpg[/img]
        $text = preg_replace(
            '/\[img\]([^\[]+)\[\/img\]/i',
            '<img src="$1" alt="User image" class="ppb-image">',
            $text
        ) ?? $text;

        // Auto-link email addresses
        $text = preg_replace(
            '/([a-zA-Z0-9._+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',
            '<a href="mailto:$1">$1</a>',
            $text
        ) ?? $text;

        // Auto-link URLs (http/https/ftp)
        $text = preg_replace(
            '/(?<!["\'>])(https?:\/\/[a-zA-Z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]+)/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $text
        ) ?? $text;

        return $text;
    }

    /**
     * Replace smilie codes with images
     */
    private static function processSmilies(string $text): string
    {
        foreach (self::$smilies as $code => $info) {
            $escapedCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            $img = sprintf(
                '<img src="images/%s" width="%d" height="%d" alt="%s" class="ppb-smilie">',
                $info['file'],
                $info['width'],
                $info['height'],
                $escapedCode
            );
            $text = str_replace($code, $img, $text);
        }

        return $text;
    }

    /**
     * Strip BBCode tags from text
     */
    public static function stripBBCode(string $text): string
    {
        // Remove all BBCode tags
        $patterns = [
            '/\[b\]/i', '/\[\/b\]/i',
            '/\[u\]/i', '/\[\/u\]/i',
            '/\[i\]/i', '/\[\/i\]/i',
            '/\[s\]/i', '/\[\/s\]/i',
            '/\[quote\]/i', '/\[\/quote\]/i',
            '/\[code\]/i', '/\[\/code\]/i',
            '/\[url=[^\]]+\]/i', '/\[url\]/i', '/\[\/url\]/i',
            '/\[img\]/i', '/\[\/img\]/i',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        return $text;
    }

    /**
     * Get list of available smilie codes
     *
     * @return array<string, array{file: string, width: int, height: int}>
     */
    public static function getSmilies(): array
    {
        return self::$smilies;
    }
}
