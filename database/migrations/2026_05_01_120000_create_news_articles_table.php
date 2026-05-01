<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50);
            $table->string('url', 2000);
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->timestamp('pubdate')->nullable();
            $table->char('content_hash', 64)->unique();
            $table->timestamp('fetched_at');

            $table->index(['source', 'pubdate'], 'idx_source_pubdate');
            $table->index('fetched_at', 'idx_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
