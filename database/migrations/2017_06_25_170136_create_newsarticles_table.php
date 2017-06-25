<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNewsarticlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('newsarticles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('crime_event_id')->unsigned();
            $table->foreign('crime_event_id')->references('id')->on('crime_events');
            $table->string('title');
            $table->text('shortdesc');
            $table->string('url');
            $table->softDeletes();
            $table->timestamps();
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
