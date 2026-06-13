<?php

use Xuple\EvoLayer\Base\Support\EvoLayerProps;

test('brand config exposes name, tagline, and description defaults', function () {
    $brand = config('evolayer.base.brand');

    expect($brand)->toBeArray()
        ->and($brand)->toHaveKeys(['name', 'tagline', 'description'])
        ->and($brand['name'])->toBe('EvoLayer Base');
});

test('EvoLayerProps::base assembles brand alongside examples and features', function () {
    $payload = EvoLayerProps::base();

    expect($payload)->toHaveKeys(['examples', 'features', 'brand'])
        ->and($payload['brand']['name'])->toBe('EvoLayer Base')
        ->and($payload['examples'])->toHaveKey('thread_studio');
});

test('host env overrides the brand name', function () {
    config()->set('evolayer.base.brand.name', 'Acme Platform');

    expect(EvoLayerProps::base()['brand']['name'])->toBe('Acme Platform');
});
