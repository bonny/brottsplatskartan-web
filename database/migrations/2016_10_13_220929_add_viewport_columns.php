<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddViewportColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('crime_events', function ($table) {

            $table->decimal('viewport_northeast_lat', 10, 7)->nullable()->default(null);
            $table->decimal('viewport_northeast_lng', 10, 7)->nullable()->default(null);

            $table->decimal('viewport_southwest_lat', 10, 7)->nullable()->default(null);
            $table->decimal('viewport_southwest_lng', 10, 7)->nullable()->default(null);

            $table->dropColumn('location_geometry_viewport');

            /*
            "northeast": {
                "lat": 66.340329,
                "lng": 21.6169479
            },
            "southwest": {
                "lat": 63.4054636,
                "lng": 14.25681
            }
            */

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
