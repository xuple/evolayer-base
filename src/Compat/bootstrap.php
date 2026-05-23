<?php

/**
 * EvoDevOps Base — Spatie compat bootstrap.
 *
 * Loaded via composer.json `autoload.files` so it runs before any model is
 * autoloaded. Conditionally defines polyfill interfaces / traits under the
 * EvoDevOps\Base\Compat namespace based on whether Spatie packages are
 * installed.
 *
 * The package's own models (e.g. FormSubmission) import from the Compat
 * namespace so they autoload identically in both modes. Behaviour (addMedia,
 * attachTag, etc.) is gated at call sites by EVO_BASE_FEATURE_* flags.
 */

namespace EvoDevOps\Base\Compat;

if (! interface_exists(__NAMESPACE__.'\\HasMedia')) {
    if (interface_exists(\Spatie\MediaLibrary\HasMedia::class)) {
        require __DIR__.'/_HasMedia.spatie.php';
    } else {
        require __DIR__.'/_HasMedia.noop.php';
    }
}

if (! trait_exists(__NAMESPACE__.'\\InteractsWithMedia')) {
    if (trait_exists(\Spatie\MediaLibrary\InteractsWithMedia::class)) {
        require __DIR__.'/_InteractsWithMedia.spatie.php';
    } else {
        require __DIR__.'/_InteractsWithMedia.noop.php';
    }
}

if (! trait_exists(__NAMESPACE__.'\\HasTags')) {
    if (trait_exists(\Spatie\Tags\HasTags::class)) {
        require __DIR__.'/_HasTags.spatie.php';
    } else {
        require __DIR__.'/_HasTags.noop.php';
    }
}
