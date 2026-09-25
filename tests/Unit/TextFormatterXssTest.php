<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use Dom\Element;
use Dom\HTMLDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\TextFormatter;

/**
 * Regressionstests gegen gespeichertes XSS in Beiträgen.
 *
 * Jede Eingabe wird in allen Kombinationen aus HTML an/aus, BBCode an/aus
 * und Smilies an/aus formatiert. Die Ausgabe wird mit dem HTML5-Parser von
 * PHP 8.4 so geparst, wie ein Browser sie sehen würde, und anschließend auf
 * erlaubte Elemente, Attribute und URL-Schemata geprüft.
 */
final class TextFormatterXssTest extends TestCase
{
    /**
     * Welche Attribute der Formatter pro Element selbst setzen darf
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'class', 'width', 'height', 'loading'],
        'blockquote' => ['class'],
        'pre' => ['class'],
    ];

    /**
     * Typische Umgehungsklassen (Payload => Beschreibung)
     *
     * @return array<string, array{string}>
     */
    public static function payloads(): array
    {
        return [
            'script-tag' => ['<script>alert(1)</script>'],
            'script-grossbuchstaben' => ['<SCRIPT>alert(1)</SCRIPT>'],
            'script-verschachtelt' => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
            'img-onerror' => ['<img src=x onerror=alert(1)>'],
            'svg-onload' => ['<svg onload=alert(1)>'],
            'svg-script' => ['<svg><script>alert(1)</script></svg>'],
            'math-href' => ['<math><mtext><a href="javascript:alert(1)">x</a></mtext></math>'],
            'erlaubtes-tag-mit-handler' => ['<b onclick="alert(1)">fett</b>'],
            'erlaubtes-tag-slash-handler' => ['<b/onmouseover=alert(1)>fett</b>'],
            'erlaubtes-tag-tab-handler' => ["<b\tonmouseover=alert(1)>fett</b>"],
            'erlaubtes-tag-newline-handler' => ["<b\nonmouseover=alert(1)>fett</b>"],
            'erlaubtes-tag-mit-style' => ['<p style="background:url(javascript:alert(1))">x</p>'],
            'a-javascript' => ['<a href="javascript:alert(1)">klick</a>'],
            'a-data' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>'],
            'iframe' => ['<iframe src="https://evil.example"></iframe>'],
            'object-embed' => ['<object data="x.swf"></object><embed src="x.swf">'],
            'form-action' => ['<form action="javascript:alert(1)"><button>x</button></form>'],
            'meta-refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'],
            'base-href' => ['<base href="javascript:alert(1)//">'],
            'style-tag' => ['<style>body{background:url(javascript:alert(1))}</style>'],
            'link-stylesheet' => ['<link rel="stylesheet" href="https://evil.example/x.css">'],
            'html-kommentar' => ['<!--<img src=x onerror=alert(1)>-->'],
            'cdata' => ['<![CDATA[<img src=x onerror=alert(1)>]]>'],
            'entity-kodiert' => ['&lt;script&gt;alert(1)&lt;/script&gt;'],
            'bbcode-url-javascript' => ['[url=javascript:alert(1)]klick[/url]'],
            'bbcode-url-javascript-gross' => ['[url=JaVaScRiPt:alert(1)]klick[/url]'],
            'bbcode-url-javascript-leerzeichen' => ['[url= javascript:alert(1)]klick[/url]'],
            'bbcode-url-javascript-tab' => ["[url=java\tscript:alert(1)]klick[/url]"],
            'bbcode-url-javascript-entity' => ['[url=java&#x09;script:alert(1)]klick[/url]'],
            'bbcode-url-javascript-kodiert' => ['[url=javascript&#58;alert(1)]klick[/url]'],
            'bbcode-url-data' => ['[url]data:text/html,<script>alert(1)</script>[/url]'],
            'bbcode-url-vbscript' => ['[url]vbscript:msgbox(1)[/url]'],
            'bbcode-url-anfuehrungszeichen' => ['[url=https://example.org" onmouseover="alert(1)]x[/url]'],
            'bbcode-url-apostroph' => ["[url=https://example.org' onmouseover='alert(1)]x[/url]"],
            'bbcode-url-verschachtelt' => ['[url=https://a.example][url]javascript:alert(1)[/url][/url]'],
            'bbcode-url-mit-email' => ['[url=https://x onmouseover=alert(1) a@b.cc]x[/url]'],
            'bbcode-img-javascript' => ['[img]javascript:alert(1)[/img]'],
            'bbcode-img-handler' => ['[img]https://example.org/a.png" onerror="alert(1)[/img]'],
            'bbcode-img-data' => ['[img]data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=[/img]'],
            'autolink-anfuehrungszeichen' => ['https://example.org/"onmouseover="alert(1)'],
            'autolink-apostroph' => ["https://example.org/'onmouseover='alert(1)"],
            'autolink-spitze-klammer' => ['https://example.org/<script>alert(1)</script>'],
            'smilie-in-entity' => ["echo('Hallo');) \"x\";) <b>;)</b>"],
            'smilie-in-link' => ['[url=https://example.org/:o]link[/url] mailto:office@example.org'],
            'quote-mit-html' => ['[quote]<img src=x onerror=alert(1)>[/quote]'],
            'code-mit-html' => ['[code]<script>alert(1)</script>[/code]'],
            'nicht-geschlossen' => ['[quote][b]offen <blockquote><ul><li>x'],
            'fremde-schliesstags' => ['</div></main></body><script>alert(1)</script>'],
        ];
    }

    #[Test]
    #[DataProvider('payloads')]
    public function outputContainsNoActiveContent(string $payload): void
    {
        foreach (['ON', 'OFF'] as $html) {
            foreach (['ON', 'OFF'] as $bbcode) {
                foreach (['ON', 'OFF'] as $smilies) {
                    $output = TextFormatter::formatPost($payload, $bbcode, $smilies, $html);
                    $this->assertSafeHtml($output, "html=$html bbcode=$bbcode smilies=$smilies");
                }
            }
        }
    }

    #[Test]
    public function allowedTagsWithoutAttributesAreKeptWhenHtmlIsOn(): void
    {
        $output = TextFormatter::formatPost('<b>fett</b> <I>kursiv</I> <br/> <ul><li>x</li></ul>', 'OFF', 'OFF', 'ON');

        $this->assertStringContainsString('<b>fett</b>', $output);
        $this->assertStringContainsString('<i>kursiv</i>', $output);
        $this->assertStringContainsString('<ul><li>x</li></ul>', $output);
    }

    #[Test]
    public function allowedTagWithAttributeStaysText(): void
    {
        $output = TextFormatter::formatPost('<b onclick="alert(1)">fett</b>', 'OFF', 'OFF', 'ON');

        $this->assertStringContainsString('&lt;b onclick=&quot;alert(1)&quot;&gt;', $output);
        $this->assertStringNotContainsString('<b onclick', $output);
    }

    #[Test]
    public function everythingIsEscapedWhenHtmlIsOff(): void
    {
        $output = TextFormatter::formatPost('<b>fett</b>', 'OFF', 'OFF', 'OFF');

        $this->assertSame('&lt;b&gt;fett&lt;/b&gt;', $output);
    }

    #[Test]
    public function unclosedTagsAreClosed(): void
    {
        $output = TextFormatter::formatPost('[quote][b]offen', 'ON', 'OFF', 'OFF');

        $this->assertStringEndsWith('</b></blockquote>', $output);
    }

    #[Test]
    public function strayClosingTagsAreRemoved(): void
    {
        $output = TextFormatter::formatPost('Text[/code]</b></i>', 'ON', 'OFF', 'ON');

        $this->assertSame('Text', $output);
    }

    #[Test]
    public function javascriptUrlInBbcodeIsNotLinked(): void
    {
        $output = TextFormatter::formatPost('[url=javascript:alert(1)]klick[/url]', 'ON', 'OFF', 'OFF');

        $this->assertStringNotContainsString('<a', $output);
        $this->assertStringContainsString('[url=javascript:alert(1)]klick[/url]', $output);
    }

    #[Test]
    public function wwwUrlIsCompletedToHttps(): void
    {
        $output = TextFormatter::formatPost('[url]www.powerscripts.org[/url]', 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('href="https://www.powerscripts.org"', $output);
        $this->assertStringContainsString('>www.powerscripts.org</a>', $output);
    }

    #[Test]
    public function quotedUrlParameterIsAccepted(): void
    {
        $output = TextFormatter::formatPost('[url="https://www.powerscripts.org"]PowerScripts[/url]', 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('href="https://www.powerscripts.org"', $output);
        $this->assertStringContainsString('>PowerScripts</a>', $output);
    }

    #[Test]
    public function autoLinkKeepsQueryStringAndStopsAtQuote(): void
    {
        $output = TextFormatter::formatPost('Siehe "https://example.org/?a=1&b=2".', 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('href="https://example.org/?a=1&amp;b=2"', $output);
        $this->assertStringContainsString('</a>&quot;.', $output);
    }

    #[Test]
    public function smiliesDoNotBreakEntities(): void
    {
        $output = TextFormatter::formatPost("echo('Hallo');", 'OFF', 'ON', 'OFF');

        $this->assertSame('echo(&apos;Hallo&apos;);', $output);
    }

    #[Test]
    public function smiliesAreNotInsertedIntoLinks(): void
    {
        $output = TextFormatter::formatPost('office@example.org :o', 'ON', 'ON', 'OFF');

        $this->assertStringContainsString('href="mailto:office@example.org"', $output);
        $this->assertStringContainsString('<img src="images/redface.gif"', $output);
    }

    #[Test]
    public function quoteLabelIsLocalizedAndEscaped(): void
    {
        $output = TextFormatter::formatPost('[quote]x[/quote]', 'ON', 'OFF', 'OFF', ['quote' => 'Zitat <1>:']);

        $this->assertStringContainsString('<small>Zitat &lt;1&gt;:</small>', $output);
    }

    #[Test]
    public function sanitizeUrlAcceptsOnlyHttpAndHttps(): void
    {
        $this->assertSame('https://example.org/a?b=1', TextFormatter::sanitizeUrl(' https://example.org/a?b=1 '));
        $this->assertSame('HTTP://example.org', TextFormatter::sanitizeUrl('HTTP://example.org'));
        $this->assertSame('https://www.example.org', TextFormatter::sanitizeUrl('www.example.org'));
        $this->assertNull(TextFormatter::sanitizeUrl('javascript:alert(1)'));
        $this->assertNull(TextFormatter::sanitizeUrl('data:text/html,x'));
        $this->assertNull(TextFormatter::sanitizeUrl('//evil.example'));
        $this->assertNull(TextFormatter::sanitizeUrl('https://'));
        $this->assertNull(TextFormatter::sanitizeUrl("https://example.org/\nx"));
        $this->assertNull(TextFormatter::sanitizeUrl(''));
    }

    /**
     * Parst die Ausgabe wie ein Browser und prüft alle Elemente/Attribute.
     */
    private function assertSafeHtml(string $output, string $context): void
    {
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body><div id="ppb-root">' . $output . '</div></body></html>',
            LIBXML_NOERROR
        );
        $root = $document->getElementById('ppb-root');
        $this->assertNotNull($root, $context);

        $allowedElements = array_merge(TextFormatter::ALLOWED_HTML_TAGS, ['a', 'img']);
        foreach ($root->getElementsByTagName('*') as $element) {
            $name = strtolower($element->localName);
            $this->assertContains($name, $allowedElements, "$context: unerwartetes Element <$name> in: $output");
            $this->assertAttributesAreSafe($element, $name, $context, $output);
        }

        // Nichts darf aus dem Container ausbrechen (z. B. durch </div>)
        $body = $document->body;
        $this->assertNotNull($body, $context);
        $this->assertSame(1, $body->childElementCount, "$context: Ausgabe verlässt den Container: $output");
    }

    private function assertAttributesAreSafe(Element $element, string $name, string $context, string $output): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$name] ?? [];
        foreach ($element->attributes as $attribute) {
            $attrName = strtolower($attribute->name);
            $this->assertContains($attrName, $allowed, "$context: unerlaubtes Attribut $attrName an <$name> in: $output");
            $this->assertStringStartsNotWith('on', $attrName, "$context: Event-Handler in: $output");

            if ($attrName === 'href' || $attrName === 'src') {
                $value = trim($attribute->value);
                $isLocalSmilie = $name === 'img' && str_starts_with($value, 'images/');
                $this->assertTrue(
                    $isLocalSmilie || preg_match('~^(https?://[^\s]+|mailto:[^\s:]+)$~i', $value) === 1,
                    "$context: unsichere URL \"$value\" in: $output"
                );
            }
        }
    }
}
