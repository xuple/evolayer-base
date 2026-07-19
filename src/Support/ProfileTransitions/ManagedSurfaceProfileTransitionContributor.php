<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ManagedSurface;
use Xuple\EvoLayer\Base\Support\PublishMap;

final readonly class ManagedSurfaceProfileTransitionContributor implements ProfileTransitionContributor
{
    public function __construct(private PublishMap $map) {}

    public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
    {
        $disabledSurfaces = collect($this->map->surfaces())
            ->filter(fn (ManagedSurface $surface): bool => $surface->ejectable
                && ! ($context->targetExamples[$surface->configKey] ?? false));

        if ($disabledSurfaces->isEmpty()) {
            return;
        }

        $manifestPath = $this->map->manifestPath();
        $manifest = $this->readManifest($manifestPath, $plan);

        foreach ($disabledSurfaces as $surface) {
            if (($manifest['surfaces'][$surface->id] ?? null) === 'ejected') {
                $plan->conflict("Managed surface [{$surface->id}] is ejected and cannot be disabled automatically.");

                continue;
            }

            $keys = collect($manifest['files'])
                ->filter(fn (mixed $file): bool => is_array($file) && ($file['surface'] ?? null) === $surface->id)
                ->keys()
                ->all();

            foreach ($this->map->expand($surface->paths) as $target) {
                $key = $this->relativeKey($target);

                if (file_exists($target) && ! in_array($key, $keys, true)) {
                    $plan->conflict("Managed file [{$key}] has unknown provenance; it was not recorded in the resync manifest.");
                }
            }

            foreach ($keys as $key) {
                $target = $this->targetPath($key);
                $record = $manifest['files'][$key];

                if (! file_exists($target)) {
                    unset($manifest['files'][$key]);

                    continue;
                }

                $installedSha = is_array($record) ? ($record['installed_sha'] ?? null) : null;

                if (! is_string($installedSha) || hash_file('sha256', $target) !== $installedSha) {
                    $plan->conflict("Managed file [{$key}] is modified and will not be deleted.");

                    continue;
                }

                $plan->delete($target);
                unset($manifest['files'][$key]);
            }
        }

        if (is_file($manifestPath)) {
            ksort($manifest['files']);
            $plan->replace(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
            );
        }
    }

    /** @return array{surfaces: array<string, string>, files: array<string, mixed>} */
    private function readManifest(string $path, ProfileTransitionPlan $plan): array
    {
        if (! is_file($path)) {
            return ['surfaces' => [], 'files' => []];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (! is_array($manifest) || ! is_array($manifest['surfaces'] ?? null) || ! is_array($manifest['files'] ?? null)) {
            $plan->conflict("Resync manifest [{$path}] is malformed.");

            return ['surfaces' => [], 'files' => []];
        }

        return $manifest;
    }

    private function relativeKey(string $target): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        $relative = str_starts_with($target, $base) ? substr($target, strlen($base)) : $target;

        return str_replace('\\', '/', $relative);
    }

    private function targetPath(string $key): string
    {
        if (str_starts_with($key, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $key) === 1) {
            return $key;
        }

        return base_path($key);
    }
}
