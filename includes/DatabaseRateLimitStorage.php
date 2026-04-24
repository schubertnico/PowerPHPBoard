<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Database Rate Limit Storage
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

final class DatabaseRateLimitStorage implements RateLimiterStorage
{
    public function __construct(private Database $db)
    {
    }

    public function getState(string $action, string $identifier, int $now): array
    {
        $row = $this->db->fetchOne(
            'SELECT attempts, window_start, locked_until FROM ppb_rate_limits WHERE action = ? AND identifier = ?',
            [$action, $identifier]
        );
        if ($row === null) {
            return ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0];
        }
        return [
            'attempts' => (int) $row['attempts'],
            'window_start' => (int) $row['window_start'],
            'locked_until' => (int) $row['locked_until'],
        ];
    }

    public function saveState(string $action, string $identifier, array $state): void
    {
        $this->db->query(
            'INSERT INTO ppb_rate_limits (action, identifier, attempts, window_start, locked_until) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), window_start = VALUES(window_start), locked_until = VALUES(locked_until)',
            [$action, $identifier, $state['attempts'], $state['window_start'], $state['locked_until']]
        );
    }
}
