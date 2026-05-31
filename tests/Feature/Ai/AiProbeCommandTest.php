<?php

use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Models\AiCapability;
use Xuple\EvoLayer\Base\Support\AiCapabilityHash;

$threadStudioResult = [
    'summary' => 'Customer cannot download invoices after their plan changed.',
    'urgency' => 'medium',
    'sentiment' => 'frustrated',
    'recommended_tone' => 'calm and accountable',
    'customer_reply' => 'Thanks for flagging this. We are looking into the invoice access issue now.',
    'internal_note' => 'Investigate invoice download permissions after billing plan upgrade.',
];

function liveSchemaHash(): string
{
    return AiCapabilityHash::fromAgent(new ThreadStudioAgent);
}

beforeEach(function () {
    // Provide a full opencode provider config so AiManager::getInstanceConfig
    // resolves (it needs a 'driver'), and the API-key short-circuit doesn't
    // fire. mergeConfigFrom does not deep-merge evolayer-ai.php's custom
    // providers into the SDK's config('ai.providers') in the testbench env,
    // so set the whole block here. ThreadStudioAgent::fake() intercepts the
    // actual prompt, so driver/url are never dialled.
    config()->set('ai.providers.opencode', [
        'driver' => 'openrouter',
        'key' => 'test-key',
        'url' => 'https://opencode.ai/zen/go/v1',
        'models' => ['text' => ['default' => 'kimi-k2.6']],
    ]);
});

test('probe --persist creates a capability row with conditions', function () use ($threadStudioResult) {
    ThreadStudioAgent::fake([$threadStudioResult])->preventStrayPrompts();

    $this->artisan('evolayer:ai:probe', ['--provider' => 'opencode', '--model' => 'kimi-k2.6', '--persist' => true])
        ->assertSuccessful();

    $row = AiCapability::where([
        'agent_class' => ThreadStudioAgent::class,
        'provider' => 'opencode',
        'model' => 'kimi-k2.6',
    ])->first();

    expect($row)->not->toBeNull()
        ->and($row->schema_hash)->toBe(liveSchemaHash())
        ->and($row->status)->toBe('supported')
        ->and($row->output_mode)->toBe('json_schema')
        ->and($row->probe_passed)->toBeTrue()
        ->and($row->probed_at)->not->toBeNull();

    // Conditions tuple written, and probe_passed is its projection.
    expect($row->conditions)->toBeArray()->toHaveCount(1)
        ->and($row->conditions[0]['type'])->toBe('StructuredStreaming')
        ->and($row->conditions[0]['status'])->toBe('True')
        ->and($row->conditions[0]['schema_hash'])->toBe(liveSchemaHash())
        ->and($row->conditions[0])->toHaveKey('observed_at')
        ->and($row->probe_passed)->toBe($row->conditions[0]['status'] === 'True');
});

test('probe without --force is skipped by the 24h cooldown', function () use ($threadStudioResult) {
    AiCapability::create([
        'agent_class' => ThreadStudioAgent::class,
        'provider' => 'opencode',
        'model' => 'kimi-k2.6',
        'schema_hash' => liveSchemaHash(),
        'probe_schema' => 'thread_studio',
        'status' => 'supported',
        'output_mode' => 'json_schema',
        'probe_passed' => true,
        'probed_at' => now(),
    ]);

    ThreadStudioAgent::fake(fn () => throw new RuntimeException('The agent must not be called during cooldown.'));

    $this->artisan('evolayer:ai:probe', ['--provider' => 'opencode', '--model' => 'kimi-k2.6', '--persist' => true])
        ->expectsOutputToContain('Skipped (cooldown). Last passed: Yes. Use --force to reprobe.')
        ->assertSuccessful();
});

test('probe --force reprobes despite a recent row', function () use ($threadStudioResult) {
    $old = now()->subHour();
    AiCapability::create([
        'agent_class' => ThreadStudioAgent::class,
        'provider' => 'opencode',
        'model' => 'kimi-k2.6',
        'schema_hash' => liveSchemaHash(),
        'probe_schema' => 'thread_studio',
        'status' => 'supported',
        'output_mode' => 'json_schema',
        'probe_passed' => true,
        'probed_at' => $old,
    ]);

    ThreadStudioAgent::fake([$threadStudioResult])->preventStrayPrompts();

    $this->artisan('evolayer:ai:probe', ['--provider' => 'opencode', '--model' => 'kimi-k2.6', '--persist' => true, '--force' => true])
        ->assertSuccessful();

    $row = AiCapability::where(['provider' => 'opencode', 'model' => 'kimi-k2.6'])->first();
    expect($row->probed_at->greaterThan($old))->toBeTrue();
});

test('reprobe-stale supersedes the old-hash row and writes a live-hash row', function () use ($threadStudioResult) {
    AiCapability::create([
        'agent_class' => ThreadStudioAgent::class,
        'provider' => 'opencode',
        'model' => 'kimi-k2.6',
        'schema_hash' => str_repeat('0', 64), // stale hash
        'probe_schema' => 'thread_studio',
        'status' => 'supported',
        'output_mode' => 'json_schema',
        'probe_passed' => true,
        'probed_at' => now()->subDays(40),
    ]);

    ThreadStudioAgent::fake([$threadStudioResult])->preventStrayPrompts();

    $this->artisan('evolayer:ai:probe', ['--reprobe-stale' => true, '--max-probes' => '1', '--persist' => true])
        ->assertSuccessful();

    $stale = AiCapability::where('schema_hash', str_repeat('0', 64))->first();
    $live = AiCapability::where('schema_hash', liveSchemaHash())->whereNull('superseded_at')->first();

    expect($stale->superseded_at)->not->toBeNull()
        ->and($live)->not->toBeNull()
        ->and($live->model)->toBe('kimi-k2.6');
});

test('output_mode is preserved, not clobbered to json_schema, on a curated json_object model', function () {
    // mimo-v2.5 is declared json_object/experimental in opencodeModelCompatibility().
    // A successful --force reprobe must preserve the declared output_mode.
    $reply = [
        'summary' => 's', 'urgency' => 'low', 'sentiment' => 'calm',
        'recommended_tone' => 'warm', 'customer_reply' => 'ok', 'internal_note' => 'note',
    ];
    ThreadStudioAgent::fake([$reply])->preventStrayPrompts();

    $this->artisan('evolayer:ai:probe', ['--provider' => 'opencode', '--model' => 'minimax-m2.5', '--persist' => true, '--force' => true])
        ->assertSuccessful();

    $row = AiCapability::where(['provider' => 'opencode', 'model' => 'minimax-m2.5'])->first();
    expect($row)->not->toBeNull()
        ->and($row->output_mode)->toBe('json_object'); // declared mode preserved, not 'json_schema'
});

test('smoke-test writes no capability rows (stays console-only)', function () use ($threadStudioResult) {
    ThreadStudioAgent::fake([$threadStudioResult])->preventStrayPrompts();

    $this->artisan('evolayer:ai:smoke-test', ['provider' => 'opencode'])->run();

    expect(AiCapability::count())->toBe(0);
});
