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

    /**
     * Konfigurationen ovan räcker inte — phpredis måste också stödja
     * optionerna. Laravels PhpRedisConnector sätter bara
     * OPT_PACK_IGNORE_NUMBERS om konstanten finns (phpredis ≥ 6.0):
     * med en äldre phpredis i basimagen slås komprimeringen på medan
     * pack_ignore_numbers tyst hoppas över — exakt scenariot som dödar
     * rate limiting. Utan ZSTD i bygget kraschar dessutom
     * config/database.php redan vid inläsning.
     */
    public function test_phpredis_stodjer_optionerna(): void
    {
        $this->assertTrue(
            defined('Redis::COMPRESSION_ZSTD'),
            'phpredis är byggt utan ZSTD — config/database.php går inte att läsa in.'
        );

        $this->assertTrue(
            defined('Redis::OPT_PACK_IGNORE_NUMBERS'),
            'phpredis saknar OPT_PACK_IGNORE_NUMBERS (kräver ≥ 6.0). Laravel '
            . 'hoppar då tyst över optionen medan komprimeringen är på — '
            . 'INCRBY slutar fungera och rate limiting dör utan felmeddelande.'
        );
    }
}
