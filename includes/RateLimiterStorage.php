<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Rate Limiter Storage Interface
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

namespace PowerPHPBoard;

interface RateLimiterStorage
{
    /**
     * @return array{attempts:int, window_start:int, locked_until:int}
     */
    public function getState(string $action, string $identifier, int $now): array;

    /**
     * @param array{attempts:int, window_start:int, locked_until:int} $state
     */
    public function saveState(string $action, string $identifier, array $state): void;
}
