<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Klassificerar svenska RSS-artiklar (todo #64). Avgör om en artikel handlar
 * om brott / blåljus / olyckor som intresserar Brottsplatskartans besökare,
 * och pekar ut vilken eller vilka kommuner händelsen utspelar sig i.
 *
 * Haiku 4.5 räcker — det är en klassifikationsuppgift, inte resonemang. Vi
 * stänger av thinking via låg max_tokens (Haiku tänker inte ändå utan flagga).
 */
#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5')]
#[MaxTokens(400)]
#[Timeout(60)]
class NewsClassifier implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return view('ai.prompts.news-classify')->render();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_blaljus' => $schema->boolean()->required(),
            'kommun_names' => $schema->array()->items($schema->string())->required(),
            'category' => $schema->string()->required(),
            'confidence' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
