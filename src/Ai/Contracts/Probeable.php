<?php

namespace Xuple\EvoLayer\Base\Ai\Contracts;

/**
 * An agent that can supply its own representative prompt for capability
 * probing. `AiCapabilityProbe` uses this instead of a hardcoded
 * per-agent branch, so any future agent probed against a provider/model
 * provides input that exercises its real schema rather than a generic
 * string that would likely produce malformed output (a false negative).
 */
interface Probeable
{
    /**
     * A representative prompt that should produce a valid structured
     * response from a capable provider/model for this agent's schema.
     */
    public function probePrompt(): string;
}
