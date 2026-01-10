<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - CSRF Protection
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
 * CSRF token generation and validation
 */
class CSRF
{
    private const string TOKEN_NAME = 'csrf_token';
    private const int TOKEN_LENGTH = 32;

    /**
     * Generate or retrieve CSRF token
     */
    public static function generateToken(): string
    {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Get hidden form field with CSRF token
     */
    public static function getTokenField(): string
    {
        $token = htmlspecialchars(self::generateToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . $token . '">';
    }

    /**
     * Get token name for manual form building
     */
    public static function getTokenName(): string
    {
        return self::TOKEN_NAME;
    }

    /**
     * Validate CSRF token
     */
    public static function validate(?string $token): bool
    {
        if (!isset($_SESSION[self::TOKEN_NAME]) || $token === null || $token === '') {
            return false;
        }
        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    /**
     * Validate token from POST request
     */
    public static function validateFromPost(): bool
    {
        $token = $_POST[self::TOKEN_NAME] ?? null;
        return self::validate($token);
    }

    /**
     * Validate or terminate script
     */
    public static function validateOrDie(?string $token = null): void
    {
        if ($token === null) {
            $token = $_POST[self::TOKEN_NAME] ?? null;
        }

        if (!self::validate($token)) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }

    /**
     * Regenerate token (call after successful form submission)
     */
    public static function regenerate(): void
    {
        unset($_SESSION[self::TOKEN_NAME]);
        self::generateToken();
    }
}
