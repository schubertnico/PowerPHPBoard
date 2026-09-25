<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Board-URL für Links in E-Mails
 *
 * MIT License - Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

/**
 * Baut absolute Links (z. B. für Passwort-Reset-Mails) ausschließlich aus
 * der im Adminbereich konfigurierten Board-URL. Der Host-Header der Anfrage
 * wird bewusst nie verwendet: Er stammt vom Client und ließe sich fälschen
 * (Host-Header-Injection, Reset-Link auf fremde Domain).
 */
final class BoardUrl
{
    /**
     * Konfigurierte Board-URL ohne abschließenden Schrägstrich
     *
     * @param array<string, mixed> $settings Zeile aus ppb_config
     *
     * @return string|null null, wenn keine gültige http(s)-Adresse eingetragen ist
     */
    public static function base(array $settings): ?string
    {
        $configured = $settings['boardurl'] ?? '';
        if (!is_string($configured)) {
            return null;
        }

        $url = TextFormatter::sanitizeUrl($configured);
        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return rtrim($url, '/');
    }

    /**
     * Absoluten Link zu einer Seite des Boards bauen
     *
     * @param array<string, int|string> $query
     */
    public static function link(string $base, string $page, array $query = []): string
    {
        $link = rtrim($base, '/') . '/' . ltrim($page, '/');

        return $query === [] ? $link : $link . '?' . http_build_query($query);
    }
}
