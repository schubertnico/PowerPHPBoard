<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Secure Session Handler
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

namespace PowerPHPBoard;

/**
 * Secure session management
 * Replaces cookie-based password storage with proper session handling
 */
class Session
{
    private static bool $started = false;

    /**
     * Start secure session
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Only configure session if headers haven't been sent yet
        if (!headers_sent()) {
            // Configure session settings before starting
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Lax');

            // Only set secure cookie if using HTTPS
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', '1');
            }

            session_start();
        }
        self::$started = true;
    }

    /**
     * Regenerate session ID (use after login to prevent session fixation)
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Set session value
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Destroy session completely
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            $sessionName = session_name();
            if ($sessionName !== false) {
                setcookie(
                    $sessionName,
                    '',
                    ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly']]
                );
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    /**
     * Get current user ID
     */
    public static function getUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Login user (stores user ID in session)
     */
    public static function login(int $userId): void
    {
        self::regenerate();
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_time'] = time();
    }

    /**
     * Logout user
     */
    public static function logout(): void
    {
        self::destroy();
    }

    /**
     * Get all session data (for debugging)
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return $_SESSION ?? [];
    }
}
