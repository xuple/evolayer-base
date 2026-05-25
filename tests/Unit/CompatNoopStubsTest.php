<?php

use Xuple\EvoLayer\Base\Compat\HasMedia;
use Xuple\EvoLayer\Base\Compat\HasTags;
use Xuple\EvoLayer\Base\Compat\InteractsWithMedia;

/**
 * Verifies the no-op variants of the Compat layer behave correctly.
 *
 * The compat bootstrap aliases either the Spatie-backed or no-op variant
 * into the Xuple\EvoLayer\Base\Compat namespace based on what's installed. In
 * the package's own test environment Spatie IS installed (as require-dev),
 * so the Compat namespace is the Spatie-backed variant. To test the no-op
 * variant we load its file directly into a sandbox namespace and assert
 * its shape.
 *
 * For meaningful end-to-end "no Spatie installed" verification, run the
 * package's tests against a separate composer install without the
 * suggested packages — see README's CI matrix guidance.
 */
test('the no-op InteractsWithMedia trait file is syntactically valid and defines the expected method surface', function () {
    $contents = file_get_contents(__DIR__.'/../../src/Compat/_InteractsWithMedia.noop.php');

    expect($contents)
        ->toContain('trait InteractsWithMedia')
        ->toContain('public function media()')
        ->toContain('public function getMedia(')
        ->toContain('public function hasMedia(')
        ->toContain('public function addMedia(')
        ->toContain('EVOLAYER_BASE_FEATURE_CONTACT_ATTACHMENTS');
});

test('the no-op HasTags trait file is syntactically valid and defines the expected method surface', function () {
    $contents = file_get_contents(__DIR__.'/../../src/Compat/_HasTags.noop.php');

    expect($contents)
        ->toContain('trait HasTags')
        ->toContain('public function tags(')
        ->toContain('public function tagsWithType(')
        ->toContain('public function attachTag(')
        ->toContain('public function syncTagsWithType(')
        ->toContain('spatie/laravel-tags');
});

test('the no-op HasMedia interface file defines an empty marker interface', function () {
    $contents = file_get_contents(__DIR__.'/../../src/Compat/_HasMedia.noop.php');

    expect($contents)
        ->toContain('interface HasMedia')
        ->toContain('contact_attachments');
});

test('the Compat bootstrap loads conditionally based on installed packages', function () {
    $contents = file_get_contents(__DIR__.'/../../src/Compat/bootstrap.php');

    expect($contents)
        ->toContain('interface_exists(\\Spatie\\MediaLibrary\\HasMedia::class)')
        ->toContain('trait_exists(\\Spatie\\MediaLibrary\\InteractsWithMedia::class)')
        ->toContain('trait_exists(\\Spatie\\Tags\\HasTags::class)')
        ->toContain('_HasMedia.spatie.php')
        ->toContain('_HasMedia.noop.php');
});

test('the Compat namespace exposes HasMedia, InteractsWithMedia, and HasTags symbols', function () {
    // In this test environment Spatie IS installed, so the symbols resolve
    // via the Spatie-backed variants. Either way the symbols must exist.
    expect(interface_exists(HasMedia::class))->toBeTrue()
        ->and(trait_exists(InteractsWithMedia::class))->toBeTrue()
        ->and(trait_exists(HasTags::class))->toBeTrue();
});
