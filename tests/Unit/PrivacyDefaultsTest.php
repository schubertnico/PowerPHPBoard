<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regressionstest: „E-Mail-Adresse verbergen?“ war bei der Registrierung
 * und bei „Benutzer anlegen“ auf „nein“ voreingestellt. Privacy by Default:
 * sichtbar nur nach ausdrücklicher Wahl.
 */
final class PrivacyDefaultsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../functions.inc.php';
    }

    #[Test]
    public function emailAddressIsHiddenUnlessTheUserChoosesOtherwise(): void
    {
        $this->assertSame('YES', ppb_hide_email(''), 'Neues Formular: „ja“ ist vorausgewählt');
        $this->assertSame('YES', ppb_hide_email('YES'));
        $this->assertSame('NO', ppb_hide_email('NO'));
        $this->assertSame('YES', ppb_hide_email('no'), 'Nur der exakte Formularwert macht die Adresse sichtbar');
        $this->assertSame('YES', ppb_hide_email('<script>'));
    }
}
