<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `crime_event_news` går från "sparade träffar" till "kontrollerade par".
 *
 * Utan de negativa svaren skickas samma event × artikel-par till Haiku vid
 * varje körning — vilket blir dyrt när matchningen körs var 15:e minut för
 * färska events. Befintliga rader är per definition träffar, därav default
 * true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crime_event_news', function (Blueprint $table) {
            $table->boolean('is_match')->default(true)->after('news_article_id');
            $table->index(['crime_event_id', 'is_match'], 'idx_event_is_match');
        });
    }

    public function down(): void
    {
        Schema::table('crime_event_news', function (Blueprint $table) {
            $table->dropIndex('idx_event_is_match');
            $table->dropColumn('is_match');
        });
    }
};
