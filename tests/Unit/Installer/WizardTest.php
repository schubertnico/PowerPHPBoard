<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit\Installer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\Installer\Wizard;

final class WizardTest extends TestCase
{
    public function testFreshWizardStartsWithRequirements(): void
    {
        $wizard = Wizard::fromSession(null);

        $this->assertSame(0, $wizard->completed());
        $this->assertSame(Wizard::STEP_REQUIREMENTS, $wizard->allowedStep(Wizard::STEP_FINISH));
        $this->assertTrue($wizard->canEnter(Wizard::STEP_REQUIREMENTS));
        $this->assertFalse($wizard->canEnter(Wizard::STEP_DATABASE));
        $this->assertNull($wizard->done());
    }

    public function testStepsUnlockOneAfterAnother(): void
    {
        $wizard = Wizard::fromSession(null);

        $wizard->completeRequirements();
        $this->assertSame(Wizard::STEP_DATABASE, $wizard->allowedStep(Wizard::STEP_FINISH));

        $wizard->storeDatabase($this->database());
        $this->assertSame(Wizard::STEP_FORUM, $wizard->allowedStep(Wizard::STEP_FINISH));

        $wizard->storeForum($this->forum());
        $this->assertSame(Wizard::STEP_ADMIN, $wizard->allowedStep(Wizard::STEP_FINISH));

        $wizard->storeAdmin('Chef', 'chef@example.com', '$argon2id$hash');
        $this->assertSame(Wizard::STEP_FINISH, $wizard->allowedStep(99));
        $this->assertSame(Wizard::STEP_ADMIN, $wizard->completed());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function requestedSteps(): array
    {
        return [
            'negativ' => [-3, Wizard::STEP_REQUIREMENTS],
            'null' => [0, Wizard::STEP_REQUIREMENTS],
            'erlaubt' => [2, 2],
            'zu weit' => [4, 3],
        ];
    }

    #[DataProvider('requestedSteps')]
    public function testAllowedStepIsClamped(int $requested, int $expected): void
    {
        $wizard = Wizard::fromSession(null);
        $wizard->completeRequirements();
        $wizard->storeDatabase($this->database());

        $this->assertSame($expected, $wizard->allowedStep($requested));
    }

    public function testGoingBackKeepsLaterSteps(): void
    {
        $wizard = $this->completeWizard();

        $wizard->storeDatabase(['server' => 'neu'] + $this->database());

        $this->assertSame(Wizard::STEP_ADMIN, $wizard->completed());
        $this->assertSame('neu', $wizard->database()['server'] ?? null);
    }

    public function testSessionRoundTrip(): void
    {
        $wizard = $this->completeWizard();

        $restored = Wizard::fromSession($wizard->toSession());

        $this->assertSame($wizard->toSession(), $restored->toSession());
        $this->assertSame(Wizard::STEP_ADMIN, $restored->completed());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function brokenSessions(): array
    {
        return [
            'Zeichenkette' => ['kaputt'],
            'Zahl' => [5],
            'completed als Text' => [['completed' => '4']],
            'completed zu groß' => [['completed' => 99]],
            'Datenbank ohne Port' => [['completed' => 4, 'database' => ['server' => 'x', 'user' => 'u', 'password' => 'p', 'database' => 'd']]],
            'Admin ohne Hash' => [['completed' => 4, 'admin' => ['username' => 'a', 'email' => 'b']]],
        ];
    }

    #[DataProvider('brokenSessions')]
    public function testBrokenSessionDataNeverSkipsSteps(mixed $data): void
    {
        $wizard = Wizard::fromSession($data);

        $this->assertLessThanOrEqual(Wizard::STEP_REQUIREMENTS, $wizard->completed());
        $this->assertLessThanOrEqual(Wizard::STEP_DATABASE, $wizard->allowedStep(Wizard::STEP_FINISH));
        $this->assertNull($wizard->done());
    }

    public function testMissingAdminDataReducesProgress(): void
    {
        $session = $this->completeWizard()->toSession();
        $session['admin'] = null;

        $wizard = Wizard::fromSession($session);

        $this->assertSame(Wizard::STEP_FORUM, $wizard->completed());
        $this->assertFalse($wizard->canEnter(Wizard::STEP_FINISH));
    }

    public function testFinishDropsAllCredentials(): void
    {
        $wizard = $this->completeWizard();

        $wizard->finish(true, "<?php return ['password' => 'geheim'];");
        $session = $wizard->toSession();

        $this->assertNull($session['database']);
        $this->assertNull($session['admin']);
        $this->assertNull($session['forum']);
        $this->assertSame([
            'config_written' => true,
            'config_source' => '',
            'admin_username' => 'Chef',
            'admin_email' => 'chef@example.com',
            'board_url' => 'https://example.com',
        ], $session['done']);
        $this->assertStringNotContainsString('geheim', serialize($session));
        $this->assertStringNotContainsString('argon2id', serialize($session));
    }

    public function testSmtpCredentialsSurviveTheSessionAndAreDroppedOnFinish(): void
    {
        $forum = $this->forum();
        $forum['mail'] = [
            'host' => 'smtp.example.com',
            'port' => 587,
            'from' => 'forum@example.com',
            'user' => 'forum@example.com',
            'password' => 'smtp-geheim',
            'encryption' => 'starttls',
        ];
        $wizard = $this->completeWizard();
        $wizard->storeForum($forum);

        $restored = Wizard::fromSession($wizard->toSession());
        $this->assertSame($forum['mail'], $restored->forum()['mail'] ?? null);

        $restored->finish(true, '');
        $this->assertStringNotContainsString('smtp-geheim', serialize($restored->toSession()));
    }

    public function testSessionMailFromBeforeSmtpLoginGetsDefaults(): void
    {
        $session = $this->completeWizard()->toSession();
        $session['forum'] = $this->forum();
        $session['forum']['mail'] = ['host' => 'localhost', 'port' => 25, 'from' => 'a@example.com'];

        $wizard = Wizard::fromSession($session);

        $this->assertSame(
            ['host' => 'localhost', 'port' => 25, 'from' => 'a@example.com', 'user' => '', 'password' => '', 'encryption' => 'none'],
            $wizard->forum()['mail'] ?? null
        );
    }

    public function testSessionMailWithWrongTypeIsDiscarded(): void
    {
        $session = $this->completeWizard()->toSession();
        $session['forum'] = $this->forum();
        $session['forum']['mail'] = ['host' => 'localhost', 'port' => 25, 'from' => 'a@example.com', 'password' => 123];

        $this->assertNull(Wizard::fromSession($session)->forum()['mail'] ?? null);
    }

    public function testFinishKeepsConfigSourceOnlyForManualSetup(): void
    {
        $wizard = $this->completeWizard();
        $wizard->finish(false, '<?php return [];');

        $restored = Wizard::fromSession($wizard->toSession());
        $this->assertSame('<?php return [];', $restored->done()['config_source'] ?? null);

        $restored->forgetConfigSource();
        $this->assertSame('', $restored->done()['config_source'] ?? null);
        $this->assertFalse($restored->done()['config_written'] ?? true);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function servers(): array
    {
        return [
            'Wurzelverzeichnis' => [['HTTP_HOST' => 'forum.example.com', 'SCRIPT_NAME' => '/install/index.php'], 'http://forum.example.com'],
            'Unterverzeichnis' => [['HTTP_HOST' => 'example.com', 'SCRIPT_NAME' => '/forum/install/index.php', 'HTTPS' => 'on'], 'https://example.com/forum'],
            'Port' => [['HTTP_HOST' => 'localhost:8219', 'SCRIPT_NAME' => '/install/index.php'], 'http://localhost:8219'],
            'IPv6' => [['HTTP_HOST' => '[::1]:8080', 'SCRIPT_NAME' => '/install/index.php'], 'http://[::1]:8080'],
            'Host mit Schadcode' => [['HTTP_HOST' => 'evil.com"><script>', 'SCRIPT_NAME' => '/install/index.php'], ''],
            'Host fehlt' => [['SCRIPT_NAME' => '/install/index.php'], ''],
            'Pfad mit Anführungszeichen' => [['HTTP_HOST' => 'example.com', 'SCRIPT_NAME' => '/a"b/install/index.php'], 'http://example.com'],
        ];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('servers')]
    public function testSuggestBoardUrl(array $server, string $expected): void
    {
        $this->assertSame($expected, Wizard::suggestBoardUrl($server));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function senders(): array
    {
        return [
            'Domain' => ['https://forum.example.com/x', 'noreply@forum.example.com'],
            'www entfernt' => ['https://www.example.com', 'noreply@example.com'],
            'localhost ist keine gültige Adresse' => ['http://localhost:8219', ''],
            'leer' => ['', ''],
        ];
    }

    #[DataProvider('senders')]
    public function testSuggestSender(string $boardUrl, string $expected): void
    {
        $this->assertSame($expected, Wizard::suggestSender($boardUrl));
    }

    public function testActionsMapToSteps(): void
    {
        $this->assertSame(array_keys(Wizard::STEPS), array_values(Wizard::ACTIONS));
    }

    private function completeWizard(): Wizard
    {
        $wizard = Wizard::fromSession(null);
        $wizard->completeRequirements();
        $wizard->storeDatabase($this->database());
        $wizard->storeForum($this->forum());
        $wizard->storeAdmin('Chef', 'chef@example.com', '$argon2id$v=19$hash');

        return $wizard;
    }

    /**
     * @return array{server: string, port: int, user: string, password: string, database: string}
     */
    private function database(): array
    {
        return ['server' => 'db', 'port' => 3306, 'user' => 'forum', 'password' => 'geheim', 'database' => 'forum'];
    }

    /**
     * @return array{boardtitle: string, boardurl: string, adminemail: string, language: string, mail: array{host: string, port: int, from: string, user: string, password: string, encryption: string}|null}
     */
    private function forum(): array
    {
        return [
            'boardtitle' => 'Forum',
            'boardurl' => 'https://example.com',
            'adminemail' => 'admin@example.com',
            'language' => 'Deutsch-Sie',
            'mail' => null,
        ];
    }
}
