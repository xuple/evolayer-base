<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Xuple\EvoLayer\Base\Support\PublishMap;

final class ExpectedProfileStateResolver
{
    /** @param array<string, mixed> $metadata */
    public function resolve(array $metadata, ProfileRegistry $profiles, PublishMap $map): ?ExpectedProfileState
    {
        if ($metadata === []) {
            return null;
        }

        $profile = $metadata['profile'] ?? null;
        $definition = is_string($profile) ? $profiles->get($profile) : null;

        if ($definition === null) {
            throw new ProfileStateException('Committed project intent references an unavailable profile definition.');
        }

        $overrides = $metadata['overrides'];
        $examples = $this->applyOverrides(
            category: 'examples',
            baseline: $definition->examples,
            overrides: $overrides['examples'],
            knownKeys: array_values(array_unique([
                ...array_keys($definition->examples),
                ...array_map(fn ($surface): string => $surface->configKey, $map->surfaces()),
            ])),
            allowedCategories: $definition->allowedOverrides,
        );
        $features = $this->applyOverrides(
            category: 'features',
            baseline: $definition->features,
            overrides: $overrides['features'],
            knownKeys: array_values(array_unique([
                ...array_keys($definition->features),
                ...array_keys((array) config('evolayer.base.features')),
            ])),
            allowedCategories: $definition->allowedOverrides,
        );

        return new ExpectedProfileState($definition, $examples, $features);
    }

    /**
     * @param  array<string, bool>  $baseline
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $knownKeys
     * @param  list<string>  $allowedCategories
     * @return array<string, bool>
     */
    private function applyOverrides(
        string $category,
        array $baseline,
        array $overrides,
        array $knownKeys,
        array $allowedCategories,
    ): array {
        if ($overrides !== [] && ! in_array($category, $allowedCategories, true)) {
            throw new ProfileStateException("Profile does not allow [{$category}] overrides.");
        }

        foreach ($overrides as $key => $enabled) {
            if (! is_string($key) || ! is_bool($enabled) || ! in_array($key, $knownKeys, true)) {
                throw new ProfileStateException("Committed profile contains an invalid [{$category}] override.");
            }

            $baseline[$key] = $enabled;
        }

        return $baseline;
    }
}
