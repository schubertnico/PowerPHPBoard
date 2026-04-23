<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Rate Limiter
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

namespace PowerPHPBoard;

final class RateLimiter
{
    /** @var callable(): int */
    private $nowFn;

    public function __construct(
        private RateLimiterStorage $storage,
        private int $maxAttempts = 10,
        private int $windowSeconds = 900,
        private int $lockSeconds = 900,
        ?callable $now = null,
    ) {
        $this->nowFn = $now ?? static fn (): int => time();
    }

    public function check(string $action, string $identifier): bool
    {
        $now = ($this->nowFn)();
        $state = $this->storage->getState($action, $identifier, $now);
        if ($state['locked_until'] > $now) {
            return false;
        }
        if ($now - $state['window_start'] > $this->windowSeconds) {
            return true;
        }
        return $state['attempts'] < $this->maxAttempts;
    }

    public function recordFailure(string $action, string $identifier): void
    {
        $now = ($this->nowFn)();
        $state = $this->storage->getState($action, $identifier, $now);
        if ($now - $state['window_start'] > $this->windowSeconds) {
            $state = ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0];
        }
        $state['attempts']++;
        if ($state['attempts'] >= $this->maxAttempts) {
            $state['locked_until'] = $now + $this->lockSeconds;
        }
        $this->storage->saveState($action, $identifier, $state);
    }

    public function recordSuccess(string $action, string $identifier): void
    {
        $now = ($this->nowFn)();
        $this->storage->saveState($action, $identifier, ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0]);
    }
}
