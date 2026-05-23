<?php

use EvoDevOps\Base\Support\PartialJsonExtractor;

test('it extracts a single field from a complete chunk', function () {
    $extractor = new PartialJsonExtractor;

    $deltas = $extractor->feed('{"summary":"Hello"}');

    expect($deltas)->toHaveCount(2);
    expect($deltas[0])->toMatchArray(['name' => 'summary', 'delta' => 'Hello', 'complete' => false]);
    expect($deltas[1])->toMatchArray(['name' => 'summary', 'delta' => '', 'complete' => true]);
    expect($extractor->isComplete())->toBeTrue();
    expect($extractor->tryParseComplete())->toBe(['summary' => 'Hello']);
});

test('it streams multiple fields in order', function () {
    $extractor = new PartialJsonExtractor;

    $deltas = $extractor->feed('{"a":"one","b":"two"}');

    $byField = [];
    foreach ($deltas as $delta) {
        $byField[$delta['name']][] = $delta['delta'];
    }

    expect($byField)->toHaveKey('a');
    expect($byField)->toHaveKey('b');
    expect($extractor->tryParseComplete())->toBe(['a' => 'one', 'b' => 'two']);
});

test('it batches characters within one feed call into a single delta', function () {
    $extractor = new PartialJsonExtractor;

    $deltas = $extractor->feed('{"summary":"Hello world"}');
    $partial = array_values(array_filter($deltas, fn ($d) => $d['complete'] === false));

    expect($partial)->toHaveCount(1);
    expect($partial[0]['delta'])->toBe('Hello world');
});

test('it emits per-chunk deltas when input is split across feeds', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('{"summary":"Hel');
    $extractor->feed('lo wor');
    $extractor->feed('ld"}');

    expect($extractor->isComplete())->toBeTrue();
    expect($extractor->tryParseComplete())->toBe(['summary' => 'Hello world']);
});

test('it survives a chunk boundary inside a JSON escape sequence', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('{"summary":"line1\\');
    $extractor->feed('nline2"}');

    expect($extractor->tryParseComplete())->toBe(['summary' => "line1\nline2"]);
});

test('it survives a chunk boundary inside a unicode escape', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('{"summary":"snowman: \\u26');
    $extractor->feed('04"}');

    expect($extractor->tryParseComplete())->toBe(['summary' => 'snowman: ☄']);
});

test('it decodes standard JSON escape sequences', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('{"a":"q\\"q","b":"slash\\\\","c":"tab\\there"}');

    expect($extractor->tryParseComplete())->toBe([
        'a' => 'q"q',
        'b' => 'slash\\',
        'c' => "tab\there",
    ]);
});

test('it tolerates whitespace between tokens', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('  { "a" : "one" , "b" : "two" }  ');

    expect($extractor->tryParseComplete())->toBe(['a' => 'one', 'b' => 'two']);
});

test('it handles an empty string value', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('{"summary":""}');

    expect($extractor->tryParseComplete())->toBe(['summary' => '']);
});

test('it returns null from tryParseComplete before the closing brace', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('{"summary":"Hello');

    expect($extractor->isComplete())->toBeFalse();
    expect($extractor->tryParseComplete())->toBeNull();
});

test('it ignores leading text before the opening brace', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('```json {"a":"v"}');

    expect($extractor->tryParseComplete())->toBe(['a' => 'v']);
});

test('it stops parsing after the top-level closing brace', function () {
    $extractor = new PartialJsonExtractor;

    $extractor->feed('{"a":"v"} trailing junk');

    expect($extractor->tryParseComplete())->toBe(['a' => 'v']);
});

test('it streams the ThreadStudio schema across realistic chunks', function () {
    $extractor = new PartialJsonExtractor;

    $chunks = [
        '{"summary":"Customer ',
        'cannot download invoices.","urgency":"med',
        'ium","sentiment":"frustrated","recommended_tone":"calm and accountable"',
        ',"customer_reply":"Thanks for flagging this.","internal_note":"Investigate billing."}',
    ];

    foreach ($chunks as $chunk) {
        $extractor->feed($chunk);
    }

    expect($extractor->tryParseComplete())->toBe([
        'summary' => 'Customer cannot download invoices.',
        'urgency' => 'medium',
        'sentiment' => 'frustrated',
        'recommended_tone' => 'calm and accountable',
        'customer_reply' => 'Thanks for flagging this.',
        'internal_note' => 'Investigate billing.',
    ]);
});
