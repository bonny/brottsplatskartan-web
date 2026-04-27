<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SCB-befolkningsdata för "brott per 1000 invånare" (todo #37).
 *
 * Tre tabeller:
 *   scb_tatorter      — råa SCB-tätorter (2017 rader, från GeoPackage CC0)
 *   scb_kommuner      — kommunbefolkning (~290 rader, från SCB-API CC0)
 *   place_population  — mappning bpk-platsnamn → tätortskod/kommunkod
 *
 * Avblockerar #27 Lager 2 (CrimeGrade-modellen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scb_tatorter', function (Blueprint $table) {
            $table->id();
            $table->string('tatortskod', 20)->unique();
            $table->string('tatort');
            $table->string('kommun_kod', 4)->index();
            $table->string('kommun_namn');
            $table->string('lan_kod', 2)->index();
            $table->string('lan_namn');
            $table->unsignedInteger('befolkning');
            $table->unsignedInteger('area_ha')->nullable();
            $table->year('ar');
            $table->timestamps();
        });

        Schema::create('scb_kommuner', function (Blueprint $table) {
            $table->id();
            $table->string('kommun_kod', 4)->unique();
            $table->string('kommun_namn');
            $table->string('lan_kod', 2)->index();
            $table->string('lan_namn')->nullable();
            $table->unsignedInteger('befolkning');
            $table->year('ar');
            $table->timestamps();
        });

        Schema::create('place_population', function (Blueprint $table) {
            $table->id();
            // Binär collation: "Habo" och "Håbo" är olika orter och måste särskiljas.
            // Default utf8mb4_unicode_ci är accent-insensitive och kraschar unique-constraint.
            $table->string('bpk_place_name')->collation('utf8mb4_bin')->unique();
            $table->string('scb_tatortskod', 20)->nullable()->index();
            $table->string('scb_kommun_kod', 4)->nullable()->index();
            $table->string('scb_lan_kod', 2)->nullable()->index();
            $table->enum('source', ['scb_tatort', 'scb_kommun', 'scb_lan', 'manual', 'none'])->default('none');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_population');
        Schema::dropIfExists('scb_kommuner');
        Schema::dropIfExists('scb_tatorter');
    }
};
