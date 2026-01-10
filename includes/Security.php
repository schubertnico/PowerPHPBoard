<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Security Helper Functions
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
 * Security helper functions for XSS prevention, password hashing, and input sanitization
 */
class Security
{
    /**
     * Escape output for HTML context (XSS prevention)
     */
    public static function escape(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Alias for escape() for shorter code
     */
    public static function e(?string $value): string
    {
        return self::escape($value);
    }

    /**
     * Escape for HTML attribute context
     */
    public static function escapeAttr(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Hash password using modern algorithm (Argon2id if available, bcrypt as fallback)
     */
    public static function hashPassword(string $password): string
    {
        // Use Argon2id if available (PHP 7.3+), otherwise bcrypt
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify password against hash
     * Supports both modern hashes and legacy base64 encoding for migration
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        // Check for legacy base64-encoded passwords (migration support)
        if (self::isLegacyHash($hash)) {
            $decoded = base64_decode($hash, true);
            return $decoded !== false && $decoded === $password;
        }

        // Modern password verification
        return password_verify($password, $hash);
    }

    /**
     * Check if password hash needs rehashing
     */
    public static function needsRehash(string $hash): bool
    {
        // Legacy base64 always needs rehash
        if (self::isLegacyHash($hash)) {
            return true;
        }

        // Check if modern hash needs upgrade
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_needs_rehash($hash, $algo);
    }

    /**
     * Check if hash is legacy base64 format
     */
    public static function isLegacyHash(string $hash): bool
    {
        // Modern hashes start with $2y$ (bcrypt) or $argon2 (argon2id)
        // and are at least 60 characters
        if (strlen($hash) >= 60 && (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'))) {
            return false;
        }

        // Check if it's valid base64
        $decoded = base64_decode($hash, true);
        return $decoded !== false && base64_encode($decoded) === $hash;
    }

    /**
     * Get integer from request safely
     */
    public static function getInt(string $key, string $method = 'GET', int $default = 0): int
    {
        $source = match (strtoupper($method)) {
            'POST' => $_POST,
            'REQUEST' => $_REQUEST,
            default => $_GET,
        };

        $value = $source[$key] ?? null;
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        return $filtered !== false ? $filtered : $default;
    }

    /**
     * Get string from request safely
     */
    public static function getString(string $key, string $method = 'GET', string $default = ''): string
    {
        $source = match (strtoupper($method)) {
            'POST' => $_POST,
            'REQUEST' => $_REQUEST,
            default => $_GET,
        };

        $value = $source[$key] ?? null;
        return $value !== null ? trim((string)$value) : $default;
    }

    /**
     * Validate email address
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate a random token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Get client IP address
     */
    public static function getClientIp(): string
    {
        // Check for forwarded IP (behind proxy)
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', (string) $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
