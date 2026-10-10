<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sparar mer metadata från Google-geokodningen och Polisens API (todo #109/#111).
 *
 * - `google_types`: Googles `types` för träffen (locality, sublocality, route
 *   …) — finare precisionsklass än `location_geometry_type`, som klumpar ihop
 *   ort och stadsdel som APPROXIMATE.
 * - `google_partial_match`: Google matchade inte hela frågan. Svag signal så
 *   länge frågan är en ihopslagen sträng av alla orter i texten (#109.1).
 * - `polisen_type`: API:ts `type`-fält, i stället för att tolka titeln.
 * - `polisen_raw`: hela API-objektet vid skapandet, så historiken kan köras om.
 *
 * Tabellen har ett index på en virtuell kolumn, så MariaDB kan inte lägga
 * till kolumner med INSTANT eller utan lås: hela tabellen byggs om och
 * skrivningar blockeras under tiden (läsningar går). Schema::table() kör en
 * ALTER per kolumn = fyra ombyggnader, därför en enda ALTER här.
 * JSON lagras som longtext; modellen castar till array.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE crime_events
            ADD COLUMN google_types LONGTEXT NULL,
            ADD COLUMN google_partial_match TINYINT(1) NULL,
            ADD COLUMN polisen_type VARCHAR(80) NULL,
            ADD COLUMN polisen_raw LONGTEXT NULL');
    }

    public function down(): void
    {
        Schema::table('crime_events', function (Blueprint $table) {
            $table->dropColumn(['google_types', 'google_partial_match', 'polisen_type', 'polisen_raw']);
        });
    }
};
