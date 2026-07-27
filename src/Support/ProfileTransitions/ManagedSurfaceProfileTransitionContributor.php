<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;
use Xuple\EvoLayer\Base\Support\ManagedSurface;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\ResyncManifestException;
use Xuple\EvoLayer\Base\Support\ResyncManifestRepository;

final readonly class ManagedSurfaceProfileTransitionContributor implements ProfileTransitionContributor
{
    public function __construct(
        private PublishMap $map,
        private ResyncManifestRepository $manifests,
        private ManagedPathPolicy $paths,
    ) {}

    public function id(): string
    {
        return 'base.managed-surfaces';
    }

    public function apiVersion(): int
    {
        return 1;
    }

    public function provides(): array
    {
        return ['profile.managed-surfaces'];
    }

    public function requires(): array
    {
        return [];
    }

    public function priority(): int
    {
        return 200;
    }

    public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
    {
        $manifestPath = $this->map->manifestPath();

        try {
            $manifestPrecondition = FilePrecondition::capture($manifestPath);
            $manifest = $this->manifests->read($this->map);
        } catch (ResyncManifestException|ManagedPathException $exception) {
            $plan->conflict($exception->getMessage());

            return;
        }

        $surfaces = collect($this->map->surfaces())
            ->filter(fn (ManagedSurface $surface): bool => $surface->ejectable);
        $enabledSurfaces = $surfaces
            ->filter(fn (ManagedSurface $surface): bool => $context->targetExamples[$surface->configKey] ?? false);
        $disabledSurfaces = $surfaces
            ->reject(fn (ManagedSurface $surface): bool => $context->targetExamples[$surface->configKey] ?? false);
        $manifestChanged = false;

        foreach ($enabledSurfaces as $surface) {
            $ejected = ($manifest['surfaces'][$surface->id] ?? null) === 'ejected';

            foreach ($this->map->expand($surface->paths) as $source => $target) {
                if ($ejected) {
                    continue;
                }

                try {
                    $this->paths->assertDescriptorSource($source, $this->map);
                    $this->paths->assertDescriptorTarget($target, $this->map);
                } catch (ManagedPathException $exception) {
                    $plan->conflict($exception->getMessage());

                    continue;
                }

                $key = $this->map->manifestKey($target);
                $precondition = FilePrecondition::capture($target);

                if ($precondition->exists) {
                    continue;
                }

                $sourceContents = @file_get_contents($source);

                if ($sourceContents === false) {
                    $plan->conflict("Managed source for [{$key}] is unreadable.");

                    continue;
                }

                $sourceSha = hash('sha256', $sourceContents);

                $plan->replace($target, $sourceContents, $precondition);
                $manifest['files'][$key] = [
                    'surface' => $surface->id,
                    'source_sha' => $sourceSha,
                    'installed_sha' => $sourceSha,
                ];
                $manifestChanged = true;
            }
        }

        foreach ($disabledSurfaces as $surface) {
            if (($manifest['surfaces'][$surface->id] ?? null) === 'ejected') {
                $plan->conflict("Managed surface [{$surface->id}] is ejected and cannot be disabled automatically.");

                continue;
            }

            foreach ($this->map->expand($surface->paths) as $source => $target) {
                try {
                    $this->paths->assertDescriptorSource($source, $this->map);
                    $this->paths->assertDescriptorTarget($target, $this->map);
                } catch (ManagedPathException $exception) {
                    $plan->conflict($exception->getMessage());

                    continue;
                }

                $key = $this->map->manifestKey($target);
                $record = $manifest['files'][$key] ?? null;
                $precondition = FilePrecondition::capture($target);

                if (! $precondition->exists) {
                    unset($manifest['files'][$key]);
                    $manifestChanged = $manifestChanged || is_array($record);

                    continue;
                }

                if (! is_array($record)) {
                    $plan->conflict("Managed file [{$key}] has unknown provenance; it was not recorded in the resync manifest.");

                    continue;
                }

                $installedSha = $record['installed_sha'];

                if (! is_string($installedSha) || $precondition->sha256 !== $installedSha) {
                    $plan->conflict("Managed file [{$key}] is modified and will not be deleted.");

                    continue;
                }

                $plan->delete($target, $precondition);
                unset($manifest['files'][$key]);
                $manifestChanged = true;
            }
        }

        if ($manifestChanged) {
            $plan->replace($manifestPath, $this->manifests->encode($manifest), $manifestPrecondition);
        }
    }
}
