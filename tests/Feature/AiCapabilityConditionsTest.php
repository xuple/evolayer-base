<?php

use Xuple\EvoLayer\Base\Models\AiCapability;

/**
 * ADR-019 conditions-lite column. The column is forward infrastructure —
 * no probe writes it yet — so these tests cover the schema + cast contract,
 * not a producer.
 */
function makeCapabilityRow(array $overrides = []): AiCapability
{
    return AiCapability::create(array_merge([
        'agent_class' => 'Xuple\\EvoLayer\\Base\\Ai\\Agents\\ThreadStudioAgent',
        'provider' => 'gemini',
        'model' => 'gemini-flash-latest',
        'schema_hash' => str_repeat('a', 64),
        'probe_schema' => 'thread_studio',
        'status' => 'supported',
        'output_mode' => 'json_schema',
        'probe_passed' => true,
        'probed_at' => now(),
    ], $overrides));
}

test('conditions defaults to null and does not break existing capability rows', function () {
    $row = makeCapabilityRow();

    expect($row->fresh()->conditions)->toBeNull()
        ->and($row->fresh()->probe_passed)->toBeTrue();
});

test('conditions round-trips as an array of condition tuples', function () {
    $conditions = [
        [
            'type' => 'StructuredStreaming',
            'status' => 'True',
            'reason' => 'FieldDeltasObserved',
            'message' => 'All expected fields emitted deltas and the final payload validated.',
            'schema_hash' => str_repeat('a', 64),
            'observed_at' => '2026-05-31T00:00:00Z',
        ],
        [
            'type' => 'CredentialsConfigured',
            'status' => 'Unknown',
            'reason' => 'NotProbed',
            'message' => 'No credential check was run for this row.',
        ],
    ];

    $row = makeCapabilityRow(['provider' => 'openai', 'conditions' => $conditions]);

    expect($row->fresh()->conditions)->toBe($conditions);
});

test('an Unknown condition is distinct from a False condition', function () {
    // The load-bearing distinction from ADR-019: untested != tested-and-failed.
    $row = makeCapabilityRow([
        'provider' => 'openrouter',
        'conditions' => [
            ['type' => 'StructuredStreaming', 'status' => 'Unknown', 'reason' => 'NotProbed', 'message' => 'Router provider not directly probed.'],
        ],
    ]);

    $statuses = collect($row->fresh()->conditions)->pluck('status');

    expect($statuses)->toContain('Unknown')
        ->and($statuses)->not->toContain('False');
});
