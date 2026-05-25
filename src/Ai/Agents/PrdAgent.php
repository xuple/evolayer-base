<?php

namespace Xuple\EvoLayer\Base\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * @evo-example prd_studio
 */
#[UseCheapestModel]
#[MaxTokens(2400)]
#[Temperature(0.4)]
class PrdAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a senior product manager writing practical software PRDs. '
            .'Transform rough founder notes into a concise, buildable product requirements document. '
            .'Prefer concrete scope, explicit non-goals, testable acceptance criteria, and implementation risks. '
            .'Do not invent integrations, legal claims, or metrics that are not implied by the prompt.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
            'problem' => $schema->string()->required(),
            'audience' => $schema->string()->required(),
            'goals' => $schema->array()->items($schema->string())->required(),
            'non_goals' => $schema->array()->items($schema->string())->required(),
            'user_stories' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'persona' => $schema->string()->required(),
                    'need' => $schema->string()->required(),
                    'benefit' => $schema->string()->required(),
                ]))
                ->required(),
            'requirements' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'label' => $schema->string()->required(),
                    'description' => $schema->string()->required(),
                    'priority' => $schema->string()->enum(['must', 'should', 'could'])->required(),
                    'acceptance_criteria' => $schema->array()->items($schema->string())->required(),
                ]))
                ->required(),
            'risks' => $schema->array()->items($schema->string())->required(),
            'success_metrics' => $schema->array()->items($schema->string())->required(),
            'open_questions' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
