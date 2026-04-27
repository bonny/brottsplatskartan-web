<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lägg till Wikidata-koppling på places-tabellen (todo #32).
 *
 * Q-id används i Place-schema.org-`sameAs` för entity-graph-koppling
 * mot AI Overviews 2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('wikidata_qid', 32)->nullable()->index();
            $table->boolean('wikidata_review_needed')->default(true);
            $table->timestamp('wikidata_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex(['wikidata_qid']);
            $table->dropColumn(['wikidata_qid', 'wikidata_review_needed', 'wikidata_verified_at']);
        });
    }
};
