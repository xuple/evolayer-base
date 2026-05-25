<?php

namespace Xuple\EvoLayer\Base\Compat;

use Illuminate\Database\Eloquent\Collection;

/**
 * No-op HasTags for hosts without spatie/laravel-tags.
 *
 * Provides minimal stubs so model autoload doesn't fail and read-only paths
 * (tags(), tagsWithType) return empty collections. Mutation methods
 * (attachTag, syncTagsWithType, etc.) intentionally throw — call sites must
 * gate on the relevant feature flag before invoking these.
 */
trait HasTags
{
    public function tags()
    {
        return $this->morphToMany(\stdClass::class, 'taggable');
    }

    public function tagsWithType(?string $type = null): Collection
    {
        return new Collection;
    }

    public function attachTag(string|array $tags, ?string $type = null): never
    {
        throw new \RuntimeException(
            'EvoDevOps Base: attachTag() called without spatie/laravel-tags installed. '.
            'Install it via `composer require spatie/laravel-tags`.'
        );
    }

    public function syncTagsWithType(array|\ArrayAccess $tags, ?string $type = null): never
    {
        throw new \RuntimeException(
            'EvoDevOps Base: syncTagsWithType() called without spatie/laravel-tags installed. '.
            'Install it via `composer require spatie/laravel-tags`.'
        );
    }
}
