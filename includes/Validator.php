<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Input Validator
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
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

    /**
     * Normalisiert die optionale Homepage-Angabe aus Profil und Registrierung.
     *
     * Leere Eingaben (auch ein allein stehendes "https://") ergeben '', eine
     * Adresse ohne Schema wie "example.org" wird zu "https://example.org".
     * Nur http(s)-Adressen sind erlaubt; alles andere (javascript:, data:, …)
     * sowie zu lange Adressen ergeben null.
     *
     * @return string|null '' = keine Homepage, null = ungültig
     */
    public static function normalizeHomepage(string $homepage): ?string
    {
        $homepage = trim($homepage);
        if ($homepage === '' || in_array(strtolower($homepage), ['http://', 'https://'], true)) {
            return '';
        }
        if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $homepage) !== 1) {
            $homepage = 'https://' . $homepage;
        }

        $url = TextFormatter::sanitizeUrl($homepage);
        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return self::withinLength($url, self::HOMEPAGE_MAX) ? $url : null;
    }
}
