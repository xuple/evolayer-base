<?php

use Xuple\EvoLayer\Base\Support\LegacyManifestAdoptionCatalog;

test('the public 0.1.9 contact checksums grant only exact historical provenance', function () {
    $catalog = new LegacyManifestAdoptionCatalog;
    $key = 'resources/js/pages/evolayer/contact.tsx';
    $sourceChecksum = 'fd4168d98fe6c5f5e17883e801e75c02a169ffe080c5c843fe5eac208e5e356f';
    $starterChecksum = 'f0f1e6bc09931c1ca1ba0422dfe2c75c0d5bc37792880f212c49e6efb8d5f88d';
    $metadata = [
        'distribution' => 'xuple/evolayer-base-starter',
        'distribution_version' => 'v0.1.19',
        'framework' => 'xuple/evolayer-base',
        'framework_version' => 'v0.1.9',
    ];

    expect($catalog->sourceChecksum('v0.1.9', $metadata, $key, $sourceChecksum))->toBe($sourceChecksum)
        ->and($catalog->sourceChecksum('0.1.9', $metadata, $key, $starterChecksum))->toBe($sourceChecksum)
        ->and($catalog->sourceChecksum('0.1.9', $metadata, $key, str_repeat('0', 64)))->toBeNull()
        ->and($catalog->sourceChecksum('0.1.8', $metadata, $key, $starterChecksum))->toBeNull();
});

test('catalog evidence is bound to the reviewed Starter and Base release pair', function (array $overrides) {
    $metadata = array_replace([
        'distribution' => 'xuple/evolayer-base-starter',
        'distribution_version' => 'v0.1.19',
        'framework' => 'xuple/evolayer-base',
        'framework_version' => 'v0.1.9',
    ], $overrides);

    expect((new LegacyManifestAdoptionCatalog)->sourceChecksum(
        'v0.1.9',
        $metadata,
        'resources/js/pages/evolayer/contact.tsx',
        'f0f1e6bc09931c1ca1ba0422dfe2c75c0d5bc37792880f212c49e6efb8d5f88d',
    ))->toBeNull();
})->with([
    'wrong Starter' => [['distribution_version' => 'v0.1.18']],
    'wrong metadata Base' => [['framework_version' => 'v0.1.8']],
    'wrong distribution' => [['distribution' => 'example/application']],
    'missing identity' => [['distribution' => null]],
]);

test('catalog construction rejects invalid evidence before it can reach a manifest', function (array $records) {
    expect(fn () => new LegacyManifestAdoptionCatalog($records))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'version' => [['v0.1.9' => []]],
    'distribution version' => [['0.1.9' => ['v0.1.19' => []]]],
    'path' => [['0.1.9' => ['0.1.19' => ['../contact.tsx' => []]]]],
    'installed checksum' => [['0.1.9' => ['0.1.19' => ['contact.tsx' => ['bad' => str_repeat('a', 64)]]]]],
    'source checksum' => [['0.1.9' => ['0.1.19' => ['contact.tsx' => [str_repeat('a', 64) => 'bad']]]]],
]);
