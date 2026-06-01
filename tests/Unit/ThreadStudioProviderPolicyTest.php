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

test('explain() classifies curated providers as allowed', function () {
    $policy = app(ThreadStudioProviderPolicy::class);

    foreach (['gemini', 'openai'] as $provider) {
        $availability = $policy->explain($provider);
        expect($availability->allowed)->toBeTrue()
            ->and($availability->status)->toBe('curated');
    }
});

test('explain() blocks Anthropic with the structured-streaming reason', function () {
    $availability = app(ThreadStudioProviderPolicy::class)->explain('anthropic');

    expect($availability->allowed)->toBeFalse()
        ->and($availability->status)->toBe('blocked')
        ->and($availability->message)->toBe(
            'Anthropic is known to the diagnostic layer but is blocked for ThreadStudio because structured streaming currently emits no usable TextDelta events.'
        );
});

test('explain() classifies router providers as candidates, not curated', function () {
    $policy = app(ThreadStudioProviderPolicy::class);

    foreach (['nvidia', 'opencode', 'openrouter'] as $provider) {
        $availability = $policy->explain($provider);
        expect($availability->allowed)->toBeFalse()
            ->and($availability->status)->toBe('candidate')
            ->and($availability->message)->toContain('router/probe candidate');
    }
});

test('explain() classifies an unrecognised provider as unknown', function () {
    $availability = app(ThreadStudioProviderPolicy::class)->explain('not-a-provider');

    expect($availability->allowed)->toBeFalse()
        ->and($availability->status)->toBe('unknown')
        ->and($availability->message)->toContain('Unknown ThreadStudio provider');
});
