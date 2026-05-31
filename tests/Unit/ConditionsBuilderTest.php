<?php

use Xuple\EvoLayer\Base\Ai\ConditionsBuilder;

beforeEach(function () {
    $this->builder = new ConditionsBuilder;
    $this->hash = str_repeat('a', 64);
});

test('exercised and passed yields True', function () {
    $condition = $this->builder->structuredStreaming(
        exercised: true,
        passed: true,
        reason: 'StructuredOutputValidated',
        message: 'All six fields validated.',
        schemaHash: $this->hash,
    );

    expect($condition['type'])->toBe('StructuredStreaming')
        ->and($condition['status'])->toBe('True')
        ->and($condition['schema_hash'])->toBe($this->hash)
        ->and($condition['observed_at'])->toBeString()->not->toBe('');
});

test('exercised and failed yields False', function () {
    $condition = $this->builder->structuredStreaming(
        exercised: true,
        passed: false,
        reason: 'ProviderDoesNotSupportJsonSchema',
        message: 'Provider does not support json_schema',
        schemaHash: $this->hash,
    );

    expect($condition['status'])->toBe('False');
});

test('not exercised yields Unknown, never False', function () {
    // The load-bearing ADR-019 distinction: a probe that never ran the
    // agent (e.g. missing credentials) observed nothing — Unknown, not False.
    $condition = $this->builder->structuredStreaming(
        exercised: false,
        passed: false,
        reason: 'CredentialsMissing',
        message: 'API key not configured',
        schemaHash: $this->hash,
    );

    expect($condition['status'])->toBe('Unknown')
        ->and($condition['status'])->not->toBe('False');
});

test('probe_passed projection is true only for an observed True condition', function () {
    $true = [$this->builder->structuredStreaming(true, true, 'r', 'm', $this->hash)];
    $false = [$this->builder->structuredStreaming(true, false, 'r', 'm', $this->hash)];
    $unknown = [$this->builder->structuredStreaming(false, false, 'r', 'm', $this->hash)];

    expect($this->builder->structuredStreamingPassed($true))->toBeTrue()
        ->and($this->builder->structuredStreamingPassed($false))->toBeFalse()
        ->and($this->builder->structuredStreamingPassed($unknown))->toBeFalse()
        ->and($this->builder->structuredStreamingPassed([]))->toBeFalse();
});
