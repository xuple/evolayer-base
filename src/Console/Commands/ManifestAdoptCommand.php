<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Xuple\EvoLayer\Base\Support\LegacyManifestAdoptionCatalog;
use Xuple\EvoLayer\Base\Support\ManagedMutationLock;
use Xuple\EvoLayer\Base\Support\ManagedMutationLockException;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\FilePrecondition;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutionException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionPlan;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataRepository;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\ResyncManifestException;
use Xuple\EvoLayer\Base\Support\ResyncManifestRepository;

#[Signature('evolayer:manifest:adopt {--pristine-only : Adopt only exact checksums from reviewed public distributions}')]
#[Description('Repair missing legacy manifest provenance when exact historical checksums prove ownership.')]
final class ManifestAdoptCommand extends Command
{
    public function handle(
        PublishMap $map,
        ResyncManifestRepository $manifests,
        LegacyManifestAdoptionCatalog $catalog,
        ManagedMutationLock $lock,
        ManagedPathPolicy $paths,
        ProfileTransitionExecutor $executor,
        ProjectMetadataRepository $metadata,
    ): int {
        if (! (bool) $this->option('pristine-only')) {
            $this->components->error('Adoption requires --pristine-only; similarity never grants deletion authority.');

            return self::FAILURE;
        }

        try {
            return $lock->run($map, function () use ($map, $manifests, $catalog, $paths, $executor, $metadata): int {
                $manifestPrecondition = FilePrecondition::capture($map->manifestPath());
                $manifest = $manifests->read($map);
                $projectMetadata = $metadata->read($map);
                $packageVersion = is_string($manifest['package_version'] ?? null)
                    ? $manifest['package_version']
                    : '';
                $adopted = [];
                $conflicts = [];

                foreach ($map->managedFiles() as $key => $file) {
                    if (isset($manifest['files'][$key])
                        || ($manifest['surfaces'][$file['surface']] ?? null) === 'ejected') {
                        continue;
                    }

                    $paths->assertReadableDescriptorTarget($file['target'], $map);
                    $precondition = FilePrecondition::capture($file['target']);

                    if (! $precondition->exists) {
                        continue;
                    }

                    $checksum = (string) $precondition->sha256;
                    $sourceChecksum = $catalog->sourceChecksum($packageVersion, $projectMetadata, $key, $checksum);

                    if ($sourceChecksum === null) {
                        $conflicts[] = "[{$key}] cannot be proven pristine; eject, restore exact public bytes, or reconcile it manually.";

                        continue;
                    }

                    $manifest['files'][$key] = [
                        'surface' => $file['surface'],
                        'source_sha' => $sourceChecksum,
                        'installed_sha' => $checksum,
                    ];
                    $adopted[$file['target']] = $precondition;
                }

                if ($conflicts !== []) {
                    $this->components->error('Manifest adoption found unresolved provenance.');
                    $this->components->bulletList($conflicts);

                    return self::FAILURE;
                }

                if ($adopted === []) {
                    $this->components->info('No missing pristine provenance required adoption.');

                    return self::SUCCESS;
                }

                $plan = new ProfileTransitionPlan;

                foreach ($adopted as $target => $precondition) {
                    $plan->guard($target, $precondition);
                }

                $plan->replace(
                    $map->manifestPath(),
                    $manifests->encode($manifest),
                    $manifestPrecondition,
                );
                $executor->execute($plan);
                $count = count($adopted);
                $this->components->info("Adopted {$count} exact pristine managed file(s).");

                return self::SUCCESS;
            });
        } catch (ManagedMutationLockException|ManagedPathException|ProfileTransitionExecutionException|ProjectMetadataException|ResyncManifestException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
