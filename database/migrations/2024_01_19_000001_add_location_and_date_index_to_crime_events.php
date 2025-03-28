<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationAndDateIndexToCrimeEvents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('crime_events', function (Blueprint $table) {
            $table->index(
                ['location_lat', 'location_lng', 'parsed_date'],
                'idx_crime_events_location_date'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('crime_events', function (Blueprint $table) {
            $table->dropIndex('idx_crime_events_location_date');
        });
    }
}
