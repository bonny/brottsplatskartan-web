<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBlogForEmojis extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blog', function (Blueprint $table) {
            // $table->string('name', 50)->change();
            // $table->string('slug', 280)->collation('utf8mb4_unicode_ci')->change();
            $table->string('title', 280)->collation('utf8mb4_unicode_ci')->change();
            $table->longText('content')->collation('utf8mb4_unicode_ci')->change();
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
