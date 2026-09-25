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

    /**
     * Regressionstest: Im Profil gab es „E-Mail senden“ nur bei verborgener
     * Adresse, im Beitragskopf den Briefumschlag nur bei sichtbarer. Jetzt
     * steht das Kontaktformular immer zur Verfügung, die Adresse selbst nur
     * bei „nicht verbergen“.
     */
    #[Test]
    public function profileAlwaysOffersTheContactFormButShowsTheAddressOnlyIfVisible(): void
    {
        $hidden = ppb_profile_email(['id' => 7, 'email' => 'anna@example.org', 'hideemail' => 'YES'], 1, 2);
        $this->assertStringContainsString('href="sendmail.php?userid=7&amp;catid=1&amp;boardid=2"', $hidden);
        $this->assertStringContainsString('id="profile-sendmail"', $hidden);
        $this->assertStringNotContainsString('anna@example.org', $hidden);
        $this->assertStringNotContainsString('mailto:', $hidden);

        $visible = ppb_profile_email(['id' => 7, 'email' => 'anna@example.org', 'hideemail' => 'NO'], 0, 0);
        $this->assertStringContainsString('href="mailto:anna@example.org"', $visible);
        $this->assertStringContainsString('href="sendmail.php?userid=7&amp;catid=0&amp;boardid=0"', $visible);

        $unknown = ppb_profile_email(['id' => 8, 'email' => 'bernd@example.org'], 0, 0);
        $this->assertStringNotContainsString('bernd@example.org', $unknown, 'Ohne Angabe bleibt die Adresse verborgen');
    }
}
