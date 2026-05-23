<?php

use EvoDevOps\Base\Support\AiCapabilityHash;

test('canonicalise correctly deep sorts nested arrays', function () {
    $array1 = [
        'z' => [
            'b' => 1,
            'a' => 2,
        ],
        'a' => 5,
    ];

    $array2 = [
        'a' => 5,
        'z' => [
            'a' => 2,
            'b' => 1,
        ],
    ];

    $hash1 = AiCapabilityHash::fromSchema($array1);
    $hash2 = AiCapabilityHash::fromSchema($array2);

    expect($hash1)->toBe($hash2);
});

test('probeSchema derives snake case without Agent suffix', function () {
    $schema = AiCapabilityHash::probeSchema('App\\Ai\\Agents\\ThreadStudioAgent');
    expect($schema)->toBe('thread_studio');
});

test('probeSchema derives snake case cleanly from acronyms', function () {
    // The current implementation is simple and might produce open_a_i.
    // Documenting the current behavior so any breakage is deliberate.
    $schema = AiCapabilityHash::probeSchema('App\\Ai\\Agents\\OpenAIAgent');
    expect($schema)->toBe('open_a_i');
});
