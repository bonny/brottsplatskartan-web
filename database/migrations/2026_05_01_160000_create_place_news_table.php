<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plats-nyhetsaggregering (todo #64): koppling artikel ↔ plats
 * efter klassifikation. classified_at på news_articles markerar
 * "har bearbetats" så vi inte gör om jobbet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->timestamp('classified_at')->nullable()->after('fetched_at');
            $table->index('classified_at', 'idx_classified_at');
        });

        Schema::create('place_news', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('place_id');
            $table->unsignedBigInteger('news_article_id');
            $table->timestamp('pubdate')->nullable();
            $table->timestamps();

            $table->unique(['place_id', 'news_article_id'], 'uniq_place_article');
            $table->index(['place_id', 'pubdate'], 'idx_place_pubdate');
            $table->index('news_article_id', 'idx_news_article');

            $table->foreign('place_id')
                ->references('id')->on('places')
                ->cascadeOnDelete();
            $table->foreign('news_article_id')
                ->references('id')->on('news_articles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_news');

        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropIndex('idx_classified_at');
            $table->dropColumn('classified_at');
        });
    }
};
