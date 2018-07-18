<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCrimeViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crime_views', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->integer('crime_event_id')->unsigned();
            $table->foreign('crime_event_id')->references('id')->on('crime_events');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crime_views');
    }
}
