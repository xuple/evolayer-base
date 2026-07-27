<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Xuple\EvoLayer\Base\Support\ManagedMutationLock;
use Xuple\EvoLayer\Base\Support\ManagedMutationLockException;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\FilePrecondition;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionConflict;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutionException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionPlan;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\ResyncManifestException;
use Xuple\EvoLayer\Base\Support\ResyncManifestRepository;

#[Signature('evolayer:eject {surface : The example surface to take ownership of}')]
#[Description('Take ownership of a managed example surface so evolayer:resync no longer overwrites it.')]
class EjectCommand extends Command
{
    public function handle(
        PublishMap $map,
        ResyncManifestRepository $manifests,
        ManagedPathPolicy $paths,
        ManagedMutationLock $lock,
        ProfileTransitionExecutor $executor,
    ): int {
        try {
            return $lock->run($map, fn (): int => $this->runEject($map, $manifests, $paths, $executor));
        } catch (ManagedMutationLockException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function runEject(
        PublishMap $map,
        ResyncManifestRepository $manifests,
        ManagedPathPolicy $paths,
        ProfileTransitionExecutor $executor,
    ): int {
        $surface = (string) $this->argument('surface');
        $features = $map->features();

        if (! isset($features[$surface])) {
            $this->components->error("Unknown or non-ejectable surface [{$surface}]. Core runtime is never ejectable; pick an example surface:");
            $this->components->bulletList($map->ejectableSurfaces());

            return self::FAILURE;
        }

        try {
            foreach ($map->expand($features[$surface]) as $source => $target) {
                $paths->assertDescriptorSource($source, $map);
                $paths->assertDescriptorTarget($target, $map);
            }
        } catch (ManagedPathException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $manifestPrecondition = FilePrecondition::capture($map->manifestPath());
            $manifest = $manifests->read($map);
        } catch (ResyncManifestException|ManagedPathException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (($manifest['surfaces'][$surface] ?? null) === 'ejected') {
            $this->components->info("[{$surface}] is already ejected — you own it.");

            return self::SUCCESS;
        }

        $plan = new ProfileTransitionPlan;

        foreach ($map->expand($features[$surface]) as $source => $target) {
            $sourceContents = @file_get_contents($source);

            if ($sourceContents === false) {
                $this->components->error("Unable to read managed source [{$source}].");

                return self::FAILURE;
            }

            $sourceSha = hash('sha256', $sourceContents);
            $targetPrecondition = FilePrecondition::capture($target);

            // Make sure the host actually has the files before handing ownership
            // over; if they never published this surface, materialise it now.
            if (! $targetPrecondition->exists) {
                $plan->replace($target, $sourceContents, $targetPrecondition);
            } else {
                $plan->guard($target, $targetPrecondition);
            }

            $manifest['files'][$map->manifestKey($target)] = [
                'surface' => $surface,
                'source_sha' => $sourceSha,
                'installed_sha' => $targetPrecondition->exists
                    ? $targetPrecondition->sha256
                    : $sourceSha,
            ];
        }

        $manifest['surfaces'][$surface] = 'ejected';
        $plan->replace($map->manifestPath(), $manifests->encode($manifest), $manifestPrecondition);

        try {
            $executor->execute($plan);
        } catch (ProfileTransitionConflict|ProfileTransitionExecutionException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->warn("Ejected [{$surface}]. Those files are now app-owned — evolayer:resync will no longer update them, so you forfeit managed updates for this surface.");

        return self::SUCCESS;
    }
}
