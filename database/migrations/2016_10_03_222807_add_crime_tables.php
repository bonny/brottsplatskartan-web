<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCrimeTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('crime_events', function ($table) {

            $table->dateTime('parsed_date')->nullable();
            $table->text('parsed_title_location')->nullable();
            $table->text('parsed_content_location')->nullable();
            $table->text('parsed_content')->nullable();
            $table->decimal('parsed_lng', 10, 7)->nullable();
            $table->decimal('parsed_lat', 10, 7)->nullable();

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
