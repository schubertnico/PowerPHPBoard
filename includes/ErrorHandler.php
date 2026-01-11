<?php

declare(strict_types=1);

namespace PowerPHPBoard;

/**
 * Centralized error and exception handler with security event logging
 */
class ErrorHandler
{
    private static bool $initialized = false;
    private static string $logPath = '';
    private static bool $displayErrors = false;

    /**
     * Initialize error handling
     */
    public static function init(string $logPath = '', bool $displayErrors = false): void
    {
        if (self::$initialized) {
            return;
        }

        self::$logPath = $logPath ?: __DIR__ . '/../logs/php-error.log';
        self::$displayErrors = $displayErrors;

        // Set error handler
        set_error_handler([self::class, 'handleError']);

        // Set exception handler
        set_exception_handler([self::class, 'handleException']);

        // Register shutdown function for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$initialized = true;
    }

    /**
     * Handle PHP errors
     */
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0
    ): bool {
        // Don't handle errors suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = match ($errno) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'ERROR',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARNING',
            E_NOTICE, E_USER_NOTICE => 'NOTICE',
            E_STRICT => 'STRICT',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            default => 'UNKNOWN',
        };

        $message = sprintf(
            "[%s] %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $errorType,
            $errstr,
            $errfile,
            $errline
        );

        self::writeLog($message);

        // Don't execute PHP's internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException(\Throwable $e): void
    {
        $message = sprintf(
            "[%s] EXCEPTION: %s in %s on line %d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        self::writeLog($message);

        // Display user-friendly error page
        if (!headers_sent()) {
            http_response_code(500);
        }

        if (self::$displayErrors) {
            echo '<h1>An error occurred</h1>';
            echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</pre>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</pre>';
        } else {
            echo '<h1>An error occurred</h1>';
            echo '<p>We apologize for the inconvenience. Please try again later.</p>';
        }
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $message = sprintf(
                "[%s] FATAL: %s in %s on line %d",
                date('Y-m-d H:i:s'),
                $error['message'],
                $error['file'],
                $error['line']
            );

            self::writeLog($message);
        }
    }

    /**
     * Log a security event
     *
     * @param array<string, mixed> $context
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $ip = self::getClientIp();
        $userId = $_SESSION['user_id'] ?? 'guest';

        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $message = sprintf(
            "[%s] SECURITY [%s] User:%s IP:%s%s",
            date('Y-m-d H:i:s'),
            $event,
            $userId,
            $ip,
            $contextStr
        );

        self::writeLog($message, 'security');
    }

    /**
     * Log failed login attempt
     */
    public static function logFailedLogin(string $email, string $reason = 'invalid_credentials'): void
    {
        self::logSecurityEvent('LOGIN_FAILED', [
            'email' => $email,
            'reason' => $reason,
        ]);
    }

    /**
     * Log successful login
     */
    public static function logSuccessfulLogin(int $userId, string $email): void
    {
        self::logSecurityEvent('LOGIN_SUCCESS', [
            'user_id' => $userId,
            'email' => $email,
        ]);
    }

    /**
     * Log CSRF validation failure
     */
    public static function logCsrfFailure(string $action = ''): void
    {
        self::logSecurityEvent('CSRF_FAILURE', [
            'action' => $action,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
    }

    /**
     * Log permission denied
     */
    public static function logPermissionDenied(string $action, int $userId = 0): void
    {
        self::logSecurityEvent('PERMISSION_DENIED', [
            'action' => $action,
            'user_id' => $userId,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
    }

    /**
     * Log suspicious activity
     *
     * @param array<string, mixed> $data
     */
    public static function logSuspiciousActivity(string $description, array $data = []): void
    {
        self::logSecurityEvent('SUSPICIOUS_ACTIVITY', array_merge(
            ['description' => $description],
            $data
        ));
    }

    /**
     * Write to log file
     */
    private static function writeLog(string $message, string $type = 'error'): void
    {
        $logFile = match ($type) {
            'security' => dirname(self::$logPath) . '/security.log',
            default => self::$logPath,
        };

        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Append to log file
        file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get client IP address
     */
    private static function getClientIp(): string
    {
        // Check for proxy headers (be careful with these in production)
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can contain multiple IPs, take the first one
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
