<?php

namespace EvoDevOps\Base\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * @evo-example contact_ai
 */
#[UseCheapestModel]
#[MaxTokens(60)]
#[Temperature(0.7)]
class SubjectHintsAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You generate short, realistic email subject line suggestions for a web contact form. '
            .'Return exactly 3 suggestions separated by " - " on a single line. '
            .'No numbering, no quotes, no explanation. Plain text only.';
    }
}
