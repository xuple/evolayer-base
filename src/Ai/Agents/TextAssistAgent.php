<?php

namespace Xuple\EvoLayer\Base\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * @evo-example ai_text_field
 */
#[UseCheapestModel]
#[MaxTokens(400)]
#[Temperature(0.6)]
class TextAssistAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a concise professional writing assistant embedded in a form.

When given a field description and optional surrounding context, generate clear, useful text for that field.

Rules:
- Return ONLY the text for the field. No labels, headings, explanations, or quotes.
- Keep the response focused and appropriately concise for the field type.
- Match the register of the context (technical, professional, conversational) if provided.
- If the context contains partial text, improve or complete it rather than starting over.
PROMPT;
    }
}
