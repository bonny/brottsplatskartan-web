<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCrimeEventIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('crime_events', function (Blueprint $table) {
            $table->index('geocoded');
            $table->index('scanned_for_locations');
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('administrative_area_level_1');
            $table->index('administrative_area_level_2');
            // $table->index('parsed_title_location'); // Gives error
            $table->index('parsed_date');
            $table->index('location_lng');
            $table->index('location_lat');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
