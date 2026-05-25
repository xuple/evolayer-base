<?php

namespace Xuple\EvoLayer\Base\Compat;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * No-op InteractsWithMedia for hosts without spatie/laravel-medialibrary.
 *
 * Provides minimal stubs so model autoload doesn't fail and read-only paths
 * (getMedia, media(), hasMedia) return empty collections gracefully. Mutation
 * methods (addMedia, etc.) intentionally throw — call sites must gate on
 * config('evo.base.features.contact_attachments') before invoking these.
 */
trait InteractsWithMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(\stdClass::class, 'model');
    }

    public function getMedia(string $collectionName = 'default', $filters = []): Collection
    {
        return new Collection;
    }

    public function hasMedia(string $collectionName = 'default'): bool
    {
        return false;
    }

    public function addMedia($file): never
    {
        throw new \RuntimeException(
            'EvoDevOps Base: addMedia() called without spatie/laravel-medialibrary installed. '.
            'Install it via `composer require spatie/laravel-medialibrary` and set '.
            'EVO_BASE_FEATURE_CONTACT_ATTACHMENTS=true.'
        );
    }
}
