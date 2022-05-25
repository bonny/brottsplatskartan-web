<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vma_alerts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('identifier');
            $table->dateTime('sent')->nullable();
            $table->string('status')->nullable();
            $table->string('msgType')->nullable();
            $table->string('references')->nullable();
            $table->string('incidents')->nullable();
            $table->json('original_message')->nullable();
        });
    }

    /*
    name	type
    id	primary key
    Identifier	string
    Sent	date
    status	string
    msgType	string
    references	string
    incidents	string
    original_message	json
    */
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vma_alerts');
    }
};
