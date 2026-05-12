<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event-nyhetskoppling (todo #63 fas 1): Haiku-validerad matchning
 * mellan en specifik polishändelse och en specifik nyhetsartikel. Bygger
 * på #64:s news_articles + place_news — vi går place_news → kandidater
 * inom event-datum ±2d → Haiku → spara träffar här.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crime_event_news', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('crime_event_id');
            $table->unsignedBigInteger('news_article_id');
            $table->string('confidence', 10);
            $table->string('ai_reason', 500)->nullable();
            $table->string('ai_model', 50)->nullable();
            $table->timestamp('matched_at');
            $table->timestamps();

            $table->unique(['crime_event_id', 'news_article_id'], 'uniq_event_article');
            $table->index(['crime_event_id', 'confidence'], 'idx_event_confidence');
            $table->index('news_article_id', 'idx_event_news_article');

            $table->foreign('crime_event_id')
                ->references('id')->on('crime_events')
                ->cascadeOnDelete();
            $table->foreign('news_article_id')
                ->references('id')->on('news_articles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime_event_news');
    }
};
