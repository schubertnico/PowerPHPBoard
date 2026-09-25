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
 *
 * Sicherheitsmodell: Der Rohtext wird IMMER zuerst vollständig escaped.
 * Erst danach entstehen HTML-Tags, und zwar ausschließlich aus drei Quellen:
 * der Whitelist attributloser HTML-Tags (nur bei htmlcode = ON), den
 * BBCode-Regeln und den Smilies. Attribute setzt nur der Formatter selbst,
 * URLs in href/src müssen mit http:// oder https:// beginnen.
 */
class TextFormatter
{
    /**
     * HTML-Tags, die bei eingeschaltetem HTML-Code erlaubt sind – nur ohne
     * Attribute (also kein Event-Handler, kein style, keine URL).
     *
     * @var list<string>
     */
    public const ALLOWED_HTML_TAGS = [
        'b', 'i', 'u', 's', 'strong', 'em', 'del', 'ins', 'mark', 'small',
        'sub', 'sup', 'p', 'br', 'hr', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
    ];

    /**
     * Tags ohne schließendes Gegenstück
     *
     * @var list<string>
     */
    private const VOID_TAGS = ['br', 'hr', 'img'];

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
     * @param string $htmlcode Allow a small whitelist of HTML tags without attributes ('ON' or 'OFF')
     * @param array{quote?: string, image?: string} $labels Localized labels for quote heading and image alt text
     *
     * @return string Formatted HTML output
     */
    public static function formatPost(
        string $text,
        string $bbcode = 'ON',
        string $smilies = 'ON',
        string $htmlcode = 'OFF',
        array $labels = []
    ): string {
        // Remove magic quotes escaping if present
        $text = stripslashes($text);

        // Immer zuerst alles escapen (XSS-Schutz), unabhängig von htmlcode
        $html = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Bei HTML = ON nur attributlose Whitelist-Tags wiederherstellen
        if (strtoupper($htmlcode) === 'ON') {
            $html = self::restoreAllowedTags($html);
        }

        $useBBCode = strtoupper($bbcode) === 'ON';
        $useSmilies = strtoupper($smilies) === 'ON';

        // Process BBCode
        if ($useBBCode) {
            $html = self::processBBCode($html, $labels);
        }

        // Automatische Links und Smilies nur im Fließtext, nie in Attributen.
        // Links nicht in bestehenden Links, Smilies nicht in Code-Blöcken.
        if ($useBBCode) {
            $html = self::mapTextSegments(
                $html,
                static fn (string $text, int $inLink, int $inPre): string => $inLink > 0 ? $text : self::autoLink($text)
            );
        }
        if ($useSmilies) {
            $html = self::mapTextSegments(
                $html,
                static fn (string $text, int $inLink, int $inPre): string => $inPre > 0 ? $text : self::processSmilies($text)
            );
        }

        // Nicht geschlossene oder verschachtelte Tags reparieren
        $html = self::balanceTags($html);

        // Convert newlines to <br>
        return nl2br($html, false);
    }

    /**
     * Prüft eine Link- oder Bildadresse aus Benutzereingaben.
     *
     * Erlaubt sind nur absolute http(s)-Adressen mit Hostnamen; "www.…" wird
     * zu "https://www.…" ergänzt. Alles andere (javascript:, data:, vbscript:,
     * relative Pfade, Steuer-, Leer- und Sonderzeichen wie " ' < > \) ergibt null.
     *
     * @param string $url Adresse im Klartext (nicht HTML-escaped)
     *
     * @return string|null Normalisierte Adresse oder null, wenn unzulässig
     */
    public static function sanitizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7F"\'<>`\\\\]/', $url) === 1) {
            return null;
        }
        if (preg_match('~^www\.~i', $url) === 1) {
            $url = 'https://' . $url;
        }
        if (preg_match('~^https?://~i', $url) !== 1) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        return $url;
    }

    /**
     * Stellt erlaubte HTML-Tags ohne Attribute aus dem escapten Text wieder her.
     *
     * Beispiel: "&lt;b&gt;" wird zu "<b>", "&lt;b onclick=…&gt;" bleibt Text.
     */
    private static function restoreAllowedTags(string $html): string
    {
        return preg_replace_callback(
            '/&lt;(\/?)([a-zA-Z][a-zA-Z0-9]*)\s*\/?&gt;/',
            static function (array $m): string {
                $name = strtolower($m[2]);
                if (!in_array($name, self::ALLOWED_HTML_TAGS, true)) {
                    return $m[0];
                }
                if (in_array($name, self::VOID_TAGS, true)) {
                    return $m[1] === '/' ? '' : '<' . $name . '>';
                }

                return '<' . $m[1] . $name . '>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Process BBCode tags
     * Uses preg_replace instead of deprecated eregi_replace
     *
     * @param array{quote?: string, image?: string} $labels
     */
    private static function processBBCode(string $text, array $labels = []): string
    {
        $quoteLabel = htmlspecialchars($labels['quote'] ?? 'Quote:', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $imageLabel = htmlspecialchars($labels['image'] ?? 'Image', ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
            '/\[quote\]/i' => '<blockquote class="ppb-quote"><small>' . $quoteLabel . '</small><hr>',
            '/\[\/quote\]/i' => '<hr></blockquote>',
            '/\[code\]/i' => '<pre class="ppb-code">',
            '/\[\/code\]/i' => '</pre>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        // URL with custom text: [url=http://example.com]text[/url] (Anführungszeichen optional)
        $text = preg_replace_callback(
            '/\[url=(?:&quot;|&apos;)?([^\]<>"\s]+?)(?:&quot;|&apos;)?\](.+?)\[\/url\]/is',
            static function (array $m): string {
                $href = self::escapedUrlToAttribute($m[1]);
                if ($href === null) {
                    return $m[0];
                }

                return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $m[2] . '</a>';
            },
            $text
        ) ?? $text;

        // Simple URL: [url]http://example.com[/url]
        $text = preg_replace_callback(
            '/\[url\]([^\[\]<>"\s]+)\[\/url\]/i',
            static function (array $m): string {
                $href = self::escapedUrlToAttribute($m[1]);
                if ($href === null) {
                    return $m[0];
                }

                return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
            },
            $text
        ) ?? $text;

        // Image: [img]http://example.com/image.jpg[/img]
        return preg_replace_callback(
            '/\[img\]([^\[\]<>"\s]+)\[\/img\]/i',
            static function (array $m) use ($imageLabel): string {
                $src = self::escapedUrlToAttribute($m[1]);
                if ($src === null) {
                    return $m[0];
                }

                return '<img src="' . $src . '" alt="' . $imageLabel . '" class="ppb-image" loading="lazy">';
            },
            $text
        ) ?? $text;
    }

    /**
     * Wandelt eine (bereits HTML-escapte) Adresse aus dem Beitrag in einen
     * sicheren Attributwert um oder liefert null, wenn sie unzulässig ist.
     */
    private static function escapedUrlToAttribute(string $escapedUrl): ?string
    {
        $url = self::sanitizeUrl(html_entity_decode($escapedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $url === null ? null : htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Wendet $callback nur auf die Textabschnitte zwischen den Tags an –
     * niemals auf Tags oder Attributwerte. Der Callback erfährt, wie tief der
     * Abschnitt in <a>- bzw. <pre>-Elementen steckt.
     *
     * @param callable(string, int, int): string $callback
     */
    private static function mapTextSegments(string $html, callable $callback): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        $inLink = 0;
        $inPre = 0;
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                $inLink = max(0, $inLink + self::tagDelta($part, 'a'));
                $inPre = max(0, $inPre + self::tagDelta($part, 'pre'));
                $out .= $part;
                continue;
            }
            $out .= $callback($part, $inLink, $inPre);
        }

        return $out;
    }

    /**
     * +1 für ein öffnendes, -1 für ein schließendes Tag des Namens, sonst 0
     */
    private static function tagDelta(string $tag, string $name): int
    {
        if (preg_match('/^<' . $name . '[\s>]/i', $tag) === 1) {
            return 1;
        }

        return preg_match('/^<\/' . $name . '>/i', $tag) === 1 ? -1 : 0;
    }

    /**
     * Auto-link URLs (http/https) and email addresses in a text segment.
     * Der Text ist HTML-escaped; "&amp;" gehört zur Adresse, andere
     * Entitäten (&quot;, &lt;, …) beenden sie.
     */
    private static function autoLink(string $text): string
    {
        return preg_replace_callback(
            '~(https?://(?:[a-zA-Z0-9\-._\~:/?#\[\]@!$*+,;=%()]|&amp;)+)|([a-zA-Z0-9._+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})~',
            static function (array $m): string {
                if (($m[2] ?? '') !== '') {
                    return '<a href="mailto:' . $m[2] . '">' . $m[2] . '</a>';
                }
                $url = $m[1];
                $trailing = '';
                // Satzzeichen am Ende gehören nicht zur Adresse ("…/seite.")
                if (preg_match('/[.,;:!?)]+$/', $url, $t) === 1) {
                    $trailing = $t[0];
                    $url = substr($url, 0, -strlen($trailing));
                }
                $href = self::escapedUrlToAttribute($url);
                if ($href === null) {
                    return $m[0];
                }

                return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $trailing;
            },
            $text
        ) ?? $text;
    }

    /**
     * Replace smilie codes with images
     *
     * Entitäten wie "&apos;)" oder "&quot;)" bleiben unangetastet, sonst würde
     * das ";)" darin als Smilie erkannt und die Entität zerstört.
     */
    private static function processSmilies(string $text): string
    {
        $parts = preg_split('/(&[a-zA-Z0-9#]+;)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }

        $replacements = [];
        foreach (self::$smilies as $code => $info) {
            $replacements[$code] = sprintf(
                '<img src="images/%s" width="%d" height="%d" alt="%s" class="ppb-smilie">',
                $info['file'],
                $info['width'],
                $info['height'],
                htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
            );
        }

        $out = '';
        foreach ($parts as $index => $part) {
            // Ungerade Indizes sind die abgetrennten Entitäten
            $out .= ($index % 2 === 1) ? $part : strtr($part, $replacements);
        }

        return $out;
    }

    /**
     * Schließt offene Tags und entfernt schließende Tags ohne Gegenstück,
     * damit ein Beitrag das Seitenlayout nicht beschädigen kann.
     */
    private static function balanceTags(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        /** @var list<string> $stack */
        $stack = [];
        $out = '';
        foreach ($parts as $part) {
            if (preg_match('/^<(\/?)([a-z][a-z0-9]*)\b/i', $part, $m) !== 1) {
                $out .= $part;
                continue;
            }
            $name = strtolower($m[2]);
            $closing = $m[1] === '/';
            if (in_array($name, self::VOID_TAGS, true)) {
                $out .= $closing ? '' : $part;
                continue;
            }
            if (!$closing) {
                $stack[] = $name;
                $out .= $part;
                continue;
            }
            // Schließendes Tag ohne offenes Gegenstück wird verworfen,
            // dazwischen offene Tags werden vorher geschlossen.
            $position = array_search($name, array_reverse($stack, true), true);
            if (is_int($position)) {
                $out .= self::closeTags(array_splice($stack, $position));
            }
        }

        return $out . self::closeTags($stack);
    }

    /**
     * Schließende Tags für die offenen Tags (innerstes zuerst)
     *
     * @param list<string> $openTags
     */
    private static function closeTags(array $openTags): string
    {
        $out = '';
        foreach (array_reverse($openTags) as $tag) {
            $out .= '</' . $tag . '>';
        }

        return $out;
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
