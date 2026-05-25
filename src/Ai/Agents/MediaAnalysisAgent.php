<?php

namespace Xuple\EvoLayer\Base\Ai\Agents;

use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * @evo-example contact_ai
 */
#[UseCheapestModel]
class MediaAnalysisAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an assistant that analyses files attached to support messages. '
            .'Provide a brief, factual description of the file content in one to two sentences. '
            .'Focus on what is relevant to a support team reading the submission.';
    }
}
