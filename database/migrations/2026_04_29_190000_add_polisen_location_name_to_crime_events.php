<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crime_events', function (Blueprint $table) {
            // Polisens location.name — alltid län-nivå, används för
            // geocoding-disambiguering.
            $table->string('polisen_location_name', 80)->nullable()->after('polisen_gps_lng');
        });
    }

    public function down(): void
    {
        Schema::table('crime_events', function (Blueprint $table) {
            $table->dropColumn('polisen_location_name');
        });
    }
};
