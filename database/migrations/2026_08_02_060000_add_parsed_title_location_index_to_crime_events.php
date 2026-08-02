<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prefixindex på crime_events.parsed_title_location.
 *
 * Why: platssidorna slår upp händelser på tre vägar —
 * parsed_title_location, administrative_area_level_2 och locations.name.
 * De två senare är indexerade, den första inte. En uppslagning på
 * parsed_title_location mättes till 6,8–8,6 s på prod (full scan av
 * 507 000 rader) och var ensam orsak till att indexeringströskeln fick
 * rullas tillbaka 2026-08-02. Se
 * docs/superpowers/specs/2026-08-02-plats-indexeringstroskel-design.md
 *
 * Kolumnen är TEXT, så den kräver prefixlängd. Uppmätt max är 20 tecken
 * och snitt 11,2 — 50 ger fyra gånger marginal utan att svälla indexet.
 * Schema::table()/->index() kan inte uttrycka prefixlängd, därav rå SQL.
 */
return new class extends Migration
{
    private const INDEXNAMN = 'crime_events_parsed_title_location_index';

    public function up(): void
    {
        if ($this->indexFinns()) {
            return;
        }

        DB::statement(
            'ALTER TABLE crime_events ADD INDEX ' . self::INDEXNAMN . ' (parsed_title_location(50))'
        );
    }

    public function down(): void
    {
        if (! $this->indexFinns()) {
            return;
        }

        DB::statement('ALTER TABLE crime_events DROP INDEX ' . self::INDEXNAMN);
    }

    private function indexFinns(): bool
    {
        $rader = DB::select(
            'SHOW INDEX FROM crime_events WHERE Key_name = ?',
            [self::INDEXNAMN]
        );

        return count($rader) > 0;
    }
};
