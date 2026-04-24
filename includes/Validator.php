<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Input Validator
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

namespace PowerPHPBoard;

final class Validator
{
    public const USERNAME_MIN = 2;
    public const USERNAME_MAX = 50;
    public const PASSWORD_MIN = 8;
    public const POST_MAX = 65000;
    public const BIOGRAPHY_MAX = 1000;
    public const SIGNATURE_MAX = 500;
    public const HOMEPAGE_MAX = 150;

    public static function isValidUsername(string $username): bool
    {
        if (!self::withinLength($username, self::USERNAME_MAX)) {
            return false;
        }
        if (mb_strlen($username) < self::USERNAME_MIN) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9._-]+$/', $username) === 1;
    }

    public static function withinLength(string $value, int $max): bool
    {
        return mb_strlen($value) <= $max;
    }

    public static function isStrongPassword(string $password): bool
    {
        return mb_strlen($password) >= self::PASSWORD_MIN;
    }
}
