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

test('the curated list preserves current behaviour as a regression guard', function () {
    // ADR-019 ships the seam with NO roster change. This pins the current
    // curated list so a roster change (Options A-E) cannot land accidentally
    // inside an unrelated commit — it must come with an explicit update here.
    $policy = app(ThreadStudioProviderPolicy::class);

    expect($policy->curatedProviders())
        ->toBe(['anthropic', 'gemini', 'nvidia', 'opencode', 'openrouter']);
});
