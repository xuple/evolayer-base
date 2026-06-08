<?php

use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;
use Xuple\EvoLayer\Base\Support\ThreadStudioProviderPolicy;

test('runtimeApprovedProviders delegates to the ThreadStudio config runtime-approved list', function () {
    $config = new ThreadStudioAiConfig;
    $policy = new ThreadStudioProviderPolicy($config);

    expect($policy->runtimeApprovedProviders())->toBe($config->runtimeApprovedProviders());
});

test('the policy is resolvable from the container with its config dependency', function () {
    $policy = app(ThreadStudioProviderPolicy::class);

    expect($policy)->toBeInstanceOf(ThreadStudioProviderPolicy::class)
        ->and($policy->runtimeApprovedProviders())->toBeArray()
        ->and($policy->runtimeApprovedProviders())->not->toBeEmpty();
});

test('the runtime-approved roster is the directly-verified providers (ADR-020)', function () {
    // Runtime-approved = directly verified provider-specific structured
    // streaming: Gemini and OpenAI. Anthropic (blocked / pending re-verification)
    // and the router-backed candidates (nvidia/opencode/openrouter) are
    // intentionally excluded. This pins the roster so a future change must
    // update it explicitly.
    $policy = app(ThreadStudioProviderPolicy::class);

    expect($policy->runtimeApprovedProviders())->toBe(['gemini', 'openai']);
});

test('explain() classifies runtime-approved providers as allowed', function () {
    $policy = app(ThreadStudioProviderPolicy::class);

    foreach (['gemini', 'openai'] as $provider) {
        $availability = $policy->explain($provider);
        expect($availability->allowed)->toBeTrue()
            ->and($availability->status)->toBe('runtime-approved');
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

test('explain() classifies router providers as candidates, not runtime-approved', function () {
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
