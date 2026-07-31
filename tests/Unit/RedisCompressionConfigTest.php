<?php

namespace Tests\Unit;

use Redis;
use Tests\TestCase;

/**
 * Responscachen stod för ~78 % av Redis minne och tryckte förbrukningen
 * mot 3 GB-taket. Lösningen är phpredis inbyggda komprimering, satt i
 * config/database.php.
 *
 * Testet låser fast kombinationen, inte bara komprimeringen: ZSTD utan
 * pack_ignore_numbers får INCRBY att returnera false, vilket tyst slår
 * ut RateLimiter — och därmed `throttle:500,1` på API:t.
 */
class RedisCompressionConfigTest extends TestCase
{
    public function test_komprimering_ar_paslagen(): void
    {
        $this->assertSame(
            Redis::COMPRESSION_ZSTD,
            config('database.redis.options.compression'),
            'Responscachen måste komprimeras — utan det slår Redis i maxmemory-taket.'
        );
    }

    public function test_pack_ignore_numbers_skyddar_rate_limiting(): void
    {
        $this->assertTrue(
            config('database.redis.options.pack_ignore_numbers'),
            'Utan pack_ignore_numbers komprimeras även numeriska värden, '
            . 'och INCRBY returnerar false. RateLimiter slutar då räkna — tyst.'
        );
    }
}
