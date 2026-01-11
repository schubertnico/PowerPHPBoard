<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Configuration
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 */

// Database configuration - uses environment variables with fallbacks
$mysql = [
    'server'   => getenv('PPB_DB_HOST') ?: 'localhost',
    'user'     => getenv('PPB_DB_USER') ?: 'root',
    'password' => getenv('PPB_DB_PASS') ?: '',
    'database' => getenv('PPB_DB_NAME') ?: 'PowerPHPBoard_v2',
];

// Application settings
define('PPB_VERSION', '2.0.0');
define('PPB_SESSION_LIFETIME', 3600);
define('PPB_CSRF_ENABLED', true);
define('PPB_DEBUG', (bool)(getenv('PPB_DEBUG') ?: false));

// Include core classes
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Session.php';
require_once __DIR__ . '/includes/CSRF.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/TextFormatter.php';
require_once __DIR__ . '/includes/ErrorHandler.php';

// Initialize error handling
PowerPHPBoard\ErrorHandler::init(
    __DIR__ . '/logs/php-error.log',
    PPB_DEBUG
);
