<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crime_events', function (Blueprint $table) {
            $table->unsignedInteger('polisen_id')->nullable()->after('md5')->index();
            $table->decimal('polisen_gps_lat', 10, 7)->nullable()->after('polisen_id');
            $table->decimal('polisen_gps_lng', 10, 7)->nullable()->after('polisen_gps_lat');
        });
    }

    public function down(): void
    {
        Schema::table('crime_events', function (Blueprint $table) {
            $table->dropIndex(['polisen_id']);
            $table->dropColumn(['polisen_id', 'polisen_gps_lat', 'polisen_gps_lng']);
        });
    }
};
