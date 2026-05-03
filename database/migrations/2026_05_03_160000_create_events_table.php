<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trafikverket Trafikinformation (todo #50, Fas 1).
 *
 * Polymorf events-tabell — 'source' ENUM gör samma schema återanvändbart
 * för #51:s framtida källor (SMHI, krisinfo, räddningstjänst).
 *
 * crime_events lever vidare orörd. message_type och county_no LÅSES vid
 * first-write så raden inte retroflyttas mellan retention-policies eller
 * läns-aggregat om Trafikverket flippar fält mid-life.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source', 32);
            $table->string('external_id', 64);
            $table->string('parent_external_id', 64)->nullable();
            $table->string('message_type', 64);
            $table->string('message_code', 64)->nullable();
            $table->tinyInteger('severity_code')->nullable();
            $table->boolean('suspended')->default(false);
            $table->timestamp('last_seen_active_at')->nullable();
            $table->string('icon_id', 64)->nullable();
            $table->text('message')->nullable();
            $table->text('location_descriptor')->nullable();
            $table->string('road_number', 32)->nullable();
            $table->smallInteger('county_no')->nullable();
            $table->string('administrative_area_level_1', 64)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->timestamp('created_time');
            $table->timestamp('modified_time');
            $table->unsignedBigInteger('related_event_id')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('imported_at');

            $table->unique(['source', 'external_id'], 'uniq_events_source_extid');
            $table->index(['lat', 'lng'], 'idx_events_geo');
            $table->index(['start_time', 'end_time'], 'idx_events_time');
            $table->index(['source', 'end_time'], 'idx_events_source_active');
            $table->index(['county_no', 'start_time'], 'idx_events_county_time');
        });

        Schema::create('event_counties', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id');
            $table->smallInteger('county_no');

            $table->primary(['event_id', 'county_no']);
            $table->index(['county_no', 'event_id'], 'idx_event_counties_county');

            $table->foreign('event_id')
                ->references('id')->on('events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_counties');
        Schema::dropIfExists('events');
    }
};
