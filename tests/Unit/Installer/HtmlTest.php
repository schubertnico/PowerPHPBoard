<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\Html;

final class HtmlTest extends TestCase
{
    public function testInputEscapesEverything(): void
    {
        $html = Html::input(
            'board_title',
            'Name <b>',
            '"><script>alert(1)</script>',
            ['board_title' => 'Fehler <i>'],
            ['data-x' => '"quoted"'],
            'Hilfe & mehr'
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<i>', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        $this->assertStringContainsString('data-x="&quot;quoted&quot;"', $html);
        $this->assertStringContainsString('Hilfe &amp; mehr', $html);
    }

    public function testInputHasMatchingIdNameAndLabel(): void
    {
        $html = Html::input('db_host', 'Server', 'localhost', [], ['required' => true, 'maxlength' => 255]);

        $this->assertStringContainsString('<label for="db_host"', $html);
        $this->assertStringContainsString('id="db_host" name="db_host"', $html);
        $this->assertStringContainsString(' required', $html);
        $this->assertStringContainsString('maxlength="255"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('aria-hidden="true">*</span>', $html);
    }

    public function testServerErrorMarksFieldInvalid(): void
    {
        $html = Html::input('db_port', 'Port', '0', ['db_port' => 'Ungültiger Port'], [], 'Standard: 3306', 'Hinweis');

        $this->assertStringContainsString('form-control is-invalid', $html);
        $this->assertStringContainsString('aria-describedby="db_port-help db_port-error"', $html);
        $this->assertStringContainsString('Ungültiger Port', $html);
        $this->assertStringNotContainsString('Hinweis', $html);
    }

    public function testClientHintIsUsedWithoutServerError(): void
    {
        $html = Html::input('db_port', 'Port', '3306', [], ['type' => 'number', 'disabled' => false], '', 'Hinweis');

        $this->assertStringNotContainsString('is-invalid', $html);
        $this->assertStringNotContainsString('aria-describedby', $html);
        $this->assertStringContainsString('<div id="db_port-error" class="invalid-feedback">Hinweis</div>', $html);
        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringNotContainsString('disabled', $html);
    }

    public function testSelectMarksSelectedOption(): void
    {
        $html = Html::select('board_language', 'Sprache', ['English' => 'English', 'Deutsch-Du' => 'Deutsch <Du>'], 'Deutsch-Du', []);

        $this->assertStringContainsString('<option value="Deutsch-Du" selected>Deutsch &lt;Du&gt;</option>', $html);
        $this->assertStringContainsString('<option value="English">English</option>', $html);
        $this->assertStringContainsString('id="board_language" name="board_language"', $html);
    }

    public function testAlert(): void
    {
        $this->assertSame('', Html::alert(''));

        $html = Html::alert('Fehler <x>', 'warning', 'my-id');
        $this->assertStringContainsString('id="my-id" class="alert alert-warning', $html);
        $this->assertStringContainsString('bi-exclamation-triangle-fill', $html);
        $this->assertStringContainsString('Fehler &lt;x&gt;', $html);
    }
}
