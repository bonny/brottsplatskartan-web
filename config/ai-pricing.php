<?php

/**
 * Anthropic-prismatris per million tokens (USD). Underhålls manuellt
 * vid modellbyte. `App\Listeners\LogAiUsage` använder denna för att
 * räkna fram kostnad i micro-USD vid varje anrop. Okänd modell loggas
 * med cost_usd_micros = NULL och en warning skrivs till loggen.
 *
 * Källa: https://www.anthropic.com/pricing (2026-01 snapshot, kontrollera
 * vid modellbyte). Reasoning-tokens betalas till output-pris i Anthropics
 * extended thinking-mode.
 */
return [
    'claude-sonnet-4-6' => [
        'input' => 3.00,
        'output' => 15.00,
        'cache_write' => 3.75,
        'cache_read' => 0.30,
        'reasoning' => 15.00,
    ],
    'claude-haiku-4-5' => [
        'input' => 1.00,
        'output' => 5.00,
        'cache_write' => 1.25,
        'cache_read' => 0.10,
        'reasoning' => 5.00,
    ],
    'claude-sonnet-4-5-20250929' => [
        'input' => 3.00,
        'output' => 15.00,
        'cache_write' => 3.75,
        'cache_read' => 0.30,
        'reasoning' => 15.00,
    ],
];
