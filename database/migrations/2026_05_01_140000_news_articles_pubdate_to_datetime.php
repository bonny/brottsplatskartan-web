<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RSS pubdates är externa och kan ligga utanför 1970–2038-fönstret
 * (arkiv, buggiga feeds). DATETIME är säkrare än TIMESTAMP för dem.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE news_articles MODIFY pubdate DATETIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE news_articles MODIFY pubdate TIMESTAMP NULL');
    }
};
