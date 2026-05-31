<?php

use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Ai\Contracts\Probeable;
use Xuple\EvoLayer\Base\Ai\ProbeResult;

test('toLegacyArray preserves the array{ok,message} contract', function () {
    $result = new ProbeResult(
        ok: true,
        message: 'Structured output works.',
        outputMode: 'json_schema',
        status: 'supported',
    );

    expect($result->toLegacyArray())->toBe([
        'ok' => true,
        'message' => 'Structured output works.',
    ]);
});

test('ProbeResult carries observation fields without policy', function () {
    $conditions = [['type' => 'StructuredStreaming', 'status' => 'True']];

    $result = new ProbeResult(
        ok: true,
        message: 'ok',
        outputMode: 'json_schema',
        status: 'supported',
        conditions: $conditions,
        latencyMs: 1234,
        payload: ['summary' => 'A short summary.'],
    );

    expect($result->conditions)->toBe($conditions)
        ->and($result->latencyMs)->toBe(1234)
        ->and($result->payload)->toBe(['summary' => 'A short summary.']);
});

test('ThreadStudioAgent is Probeable and returns a non-empty probe prompt', function () {
    $agent = new ThreadStudioAgent;

    expect($agent)->toBeInstanceOf(Probeable::class)
        ->and($agent->probePrompt())->toBeString()
        ->and(trim($agent->probePrompt()))->not->toBe('')
        ->and($agent->probePrompt())->toContain('Customer message:');
});
