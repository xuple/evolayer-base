<?php

/**
 * EvoLayer Base — Spatie compat bootstrap.
 *
 * Loaded via composer.json `autoload.files` so it runs before any model is
 * autoloaded. Conditionally defines polyfill interfaces / traits under the
 * Xuple\EvoLayer\Base\Compat namespace based on whether Spatie packages are
 * installed.
 *
 * The package's own models (e.g. FormSubmission) import from the Compat
 * namespace so they autoload identically in both modes. Behaviour (addMedia,
 * attachTag, etc.) is gated at call sites by EVOLAYER_BASE_FEATURE_* flags.
 */

namespace Xuple\EvoLayer\Base\Compat;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

if (! interface_exists(__NAMESPACE__.'\\HasMedia')) {
    if (interface_exists(HasMedia::class)) {
        require __DIR__.'/_HasMedia.spatie.php';
    } else {
        require __DIR__.'/_HasMedia.noop.php';
    }
}

if (! trait_exists(__NAMESPACE__.'\\InteractsWithMedia')) {
    if (trait_exists(InteractsWithMedia::class)) {
        require __DIR__.'/_InteractsWithMedia.spatie.php';
    } else {
        require __DIR__.'/_InteractsWithMedia.noop.php';
    }
}

if (! trait_exists(__NAMESPACE__.'\\HasTags')) {
    if (trait_exists(HasTags::class)) {
        require __DIR__.'/_HasTags.spatie.php';
    } else {
        require __DIR__.'/_HasTags.noop.php';
    }
}
