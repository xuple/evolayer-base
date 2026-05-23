<?php

use EvoDevOps\Base\Support\ThreadStudioResult;

test('it creates a thread studio result from structured provider data', function () {
    $result = ThreadStudioResult::fromArray([
        'summary' => 'Customer cannot download invoices after upgrading.',
        'urgency' => 'medium',
        'sentiment' => 'frustrated',
        'recommended_tone' => 'calm and accountable',
        'customer_reply' => 'Thanks for flagging this. We are checking invoice access now.',
        'internal_note' => 'Investigate invoice visibility after plan upgrade.',
    ]);

    expect($result->summary)->toBe('Customer cannot download invoices after upgrading.')
        ->and($result->recommendedTone)->toBe('calm and accountable')
        ->and($result->toArray())->toMatchArray([
            'recommended_tone' => 'calm and accountable',
            'customer_reply' => 'Thanks for flagging this. We are checking invoice access now.',
            'internal_note' => 'Investigate invoice visibility after plan upgrade.',
        ]);
});

test('it rejects malformed thread studio result data', function () {
    expect(fn () => ThreadStudioResult::fromArray([
        'summary' => 'Customer cannot download invoices after upgrading.',
    ]))->toThrow(
        InvalidArgumentException::class,
        'The AI provider response is incomplete or invalid (missing: urgency, sentiment, recommended_tone, customer_reply, internal_note).'
    );
});

test('it rejects invalid urgency values', function () {
    expect(fn () => ThreadStudioResult::fromArray([
        'summary' => 'Customer cannot download invoices after upgrading.',
        'urgency' => 'critical',
        'sentiment' => 'frustrated',
        'recommended_tone' => 'calm',
        'customer_reply' => 'Thanks.',
        'internal_note' => 'Check it.',
    ]))->toThrow(
        InvalidArgumentException::class,
        'The AI provider response is incomplete or invalid (invalid: urgency).'
    );
});

test('it rejects invalid sentiment values', function () {
    expect(fn () => ThreadStudioResult::fromArray([
        'summary' => 'Customer cannot download invoices after upgrading.',
        'urgency' => 'medium',
        'sentiment' => 'furious',
        'recommended_tone' => 'calm',
        'customer_reply' => 'Thanks.',
        'internal_note' => 'Check it.',
    ]))->toThrow(
        InvalidArgumentException::class,
        'The AI provider response is incomplete or invalid (invalid: sentiment).'
    );
});

test('diagnostics returns full missing field list', function () {
    $diagnostics = ThreadStudioResult::diagnostics([
        'summary' => 'Customer needs help.',
    ]);

    expect($diagnostics['missing_fields'])->toBe(['urgency', 'sentiment', 'recommended_tone', 'customer_reply', 'internal_note'])
        ->and($diagnostics['invalid_fields'])->toBe([]);
});

test('diagnostics returns invalid enum value', function () {
    $diagnostics = ThreadStudioResult::diagnostics([
        'summary' => 'Customer needs help.',
        'urgency' => 'critical',
        'sentiment' => 'frustrated',
        'recommended_tone' => 'calm',
        'customer_reply' => 'Thanks.',
        'internal_note' => 'Check it.',
    ]);

    expect($diagnostics['missing_fields'])->toBe([])
        ->and($diagnostics['invalid_fields'])->toHaveCount(1)
        ->and($diagnostics['invalid_fields'][0]['field'])->toBe('urgency')
        ->and($diagnostics['invalid_fields'][0]['reason'])->toContain('not in enum');
});

test('fromArray exception names the full failure set not only the first field', function () {
    expect(fn () => ThreadStudioResult::fromArray([
        'summary' => 'Customer needs help.',
        'urgency' => 'critical',
        'sentiment' => 'furious',
    ]))->toThrow(
        InvalidArgumentException::class,
        'incomplete or invalid'
    );

    try {
        ThreadStudioResult::fromArray([
            'summary' => 'Customer needs help.',
            'urgency' => 'critical',
            'sentiment' => 'furious',
        ]);
    } catch (InvalidArgumentException $exception) {
        $message = $exception->getMessage();

        expect($message)->toContain('missing: recommended_tone, customer_reply, internal_note');
        expect($message)->toContain('invalid: urgency, sentiment');
    }
});
