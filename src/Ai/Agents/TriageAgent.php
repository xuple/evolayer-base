<?php

namespace EvoDevOps\Base\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * @evo-example admin_inbox
 */
#[UseCheapestModel]
class TriageAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const array TAGS = [
        'billing', 'bug', 'complaint', 'compliment',
        'feedback', 'general', 'sales', 'support', 'technical', 'urgent',
    ];

    public function instructions(): Stringable|string
    {
        return 'You are a support triage assistant. Analyse the subject and message from a contact form submission. '
            .'Return urgency (low, medium, or high), sentiment (positive, neutral, or negative), a concise '
            .'one-to-two sentence summary of what the sender needs, and up to 3 tags from the allowed list. '
            .'Be objective and consistent.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'urgency' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'sentiment' => $schema->string()->enum(['positive', 'neutral', 'negative'])->required(),
            'summary' => $schema->string()->required(),
            'tags' => $schema->array()->items($schema->string()->enum(self::TAGS))->required(),
        ];
    }
}
