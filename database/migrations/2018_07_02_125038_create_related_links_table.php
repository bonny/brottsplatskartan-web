<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRelatedLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('related_links', function (Blueprint $table) {
            $table->increments('id');
            $table->string('place', 100)->nullable();
            $table->string('lan', 100)->nullable();
            $table->string('title', 255)->nullable();
            $table->text('desc')->nullable();
            $table->string('url', 255)->nullable();
            $table->integer('prio')->default(0)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->index('place');
            $table->index('lan');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('related_links');
    }
}
