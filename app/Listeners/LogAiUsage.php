<?php

namespace App\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Responses\Data\TextUsage;

/**
 * Persisterar token-användning från `laravel/ai` agent-anrop till
 * `ai_usage_logs`. Drivs av `Laravel\Ai\Events\AgentPrompted` som
 * dispatchas i `vendor/laravel/ai/src/Providers/Concerns/GeneratesText.php`
 * efter varje generering. Se todo #81.
 */
class LogAiUsage
{
    public function handle(AgentPrompted $event): void
    {
        // AgentStreamed ärver AgentPrompted — vi triggas också för stream:ade
        // anrop. Ignorera dem så vi inte dubbel-loggar (vi använder inte
        // streaming idag men säkrast så).
        if ($event instanceof AgentStreamed) {
            return;
        }

        try {
            $usage = $event->response->usage;
            $model = $event->prompt->model;
            $agent = $event->prompt->agent::class;

            $costMicros = $this->calculateCostMicros($model, $usage);

            DB::table('ai_usage_logs')->insert([
                'agent' => $agent,
                'model' => $model,
                'invocation_id' => $event->invocationId,
                // Sedan laravel/ai 1.0 inkluderar inputTokens cache-tokens och
                // cache-/reasoning-fälten är null när providern inte rapporterar
                // dem. Kolumnerna är NOT NULL, och input_tokens har alltid
                // betytt okachad input (Anthropics `input_tokens`) — behåll den
                // betydelsen så gammal och ny data går att jämföra i ai:usage.
                'input_tokens' => $usage->uncachedInputTokens(),
                'output_tokens' => $usage->outputTokens,
                'cache_read_tokens' => $usage->cacheReadInputTokens ?? 0,
                'cache_write_tokens' => $usage->cacheWriteInputTokens ?? 0,
                'reasoning_tokens' => $usage->reasoningTokens ?? 0,
                'cost_usd_micros' => $costMicros,
                'context_json' => json_encode($this->collectContext()),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Loggning får aldrig krascha AI-anropet. Logga felet och gå vidare.
            Log::warning('LogAiUsage misslyckades', [
                'exception' => $e->getMessage(),
                'agent' => $event->prompt->agent::class,
                'model' => $event->prompt->model,
            ]);
        }
    }

    /**
     * Räkna fram kostnad i micro-USD (1 USD = 1_000_000). Returnerar null
     * om modellen saknas i prismatrisen — vi loggar då anropet ändå men
     * utan kostnad, så `ai:usage` kan flagga okända modeller.
     */
    private function calculateCostMicros(string $model, TextUsage $usage): ?int
    {
        $pricing = config("ai-pricing.{$model}");
        if (! is_array($pricing)) {
            Log::warning('Okänd modell saknas i ai-pricing.php', ['model' => $model]);
            return null;
        }

        // Pris-fälten är USD per million tokens. Multiplicera tokens × pris,
        // dela med 1_000_000 (för MTok), multiplicera med 1_000_000 (för micro-USD).
        // De två faktorerna tar ut varandra — direkt: tokens × pris.
        // Reasoning-tokens är en delmängd av outputTokens (laravel/ai 1.0) och
        // ska inte läggas på en gång till.
        $cost = $usage->uncachedInputTokens() * $pricing['input']
            + $usage->outputTokens * $pricing['output']
            + ($usage->cacheWriteInputTokens ?? 0) * $pricing['cache_write']
            + ($usage->cacheReadInputTokens ?? 0) * $pricing['cache_read'];

        return (int) round($cost);
    }

    /**
     * Bygg context-objekt så vi kan svara på "vilket artisan-kommando / HTTP-rutt
     * triggade det här anropet" senare i `ai:usage`-rapporten.
     */
    private function collectContext(): array
    {
        $context = [];

        // Artisan: $_SERVER['argv'] = ['artisan', 'crimeevents:create-summaries', '--administrative_area_level_1=stockholm', ...]
        if (app()->runningInConsole() && isset($_SERVER['argv'])) {
            $argv = $_SERVER['argv'];
            $context['source'] = 'console';
            $context['command'] = $argv[1] ?? null;
            // Skippa argv[0] (skriptnamn) och argv[1] (kommando), spara övriga args så vi kan se flaggor.
            $args = array_slice($argv, 2);
            if ($args !== []) {
                $context['args'] = $args;
            }
            return $context;
        }

        $context['source'] = 'http';
        if (app()->bound('request') && ($route = request()->route()) !== null) {
            $context['route'] = $route->getName() ?? $route->uri();
        }

        return $context;
    }
}
