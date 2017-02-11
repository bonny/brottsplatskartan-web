<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*Schema::create('settings', function (Blueprint $table) {
            $table->string('key');
            $table->string('value')->nullable();
            $table->string('locale')->nullable();
        });
        */
        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->change();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::drop('settings');
    }
}
