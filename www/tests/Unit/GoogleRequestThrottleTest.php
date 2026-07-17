<?php

namespace Tests\Unit;

use App\Services\Serp\GoogleRequestThrottle;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use PHPUnit\Framework\TestCase;

class GoogleRequestThrottleTest extends TestCase
{
    public function test_it_waits_until_the_next_allowed_request_window(): void
    {
        $store = new class
        {
            /** @var array<string, int> */
            public array $values = [
                GoogleRequestThrottle::NEXT_ALLOWED_AT_CACHE_KEY => 1050,
            ];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }

            public function forever(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }
        };

        $cacheFactory = $this->createMock(CacheFactory::class);
        $cacheFactory->method('store')->willReturn($store);

        $slept = [];
        $throttle = new GoogleRequestThrottle(
            $cacheFactory,
            100,
            now: static fn (): int => 1000,
            sleep: static function (int $milliseconds) use (&$slept): void {
                $slept[] = $milliseconds;
            },
        );

        $throttle->throttle();

        $this->assertSame([50], $slept);
        $this->assertSame(1100, $store->values[GoogleRequestThrottle::NEXT_ALLOWED_AT_CACHE_KEY]);
    }

    public function test_zero_delay_disables_throttling(): void
    {
        $store = new class
        {
            /** @var array<string, int> */
            public array $values = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }

            public function forever(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }
        };

        $cacheFactory = $this->createMock(CacheFactory::class);
        $cacheFactory->method('store')->willReturn($store);

        $slept = [];
        $throttle = new GoogleRequestThrottle(
            $cacheFactory,
            0,
            now: static fn (): int => 1000,
            sleep: static function (int $milliseconds) use (&$slept): void {
                $slept[] = $milliseconds;
            },
        );

        $throttle->throttle();

        $this->assertSame([], $slept);
        $this->assertArrayNotHasKey(GoogleRequestThrottle::NEXT_ALLOWED_AT_CACHE_KEY, $store->values);
    }
}
