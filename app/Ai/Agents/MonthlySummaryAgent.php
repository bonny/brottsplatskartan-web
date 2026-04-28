<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
#[MaxTokens(2500)]
#[Temperature(0.5)]
#[Timeout(180)]
class MonthlySummaryAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return view('ai.prompts.monthly-summary')->render();
    }
}
