<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crime_events', function (Blueprint $table) {
            // Polisens `location.name` — alltid län-nivå ("Stockholms län").
            // Skiljer sig från `parsed_title_location` som ibland är stad
            // (titeln slutar på antingen län eller ort beroende på event).
            // Används i geocoding-querysträngen för disambiguering.
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
