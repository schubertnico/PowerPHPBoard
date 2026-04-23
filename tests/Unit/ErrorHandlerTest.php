<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PowerPHPBoard\ErrorHandler;
use ReflectionClass;

/**
 * Unit tests for ErrorHandler class
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ErrorHandlerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ppb_test_logs_' . uniqid();
        mkdir($this->logDir, 0755, true);
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/test';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);

        // Reset static state via reflection
        $ref = new ReflectionClass(ErrorHandler::class);
        $ref->getProperty('initialized')->setValue(null, false);
        $ref->getProperty('logPath')->setValue(null, '');
        $ref->getProperty('displayErrors')->setValue(null, false);
    }

    protected function tearDown(): void
    {
        $this->cleanupDir($this->logDir);
        parent::tearDown();
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanupDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function getLogPath(): string
    {
        return $this->logDir . DIRECTORY_SEPARATOR . 'php-error.log';
    }

    private function getSecurityLogPath(): string
    {
        return $this->logDir . DIRECTORY_SEPARATOR . 'security.log';
    }

    private function initHandler(bool $displayErrors = false): void
    {
        // Use reflection to set logPath without calling init() to avoid set_error_handler side effects
        $ref = new ReflectionClass(ErrorHandler::class);
        $ref->getProperty('logPath')->setValue(null, $this->getLogPath());
        $ref->getProperty('displayErrors')->setValue(null, $displayErrors);
        $ref->getProperty('initialized')->setValue(null, true);
    }

    #[Test]
    public function initSetsUpHandlers(): void
    {
        ErrorHandler::init($this->getLogPath(), false);

        // Verify init sets initialized flag
        $ref = new ReflectionClass(ErrorHandler::class);
        $this->assertTrue($ref->getProperty('initialized')->getValue(null));
    }

    #[Test]
    public function initPreventsDoubleInitialization(): void
    {
        ErrorHandler::init($this->getLogPath(), false);
        // Second call with different displayErrors should be ignored
        ErrorHandler::init($this->getLogPath(), true);

        $ref = new ReflectionClass(ErrorHandler::class);
        $this->assertFalse($ref->getProperty('displayErrors')->getValue(null));
    }

    #[Test]
    public function initUsesDefaultLogPath(): void
    {
        ErrorHandler::init('', false);

        $ref = new ReflectionClass(ErrorHandler::class);
        $logPath = $ref->getProperty('logPath')->getValue(null);
        $this->assertStringContainsString('logs', $logPath);
        $this->assertStringContainsString('php-error.log', $logPath);
    }

    #[Test]
    public function handleErrorLogsWarning(): void
    {
        $this->initHandler();
        error_reporting(E_ALL);

        $result = ErrorHandler::handleError(E_USER_WARNING, 'Test warning', '/test.php', 42);

        $this->assertTrue($result);
        $content = file_get_contents($this->getLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('Test warning', $content);
        $this->assertStringContainsString('/test.php', $content);
        $this->assertStringContainsString('42', $content);
    }

    #[Test]
    public function handleErrorLogsNotice(): void
    {
        $this->initHandler();
        error_reporting(E_ALL);

        ErrorHandler::handleError(E_USER_NOTICE, 'Test notice', '/file.php', 10);

        $content = file_get_contents($this->getLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('NOTICE', $content);
        $this->assertStringContainsString('Test notice', $content);
    }

    #[Test]
    public function handleErrorLogsDeprecated(): void
    {
        $this->initHandler();
        error_reporting(E_ALL);

        ErrorHandler::handleError(E_USER_DEPRECATED, 'Deprecated function', '/old.php', 5);

        $content = file_get_contents($this->getLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('DEPRECATED', $content);
        $this->assertStringContainsString('Deprecated function', $content);
    }

    #[Test]
    public function handleErrorLogsError(): void
    {
        $this->initHandler();
        error_reporting(E_ALL);

        ErrorHandler::handleError(E_USER_ERROR, 'Fatal user error', '/critical.php', 99);

        $content = file_get_contents($this->getLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('Fatal user error', $content);
    }

    #[Test]
    public function handleErrorReturnsFalseForSuppressedErrors(): void
    {
        $this->initHandler();
        // Suppress all errors
        $oldLevel = error_reporting(0);

        $result = ErrorHandler::handleError(E_USER_WARNING, 'Suppressed', '/test.php', 1);

        error_reporting($oldLevel);
        $this->assertFalse($result);
    }

    #[Test]
    public function handleExceptionLogsException(): void
    {
        $this->initHandler(false);

        ob_start();
        ErrorHandler::handleException(new \RuntimeException('Test exception'));
        $output = ob_get_clean();

        $content = file_get_contents($this->getLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('EXCEPTION', $content);
        $this->assertStringContainsString('Test exception', $content);
        $this->assertStringContainsString('Stack trace', $content);

        $this->assertStringContainsString('An error occurred', $output);
        $this->assertStringContainsString('We apologize', $output);
    }

    #[Test]
    public function handleExceptionDisplaysDetailsWhenEnabled(): void
    {
        $this->initHandler(true);

        ob_start();
        ErrorHandler::handleException(new \RuntimeException('Detailed error info'));
        $output = ob_get_clean();

        $this->assertStringContainsString('Detailed error info', $output);
    }

    #[Test]
    public function logSecurityEventWritesToSecurityLog(): void
    {
        $this->initHandler();

        ErrorHandler::logSecurityEvent('TEST_EVENT', ['key' => 'value']);

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('SECURITY', $content);
        $this->assertStringContainsString('TEST_EVENT', $content);
        $this->assertStringContainsString('User:guest', $content);
        $this->assertStringContainsString('IP:127.0.0.1', $content);
        $this->assertStringContainsString('"key":"value"', $content);
    }

    #[Test]
    public function logSecurityEventIncludesUserId(): void
    {
        $this->initHandler();
        $_SESSION['user_id'] = 42;

        ErrorHandler::logSecurityEvent('AUTH_EVENT');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('User:42', $content);
    }

    #[Test]
    public function logSecurityEventWithoutContext(): void
    {
        $this->initHandler();

        ErrorHandler::logSecurityEvent('SIMPLE_EVENT');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('SIMPLE_EVENT', $content);
        $this->assertStringNotContainsString('{', $content);
    }

    #[Test]
    public function logFailedLoginLogsEvent(): void
    {
        $this->initHandler();

        ErrorHandler::logFailedLogin('user@example.com', 'invalid_credentials');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('LOGIN_FAILED', $content);
        $this->assertStringContainsString('user@example.com', $content);
        $this->assertStringContainsString('invalid_credentials', $content);
    }

    #[Test]
    public function logSuccessfulLoginLogsEvent(): void
    {
        $this->initHandler();

        ErrorHandler::logSuccessfulLogin(5, 'admin@example.com');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('LOGIN_SUCCESS', $content);
        $this->assertStringContainsString('admin@example.com', $content);
    }

    #[Test]
    public function logCsrfFailureLogsEvent(): void
    {
        $this->initHandler();
        $_SERVER['REQUEST_URI'] = '/submit-form';

        ErrorHandler::logCsrfFailure('form_submit');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('CSRF_FAILURE', $content);
        $this->assertStringContainsString('form_submit', $content);
        $this->assertStringContainsString('/submit-form', $content);
    }

    #[Test]
    public function logPermissionDeniedLogsEvent(): void
    {
        $this->initHandler();
        $_SERVER['REQUEST_URI'] = '/admin';

        ErrorHandler::logPermissionDenied('admin_access', 7);

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('PERMISSION_DENIED', $content);
        $this->assertStringContainsString('admin_access', $content);
        $this->assertStringContainsString('/admin', $content);
    }

    #[Test]
    public function logSuspiciousActivityLogsEvent(): void
    {
        $this->initHandler();

        ErrorHandler::logSuspiciousActivity('Multiple failed attempts', ['count' => 10]);

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('SUSPICIOUS_ACTIVITY', $content);
        $this->assertStringContainsString('Multiple failed attempts', $content);
        $this->assertStringContainsString('"count":10', $content);
    }

    #[Test]
    public function logSuspiciousActivityWithoutExtraData(): void
    {
        $this->initHandler();

        ErrorHandler::logSuspiciousActivity('Odd behavior');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('SUSPICIOUS_ACTIVITY', $content);
        $this->assertStringContainsString('Odd behavior', $content);
    }

    #[Test]
    public function getClientIpUsesXForwardedFor(): void
    {
        $this->initHandler();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 70.41.3.18';

        ErrorHandler::logSecurityEvent('IP_TEST');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('IP:203.0.113.50', $content);
    }

    #[Test]
    public function getClientIpUsesXRealIp(): void
    {
        $this->initHandler();
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.25';

        ErrorHandler::logSecurityEvent('IP_TEST');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('IP:198.51.100.25', $content);
    }

    #[Test]
    public function getClientIpUsesClientIp(): void
    {
        $this->initHandler();
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['HTTP_CLIENT_IP'] = '198.51.100.99';

        ErrorHandler::logSecurityEvent('IP_TEST');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('IP:198.51.100.99', $content);
    }

    #[Test]
    public function getClientIpFallsBackToDefault(): void
    {
        $this->initHandler();
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['REMOTE_ADDR']);

        ErrorHandler::logSecurityEvent('IP_TEST');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('IP:0.0.0.0', $content);
    }

    #[Test]
    public function getClientIpIgnoresInvalidIp(): void
    {
        $this->initHandler();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        ErrorHandler::logSecurityEvent('IP_TEST');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('IP:10.0.0.1', $content);
    }

    #[Test]
    public function writeLogCreatesDirectoryIfNotExists(): void
    {
        $nestedPath = $this->logDir . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'php-error.log';

        $ref = new ReflectionClass(ErrorHandler::class);
        $ref->getProperty('logPath')->setValue(null, $nestedPath);
        $ref->getProperty('initialized')->setValue(null, true);
        error_reporting(E_ALL);

        ErrorHandler::handleError(E_USER_WARNING, 'test', '/test.php', 1);

        $this->assertFileExists($nestedPath);
    }

    #[Test]
    public function handleShutdownDoesNothingWithoutFatalError(): void
    {
        $this->initHandler();

        ErrorHandler::handleShutdown();

        $this->assertFileDoesNotExist($this->getLogPath());
    }

    #[Test]
    public function logFailedLoginWithDefaultReason(): void
    {
        $this->initHandler();

        ErrorHandler::logFailedLogin('test@test.com');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('invalid_credentials', $content);
    }

    #[Test]
    public function logPermissionDeniedWithDefaultUserId(): void
    {
        $this->initHandler();

        ErrorHandler::logPermissionDenied('delete_post');

        $content = file_get_contents($this->getSecurityLogPath());
        $this->assertIsString($content);
        $this->assertStringContainsString('PERMISSION_DENIED', $content);
        $this->assertStringContainsString('"user_id":0', $content);
    }
}
