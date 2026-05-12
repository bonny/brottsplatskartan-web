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
 * Avgör om en specifik nyhetsartikel handlar om en specifik polishändelse
 * (todo #63 fas 1). Tar emot event-metadata + artikel-metadata, returnerar
 * is_match + confidence + kort motivering.
 *
 * Modell: Haiku 4.5 — klassifikation, inte resonemang.
 */
#[Provider(Lab::Anthropic)]
#[Model('claude-haiku-4-5')]
#[MaxTokens(400)]
#[Timeout(60)]
class EventNewsMatcher implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return view('ai.prompts.event-news-match')->render();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_match' => $schema->boolean()->required(),
            'confidence' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
