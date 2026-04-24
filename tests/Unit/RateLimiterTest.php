<?php

declare(strict_types=1);

namespace PowerPHPBoard\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PowerPHPBoard\RateLimiter;
use PowerPHPBoard\RateLimiterStorage;

final class InMemoryRateLimitStorage implements RateLimiterStorage
{
    /** @var array<string, array{attempts:int, window_start:int, locked_until:int}> */
    private array $data = [];

    public function getState(string $action, string $identifier, int $now): array
    {
        $key = $action . '|' . $identifier;
        return $this->data[$key] ?? ['attempts' => 0, 'window_start' => $now, 'locked_until' => 0];
    }

    public function saveState(string $action, string $identifier, array $state): void
    {
        $this->data[$action . '|' . $identifier] = $state;
    }
}

final class RateLimiterTest extends TestCase
{
    public function testAllowsWithinLimit(): void
    {
        $storage = new InMemoryRateLimitStorage();
        $rl = new RateLimiter($storage, maxAttempts: 5, windowSeconds: 60, lockSeconds: 300);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($rl->check('login', 'ip:1.2.3.4'), "Attempt $i should pass");
            $rl->recordFailure('login', 'ip:1.2.3.4');
        }
        $this->assertFalse($rl->check('login', 'ip:1.2.3.4'), 'Attempt 6 must be blocked');
    }

    public function testResetOnSuccess(): void
    {
        $storage = new InMemoryRateLimitStorage();
        $rl = new RateLimiter($storage, maxAttempts: 3, windowSeconds: 60, lockSeconds: 300);

        $rl->recordFailure('login', 'u');
        $rl->recordFailure('login', 'u');
        $rl->recordSuccess('login', 'u');
        $this->assertTrue($rl->check('login', 'u'));
    }

    public function testWindowExpirationResetsCounter(): void
    {
        $storage = new InMemoryRateLimitStorage();
        $rl = new RateLimiter($storage, maxAttempts: 3, windowSeconds: 1, lockSeconds: 10, now: fn (): int => 1000);
        $rl->recordFailure('login', 'u');
        $rl->recordFailure('login', 'u');
        $rl2 = new RateLimiter($storage, maxAttempts: 3, windowSeconds: 1, lockSeconds: 10, now: fn (): int => 1002);
        $this->assertTrue($rl2->check('login', 'u'));
    }

    public function testLockOutlastsWindow(): void
    {
        $storage = new InMemoryRateLimitStorage();
        $rl = new RateLimiter($storage, maxAttempts: 2, windowSeconds: 1, lockSeconds: 60, now: fn (): int => 1000);
        $rl->recordFailure('login', 'u');
        $rl->recordFailure('login', 'u');
        $rl2 = new RateLimiter($storage, maxAttempts: 2, windowSeconds: 1, lockSeconds: 60, now: fn (): int => 1030);
        $this->assertFalse($rl2->check('login', 'u'), 'Lock muss window ueberdauern');
    }
}
