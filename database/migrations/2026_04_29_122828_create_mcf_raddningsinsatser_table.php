<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MCF (tidigare MSB) räddningstjänstens insatser per kommun, år, månad och
 * övergripande händelsetyp (todo #39).
 *
 * Datakälla: PxWeb v1 API på statistik.mcf.se, tabell B11. Komplement till
 * BRÅ #38 — BRÅ täcker brott, MCF täcker olyckor/räddning.
 *
 * Granularitet: månad × händelsetyp. År-totalen härleds via SUM(). Rader
 * med antal=0 importeras inte för att hålla tabellstorleken nere
 * (~290 × 28år × 12mån × 14typ = 1.4M möjliga, ~50 % nollor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcf_raddningsinsatser', function (Blueprint $table) {
            $table->id();
            $table->string('kommun_kod', 4);
            $table->year('ar');
            $table->unsignedTinyInteger('manad');
            $table->unsignedSmallInteger('handelsetyp_id');
            $table->string('handelsetyp_namn', 80);
            $table->unsignedInteger('antal');
            $table->string('source_url', 500)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['kommun_kod', 'ar', 'manad', 'handelsetyp_id'],
                'idx_unique_kommun_ar_manad_typ'
            );
            $table->index(['ar', 'handelsetyp_id'], 'idx_ar_typ');
            $table->index(['kommun_kod', 'ar'], 'idx_kommun_ar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcf_raddningsinsatser');
    }
};
