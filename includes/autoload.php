<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Autoloader für die Klassen unter includes/
 *
 * config.inc.php bindet die Kernklassen einzeln ein. Neuere Klassen
 * (z. B. Auth, BoardAccess, BoardUrl) lädt dieser Autoloader bei Bedarf,
 * damit die Konfigurationsdatei unverändert bleiben kann.
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'PowerPHPBoard\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if (preg_match('/^[A-Za-z0-9_]+$/', $relative) !== 1) {
        return;
    }
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
