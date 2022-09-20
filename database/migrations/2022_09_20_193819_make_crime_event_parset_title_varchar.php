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
        Schema::table('crime_events', function ($table) {
            // Ändra parsed_title från text till varchar pga
            // längsta titeln är efter många år 57 tecken.
            $table->string('parsed_title', 100)->nullable()->change();
            $table->index('parsed_title');
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
};
