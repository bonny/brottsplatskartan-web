<?php

namespace App;

/**
 * Single source of truth för Tier 1-städer. Alla slug-tester, slug→display-
 * översättningar och uppslag av kommun-kod / Wikidata-Q-id går genom denna
 * klass. Datan ligger i config/tier1-cities.php.
 */
class Tier1
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = config('tier1-cities', []);
        }
        return self::$cache;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    public static function isTier1(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    /**
     * Slug → display-form ("malmo" → "Malmö"). DB-fälten lagrar display-form,
     * så queries mot Tier 1 måste översätta först. Returnerar input
     * oförändrat om slugen inte är Tier 1, så helpers kan kallas oavsett.
     */
    public static function displayName(string $slug): string
    {
        return self::all()[$slug]['displayName'] ?? $slug;
    }

    /**
     * Returnerar full city-data för slug, eller null om inte Tier 1.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }
}
