<?php

declare(strict_types=1);

namespace App\Services\Serp;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;

final class GoogleRequestThrottle
{
    public const NEXT_ALLOWED_AT_CACHE_KEY = 'seo:serp:google:next_allowed_at_ms';

    private const LOCK_KEY = 'seo:serp:google:throttle_lock';

    private readonly Closure $now;

    private readonly Closure $sleep;

    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private readonly int $requestDelayMs,
        ?callable $now = null,
        ?callable $sleep = null,
    ) {
        $this->now = Closure::fromCallable($now ?? static fn (): int => (int) round(microtime(true) * 1000));
        $this->sleep = Closure::fromCallable($sleep ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        });
    }

    public function throttle(): void
    {
        if ($this->requestDelayMs <= 0) {
            return;
        }

        $store = $this->cacheFactory->store();

        if (method_exists($store, 'getStore') && $store->getStore() instanceof LockProvider) {
            $lockSeconds = max(1, (int) ceil($this->requestDelayMs / 1000) + 1);

            $store->lock(self::LOCK_KEY, $lockSeconds)->block($lockSeconds, function () use ($store): void {
                $this->waitForWindowAndReserveNextSlot($store);
            });

            return;
        }

        $this->waitForWindowAndReserveNextSlot($store);
    }

    private function waitForWindowAndReserveNextSlot(object $store): void
    {
        $now = $this->nowMs();
        $nextAllowedAtMs = (int) ($store->get(self::NEXT_ALLOWED_AT_CACHE_KEY, 0) ?? 0);
        $waitMs = $nextAllowedAtMs - $now;

        if ($waitMs > 0) {
            $this->sleepMs($waitMs);
        }

        $store->forever(self::NEXT_ALLOWED_AT_CACHE_KEY, $this->nowMs() + $this->requestDelayMs);
    }

    private function nowMs(): int
    {
        return ($this->now)();
    }

    private function sleepMs(int $milliseconds): void
    {
        ($this->sleep)($milliseconds);
    }
}
