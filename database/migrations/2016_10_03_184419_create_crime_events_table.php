<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCrimeEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crime_events', function (Blueprint $table) {

            $table->increments('id');
            $table->timestamps();
            $table->string("title");
            $table->text("description");
            $table->string("permalink");
            $table->string("pubdate");
            $table->string("pubdate_iso8601");
            $table->string("md5");

            $table->index("md5");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crime_events');
    }
}
