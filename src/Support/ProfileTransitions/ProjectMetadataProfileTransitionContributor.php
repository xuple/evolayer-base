<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Composer\InstalledVersions;
use Xuple\EvoLayer\Base\Contracts\ProfileTransitionContributor;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\PublishMap;

final readonly class ProjectMetadataProfileTransitionContributor implements ProfileTransitionContributor
{
    public function __construct(
        private PublishMap $map,
        private ProjectMetadataRepository $metadata,
    ) {}

    public function id(): string
    {
        return 'base.project-intent';
    }

    public function apiVersion(): int
    {
        return 1;
    }

    public function provides(): array
    {
        return ['profile.committed-intent'];
    }

    public function requires(): array
    {
        return [];
    }

    public function priority(): int
    {
        return 50;
    }

    public function contribute(ProfileTransitionContext $context, ProfileTransitionPlan $plan): void
    {
        $path = $this->map->projectMetadataPath();

        try {
            $precondition = FilePrecondition::capture($path);
            $metadata = $this->metadata->read($this->map);
        } catch (ProjectMetadataException|ManagedPathException $exception) {
            $plan->conflict($exception->getMessage());

            return;
        }

        $kind = $metadata['kind'] ?? null;

        if ($kind === null && ($metadata['mode'] ?? null) === 'application') {
            $kind = 'generated-application';
        }

        if ($kind !== null && ! is_string($kind)) {
            $plan->conflict('Project metadata contains an invalid repository identity.');

            return;
        }

        unset($metadata['mode'], $metadata['verification'], $metadata['profile_migration']);
        $metadata['schema_version'] = ProjectMetadataRepository::SCHEMA_VERSION;
        $metadata['kind'] = $kind ?? 'package-host';
        $metadata['profile'] = $context->profile;
        $metadata['overrides'] = [
            'examples' => $context->exampleOverrides,
            'features' => $context->featureOverrides,
        ];
        $recordedStarterVersion = $metadata['applied_with']['starter'] ?? null;

        $metadata['applied_with'] = array_filter([
            'base' => $this->packageVersion('xuple/evolayer-base'),
            // The starter version is only derivable while the root package is
            // still the starter. Once a generated application claims its own
            // Composer name — the documented `composer config name app/<app>`
            // step — derivation becomes impossible, so fall back to the value
            // recorded at install time. The starter an application was
            // generated from is a historical fact and cannot change later.
            'starter' => $this->rootPackageVersion('xuple/evolayer-base-starter')
                ?? (is_string($recordedStarterVersion) ? $recordedStarterVersion : null),
        ], fn (?string $version): bool => $version !== null);

        $plan->replace($path, $this->metadata->encode($metadata), $precondition);
    }

    private function packageVersion(string $package): ?string
    {
        return InstalledVersions::isInstalled($package)
            ? InstalledVersions::getPrettyVersion($package)
            : null;
    }

    private function rootPackageVersion(string $package): ?string
    {
        $root = InstalledVersions::getRootPackage();

        return ($root['name'] ?? null) === $package ? ($root['pretty_version'] ?? null) : null;
    }
}
