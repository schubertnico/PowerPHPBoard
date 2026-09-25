<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Configuration
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
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

use PowerPHPBoard\Installer\LocalConfig;

require_once __DIR__ . '/includes/Installer/LocalConfig.php';

// Zugangsdaten – Rangfolge (höchste zuerst):
//   1. config.local.php (vom Web-Installer unter install/ angelegt, nicht versioniert)
//   2. Umgebungsvariablen PPB_DB_* und PPB_MAIL_* (Docker, SetEnv, PHP-FPM)
//   3. Vorgaben aus LocalConfig::DEFAULT_MYSQL und LocalConfig::DEFAULT_MAIL
// Zugangsdaten bitte nicht hier eintragen, sondern in config.local.php –
// diese Datei wird bei jedem Update überschrieben.
$mysql = [
    'server'   => getenv('PPB_DB_HOST') ?: LocalConfig::DEFAULT_MYSQL['server'],
    'port'     => (int) (getenv('PPB_DB_PORT') ?: LocalConfig::DEFAULT_MYSQL['port']),
    'user'     => getenv('PPB_DB_USER') ?: LocalConfig::DEFAULT_MYSQL['user'],
    'password' => getenv('PPB_DB_PASS') ?: LocalConfig::DEFAULT_MYSQL['password'],
    'database' => getenv('PPB_DB_NAME') ?: LocalConfig::DEFAULT_MYSQL['database'],
];

// Mail configuration (Mailpit for dev, real SMTP in production)
$mail = [
    'host' => getenv('PPB_MAIL_HOST') ?: LocalConfig::DEFAULT_MAIL['host'],
    'port' => (int) (getenv('PPB_MAIL_PORT') ?: LocalConfig::DEFAULT_MAIL['port']),
    'from' => getenv('PPB_MAIL_FROM') ?: LocalConfig::DEFAULT_MAIL['from'],
];

if (is_file(__DIR__ . '/' . LocalConfig::FILENAME)) {
    ['mysql' => $mysql, 'mail' => $mail] = LocalConfig::apply(
        $mysql,
        $mail,
        require __DIR__ . '/' . LocalConfig::FILENAME
    );
}

// Application settings
define('PPB_VERSION', '2.3.0');
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
require_once __DIR__ . '/includes/Validator.php';
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/includes/RateLimiterStorage.php';
require_once __DIR__ . '/includes/RateLimiter.php';
require_once __DIR__ . '/includes/DatabaseRateLimitStorage.php';

// Initialize error handling
PowerPHPBoard\ErrorHandler::init(
    __DIR__ . '/logs/php-error.log',
    PPB_DEBUG
);
