<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BRÅ:s anmälda brott per kommun + år (todo #38).
 *
 * Avblockerar #27 Lager 2 — riktig brottsstatistik som komplement till
 * Polisens publicerade händelser (vilka inte är heltäckande).
 *
 * MVP: bara totaler. Brottstyp-uppdelning kräver SOL-scraping (ej i scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bra_anmalda_brott', function (Blueprint $table) {
            $table->id();
            $table->string('kommun_kod', 4);
            $table->year('ar');
            $table->unsignedInteger('antal');
            $table->unsignedInteger('per_100k');
            $table->string('source_url', 500)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['kommun_kod', 'ar'], 'idx_unique_kommun_ar');
            $table->index('ar', 'idx_ar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bra_anmalda_brott');
    }
};
