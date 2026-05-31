<?php

use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Models\AiCapability;

$validReply = [
    'summary' => 'Customer needs next steps after downloading.',
    'urgency' => 'low',
    'sentiment' => 'confused',
    'recommended_tone' => 'warm and guiding',
    'customer_reply' => 'Welcome! After downloading, run composer setup and then visit the dashboard.',
    'internal_note' => 'New user; point at the SQLite fast-start lane.',
];

beforeEach(function () {
    config()->set('ai.providers.opencode', [
        'driver' => 'openrouter',
        'key' => 'test-key',
        'url' => 'https://opencode.ai/zen/go/v1',
        'models' => ['text' => ['default' => 'kimi-k2.6']],
    ]);
});

test('smoke-test success message includes the response summary and writes no rows', function () use ($validReply) {
    ThreadStudioAgent::fake([$validReply])->preventStrayPrompts();

    $this->artisan('evolayer:ai:smoke-test', ['provider' => 'opencode', '--model' => 'kimi-k2.6'])
        ->expectsOutputToContain('Structured output works (model: kimi-k2.6). Response: Customer needs next steps after downloading.')
        ->assertSuccessful();

    expect(AiCapability::count())->toBe(0);
});

test('smoke-test reports a schema-invalid response as a validation failure', function () {
    // Decodable but missing required fields — the probe alone would pass,
    // but the smoke command's ThreadStudioResult::fromArray() rejects it.
    ThreadStudioAgent::fake([['summary' => 'only a summary, missing the rest']])->preventStrayPrompts();

    $this->artisan('evolayer:ai:smoke-test', ['provider' => 'opencode', '--model' => 'kimi-k2.6'])
        ->assertFailed();
});

test('smoke-test reports a missing API key', function () {
    config()->set('ai.providers.opencode.key', null);
    ThreadStudioAgent::fake(fn () => throw new RuntimeException('agent must not be called without a key'));

    $this->artisan('evolayer:ai:smoke-test', ['provider' => 'opencode', '--model' => 'kimi-k2.6'])
        ->expectsOutputToContain('API key not configured')
        ->assertFailed();
});
