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
            $table->string('title_alt_1')->nullable()->default(null);
            $table->text('description_alt_1')->nullable()->default(null);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('crime_events', function ($table) {
            $table->dropColumn('title_alt_1');
            $table->dropColumn('description_alt_1');
        });
    }
};
