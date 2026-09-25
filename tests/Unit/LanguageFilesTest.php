<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die drei Sprachdateien definieren dieselben Schlüssel, und die Oberfläche
 * bietet keine Einstellung an, die nichts bewirkt.
 */
final class LanguageFilesTest extends TestCase
{
    private const array FILES = ['english.inc.php', 'deutsch-sie.inc.php', 'deutsch-du.inc.php'];

    #[Test]
    public function allLanguageFilesDefineTheSameKeys(): void
    {
        $keys = [];
        foreach (self::FILES as $file) {
            $keys[$file] = self::keysOf(__DIR__ . '/../../' . $file);
        }

        $reference = $keys['english.inc.php'];
        $this->assertNotEmpty($reference);
        foreach ($keys as $file => $fileKeys) {
            $this->assertSame([], array_values(array_diff($reference, $fileKeys)), $file . ': fehlende Schlüssel');
            $this->assertSame([], array_values(array_diff($fileKeys, $reference)), $file . ': zusätzliche Schlüssel');
        }
        foreach (['boardclosedmodhint', 'threadclosedmodhint', 'noreplys'] as $key) {
            $this->assertContains($key, $reference);
        }
    }

    /**
     * Regressionstest: „Anmeldung merken?“ (ppb_users.logincookie) steuerte
     * nur, welcher Knopf nach der Registrierung erschien. Die Option ist aus
     * Formularen und Sprachdateien entfernt, die Spalte bleibt bestehen.
     */
    #[Test]
    public function rememberLoginOptionIsNoLongerOffered(): void
    {
        foreach (self::FILES as $file) {
            $this->assertNotContains('saveloginincookie', self::keysOf(__DIR__ . '/../../' . $file), $file);
        }
        foreach (['register.php', 'profile.php', 'admin/adduser.php', 'admin/edituser.php'] as $form) {
            $source = (string) file_get_contents(__DIR__ . '/../../' . $form);
            $this->assertStringNotContainsString('logincookie', $source, $form);
        }
    }

    /**
     * @return list<string> Schlüssel ohne Präfix "lang_"
     */
    private static function keysOf(string $path): array
    {
        $source = (string) file_get_contents($path);
        preg_match_all('/^\$lang_([A-Za-z0-9_]+)\s*=/m', $source, $matches);

        return $matches[1];
    }
}
