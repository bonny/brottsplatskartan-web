<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            // Tidsstämpel: AI-klassifikationen körd. Null = AI har inte
            // bearbetat artikeln än. Vi kan re-köra alla genom att nolla
            // detta fält för en kohort om/när AI-modellen byts.
            $table->timestamp('ai_classified_at')->nullable()->after('classified_at');

            // AI:s slutsats — om artikeln räknas som blåljus/intressant.
            // Null = inte AI-bearbetad än, false = AI sa nej, true = AI sa ja.
            $table->boolean('ai_is_blaljus')->nullable()->after('ai_classified_at');

            // Audit: kort motivering från AI (varför ja/nej + ev. extra context).
            // För felsökning av false positives/negatives utan re-anrop.
            $table->text('ai_reason')->nullable()->after('ai_is_blaljus');

            $table->index('ai_classified_at', 'idx_ai_classified_at');
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropIndex('idx_ai_classified_at');
            $table->dropColumn(['ai_classified_at', 'ai_is_blaljus', 'ai_reason']);
        });
    }
};
