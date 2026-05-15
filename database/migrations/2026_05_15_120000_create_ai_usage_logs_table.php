<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-usage-loggning (todo #81): en rad per anrop som `laravel/ai` skickar
 * mot en provider (Anthropic m.fl.). Driver `ai:usage`-rapport och fångar
 * regressioner när nya pipelines lyfts. Fylls av `App\Listeners\LogAiUsage`
 * som lyssnar på `Laravel\Ai\Events\AgentPrompted`.
 *
 * cost_usd_micros = USD * 1_000_000, undviker float-precision och låter
 * SUM() i SQL ge exakt summa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('agent', 150)->index();
            $table->string('model', 80)->index();
            $table->string('invocation_id', 64)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->unsignedBigInteger('cost_usd_micros')->nullable();
            $table->json('context_json')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
