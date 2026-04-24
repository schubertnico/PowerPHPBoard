<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\TextFormatter;

/**
 * Unit tests for TextFormatter class
 */
class TextFormatterTest extends TestCase
{
    #[Test]
    public function bbCodeBold(): void
    {
        $input = '[b]bold text[/b]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('<b>bold text</b>', $output);
    }

    #[Test]
    public function bbCodeItalic(): void
    {
        $input = '[i]italic text[/i]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('<i>italic text</i>', $output);
    }

    #[Test]
    public function bbCodeUnderline(): void
    {
        $input = '[u]underlined text[/u]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('<u>underlined text</u>', $output);
    }

    #[Test]
    public function bbCodeStrikethrough(): void
    {
        $input = '[s]strikethrough[/s]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('<s>strikethrough</s>', $output);
    }

    #[Test]
    public function bbCodeUrl(): void
    {
        $input = '[url]https://example.com[/url]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('href="https://example.com"', $output);
        $this->assertStringContainsString('target="_blank"', $output);
        $this->assertStringContainsString('rel="noopener noreferrer"', $output);
    }

    #[Test]
    public function bbCodeUrlWithText(): void
    {
        $input = '[url=https://example.com]Click here[/url]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('href="https://example.com"', $output);
        $this->assertStringContainsString('>Click here</a>', $output);
    }

    #[Test]
    public function bbCodeQuote(): void
    {
        $input = '[quote]Quoted text[/quote]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('<blockquote', $output);
        $this->assertStringContainsString('Quoted text', $output);
        $this->assertStringContainsString('</blockquote>', $output);
    }

    #[Test]
    public function bbCodeCode(): void
    {
        $input = '[code]echo "hello";[/code]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('<pre', $output);
        $this->assertStringContainsString('</pre>', $output);
    }

    #[Test]
    public function bbCodeImage(): void
    {
        $input = '[img]https://example.com/image.jpg[/img]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('<img src="https://example.com/image.jpg"', $output);
    }

    #[Test]
    public function bbCodeCaseInsensitive(): void
    {
        $input = '[B]BOLD[/B] [i]italic[/I] [U]underline[/u]';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('<b>BOLD</b>', $output);
        $this->assertStringContainsString('<i>italic</i>', $output);
        $this->assertStringContainsString('<u>underline</u>', $output);
    }

    #[Test]
    public function smiliesReplacement(): void
    {
        $input = 'Hello :) World';
        $output = TextFormatter::formatPost($input, 'OFF', 'ON', 'OFF');

        $this->assertStringContainsString('<img src="images/smile.gif"', $output);
        // :) may still appear in alt attribute, so check that img tag is present
        $this->assertMatchesRegularExpression('/<img[^>]+smile\.gif/', $output);
    }

    #[Test]
    public function smiliesWink(): void
    {
        $input = 'Winking ;)';
        $output = TextFormatter::formatPost($input, 'OFF', 'ON', 'OFF');

        $this->assertStringContainsString('<img src="images/wink.gif"', $output);
    }

    #[Test]
    public function smiliesBigGrin(): void
    {
        $input = 'Happy :D';
        $output = TextFormatter::formatPost($input, 'OFF', 'ON', 'OFF');

        $this->assertStringContainsString('<img src="images/biggrin.gif"', $output);
    }

    #[Test]
    public function smiliesSad(): void
    {
        $input = 'Sad :(';
        $output = TextFormatter::formatPost($input, 'OFF', 'ON', 'OFF');

        $this->assertStringContainsString('<img src="images/frown.gif"', $output);
    }

    #[Test]
    public function smiliesMad(): void
    {
        $input = 'Angry :mad:';
        $output = TextFormatter::formatPost($input, 'OFF', 'ON', 'OFF');

        $this->assertStringContainsString('<img src="images/mad.gif"', $output);
    }

    #[Test]
    public function smiliesDisabled(): void
    {
        $input = 'Hello :) World';
        $output = TextFormatter::formatPost($input, 'OFF', 'OFF', 'OFF');

        $this->assertStringContainsString(':)', $output);
        $this->assertStringNotContainsString('<img', $output);
    }

    #[Test]
    public function htmlEscapedWhenDisabled(): void
    {
        $input = '<script>alert("XSS")</script>';
        $output = TextFormatter::formatPost($input, 'OFF', 'OFF', 'OFF');

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    #[Test]
    public function htmlAllowedWhenEnabled(): void
    {
        $input = '<b>Bold HTML</b>';
        $output = TextFormatter::formatPost($input, 'OFF', 'OFF', 'ON');

        $this->assertStringContainsString('<b>Bold HTML</b>', $output);
    }

    #[Test]
    public function newlinesConvertedToBr(): void
    {
        $input = "Line 1\nLine 2";
        $output = TextFormatter::formatPost($input, 'OFF', 'OFF', 'OFF');

        $this->assertStringContainsString('<br>', $output);
    }

    #[Test]
    public function stripBBCodeRemovesTags(): void
    {
        $input = '[b]Bold[/b] and [i]Italic[/i] text';
        $output = TextFormatter::stripBBCode($input);

        $this->assertSame('Bold and Italic text', $output);
    }

    #[Test]
    public function stripBBCodeRemovesUrlTags(): void
    {
        $input = '[url=http://example.com]Link[/url]';
        $output = TextFormatter::stripBBCode($input);

        $this->assertSame('Link', $output);
    }

    #[Test]
    public function getSmiliesReturnsArray(): void
    {
        $smilies = TextFormatter::getSmilies();

        $this->assertIsArray($smilies);
        $this->assertNotEmpty($smilies);
        $this->assertArrayHasKey(':)', $smilies);
        $this->assertArrayHasKey('file', $smilies[':)']);
        $this->assertArrayHasKey('width', $smilies[':)']);
        $this->assertArrayHasKey('height', $smilies[':)']);
    }

    #[Test]
    public function autoLinkEmail(): void
    {
        $input = 'Contact us at test@example.com for help';
        $output = TextFormatter::formatPost($input, 'ON', 'OFF', 'OFF');

        $this->assertStringContainsString('href="mailto:test@example.com"', $output);
    }

    #[Test]
    public function combinedBBCodeAndSmilies(): void
    {
        $input = '[b]Hello[/b] :) [i]World[/i]';
        $output = TextFormatter::formatPost($input, 'ON', 'ON', 'OFF');

        $this->assertStringContainsString('<b>Hello</b>', $output);
        $this->assertStringContainsString('<i>World</i>', $output);
        $this->assertStringContainsString('<img src="images/smile.gif"', $output);
    }
}
