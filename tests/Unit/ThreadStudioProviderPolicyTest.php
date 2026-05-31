<?php

use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;
use Xuple\EvoLayer\Base\Support\ThreadStudioProviderPolicy;

test('curatedProviders delegates to the ThreadStudio config curated list', function () {
    $config = new ThreadStudioAiConfig;
    $policy = new ThreadStudioProviderPolicy($config);

    expect($policy->curatedProviders())->toBe($config->supportedProviders());
});

test('the policy is resolvable from the container with its config dependency', function () {
    $policy = app(ThreadStudioProviderPolicy::class);

    expect($policy)->toBeInstanceOf(ThreadStudioProviderPolicy::class)
        ->and($policy->curatedProviders())->toBeArray()
        ->and($policy->curatedProviders())->not->toBeEmpty();
});

test('the curated roster is the directly-verified providers (ADR-020 D-prime)', function () {
    // Curated = directly verified provider-specific structured streaming:
    // Gemini and OpenAI. Anthropic (blocked/pending) and the router
    // candidates (nvidia/opencode/openrouter) are intentionally excluded.
    // This pins the roster so a future change must update it explicitly.
    $policy = app(ThreadStudioProviderPolicy::class);

    expect($policy->curatedProviders())->toBe(['gemini', 'openai']);
});
