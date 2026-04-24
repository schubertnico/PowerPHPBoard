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
