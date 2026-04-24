<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap File
 *
 * This file is loaded before any tests run.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test constants
define('PPB_TEST_MODE', true);

// ErrorHandler mit einem tmp-basierten Log-Pfad initialisieren, damit
// Tests, die ueber Security-Events loggen (z. B. CSRFTest), keinen
// "Permission denied" auf "/security.log" (absoluter Root-Pfad) werfen.
$testLogDir = sys_get_temp_dir() . '/powerphpboard-tests-' . getmypid();
if (!is_dir($testLogDir)) {
    mkdir($testLogDir, 0o755, true);
}
\PowerPHPBoard\ErrorHandler::init($testLogDir . '/php-error.log', false);
