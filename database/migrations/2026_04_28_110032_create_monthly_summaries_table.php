<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lagrar AI-genererade månadssammanfattningar per Tier 1-stad
 * (todo #27 Lager 3). Speglar DailySummary-mönstret men aggregerat
 * per (area, year, month).
 *
 * - events_data: array av event-IDs för change-detection
 *   (skip AI-generering om månadens events är oförändrade)
 * - prev_month_count: föregående månads antal events. AI:n får
 *   denna som kontext för att naturligt formulera trend ("ökade med X%"
 *   etc.) — ingen separat trend_percent-kolumn så vi slipper styla det
 *   själva i UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('area', 64);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->mediumText('summary');
            $table->json('events_data')->nullable();
            $table->unsignedInteger('events_count')->default(0);
            $table->unsignedInteger('prev_month_count')->nullable();
            $table->timestamps();

            $table->unique(['area', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_summaries');
    }
};
