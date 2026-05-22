<?php

namespace EvoDevOps\Base\Support;

use EvoDevOps\Base\Ai\Agents\PrdAgent;

/**
 * @evo-example prd_studio
 */
class PrdGenerator
{
    /**
     * @param  array{product_context: string, audience?: string|null, constraints?: string|null, tone?: string|null}  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        return (new PrdAgent)
            ->prompt($this->prompt($input))
            ->toArray();
    }

    /**
     * @param  array{product_context: string, audience?: string|null, constraints?: string|null, tone?: string|null}  $input
     */
    private function prompt(array $input): string
    {
        $audience = $input['audience'] ?? 'Infer the primary audience from the product context.';
        $constraints = $input['constraints'] ?? 'No additional constraints were provided.';
        $tone = $input['tone'] ?? 'concise';

        return <<<PROMPT
PRD tone: {$tone}

Product context:
{$input['product_context']}

Audience:
{$audience}

Constraints:
{$constraints}
PROMPT;
    }
}
