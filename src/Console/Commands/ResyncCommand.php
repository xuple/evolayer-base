<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Xuple\EvoLayer\Base\Support\ManagedMutationLock;
use Xuple\EvoLayer\Base\Support\ManagedMutationLockException;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ExpectedProfileStateResolver;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\FilePrecondition;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileRegistry;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileStateException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionConflict;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutionException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionPlan;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataRepository;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\ResyncManifestException;
use Xuple\EvoLayer\Base\Support\ResyncManifestRepository;

#[Signature('evolayer:resync
    {--dry-run : Show what would change without writing any files}
    {--force : Overwrite files you have modified locally}')]
#[Description('Re-publish package-managed frontend stubs without overwriting app-owned or ejected files.')]
class ResyncCommand extends Command
{
    public function handle(
        PublishMap $map,
        ResyncManifestRepository $manifests,
        ManagedPathPolicy $paths,
        ManagedMutationLock $lock,
        ProjectMetadataRepository $metadata,
        ProfileRegistry $profiles,
        ExpectedProfileStateResolver $expectedProfiles,
        ProfileTransitionExecutor $executor,
    ): int {
        if ((bool) $this->option('dry-run')) {
            return $this->runResync(
                $map,
                $manifests,
                $paths,
                $metadata,
                $profiles,
                $expectedProfiles,
                $executor,
            );
        }

        try {
            return $lock->run(
                $map,
                fn (): int => $this->runResync(
                    $map,
                    $manifests,
                    $paths,
                    $metadata,
                    $profiles,
                    $expectedProfiles,
                    $executor,
                ),
            );
        } catch (ManagedMutationLockException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function runResync(
        PublishMap $map,
        ResyncManifestRepository $manifests,
        ManagedPathPolicy $paths,
        ProjectMetadataRepository $metadata,
        ProfileRegistry $profiles,
        ExpectedProfileStateResolver $expectedProfiles,
        ProfileTransitionExecutor $executor,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        try {
            $manifestPrecondition = FilePrecondition::capture($map->manifestPath());
            $manifest = $manifests->read($map);
            $expected = $expectedProfiles->resolve($metadata->read($map), $profiles, $map);
        } catch (ResyncManifestException|ManagedPathException|ProjectMetadataException|ProfileStateException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        // Core first, then each ejectable feature surface.
        $surfaces = array_merge(['core' => $map->core()], $map->features());

        try {
            foreach ($surfaces as $pairs) {
                foreach ($map->expand($pairs) as $source => $target) {
                    $paths->assertDescriptorSource($source, $map);
                    $paths->assertDescriptorTarget($target, $map);
                }
            }
        } catch (ManagedPathException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->hasUnresolvedLegacyProvenance($manifest, $map)) {
            $this->components->error('Resync found unrecorded managed source from an older package manifest.');
            $this->line('  Run `php artisan evolayer:manifest:inspect`, then `php artisan evolayer:manifest:adopt --pristine-only` or explicitly eject/reconcile the reported source before resyncing.');

            return self::FAILURE;
        }

        $originalManifest = $manifest;

        $counts = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'kept (modified)' => 0,
            'skipped (ejected)' => 0,
            'skipped (disabled)' => 0,
        ];
        $plan = new ProfileTransitionPlan;

        foreach ($surfaces as $surface => $pairs) {
            $ejected = ($manifest['surfaces'][$surface] ?? 'managed') === 'ejected';
            $descriptor = $map->surfaces()[$surface] ?? null;
            $disabled = $surface !== 'core'
                && $expected !== null
                && $descriptor !== null
                && ! ($expected->examples[$descriptor->configKey] ?? false);

            foreach ($map->expand($pairs) as $source => $target) {
                if ($disabled) {
                    $counts['skipped (disabled)']++;

                    continue;
                }

                if ($ejected) {
                    $counts['skipped (ejected)']++;

                    continue;
                }

                $key = $map->manifestKey($target);
                $sourceSha = hash_file('sha256', $source);
                $targetPrecondition = FilePrecondition::capture($target);

                $action = $this->decide(
                    targetExists: $targetPrecondition->exists,
                    targetSha: $targetPrecondition->sha256,
                    sourceSha: $sourceSha,
                    recordedSha: $manifest['files'][$key]['installed_sha'] ?? null,
                    force: $force,
                );

                if ($action === 'modified') {
                    $counts['kept (modified)']++;
                    $this->components->warn("kept your changes: {$key} (use --force to overwrite, or `evolayer:eject` to own it)");

                    continue;
                }

                if ($action === 'unchanged') {
                    $counts['unchanged']++;
                    $plan->guard($target, $targetPrecondition);
                } else {
                    $counts[$action === 'create' ? 'created' : 'updated']++;
                    $contents = file_get_contents($source);

                    if ($contents === false) {
                        $this->components->error("Unable to read managed source [{$source}].");

                        return self::FAILURE;
                    }

                    $plan->replace($target, $contents, $targetPrecondition);
                }

                // Managed file we now own the provenance of: record source +
                // installed checksum so the next resync can tell pristine from
                // user-modified.
                $manifest['files'][$key] = [
                    'surface' => $surface,
                    'source_sha' => $sourceSha,
                    'installed_sha' => $sourceSha,
                ];
            }
        }

        $manifest['package_version'] = $this->packageVersion();

        if ($this->manifestChanged($originalManifest, $manifest)) {
            $manifest['generated_at'] = date('c');
            $plan->replace($map->manifestPath(), $manifests->encode($manifest), $manifestPrecondition);
        } else {
            $plan->guard($map->manifestPath(), $manifestPrecondition);
        }

        try {
            $executor->execute($plan, $dryRun);
        } catch (ProfileTransitionConflict|ProfileTransitionExecutionException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderSummary($counts, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Decide what to do with one file.
     *
     * @return 'create'|'update'|'unchanged'|'modified'
     */
    private function decide(bool $targetExists, ?string $targetSha, string $sourceSha, ?string $recordedSha, bool $force): string
    {
        if (! $targetExists) {
            return 'create';
        }

        if ($targetSha === $sourceSha) {
            return 'unchanged';
        }

        if ($force) {
            return 'update';
        }

        // Pristine: target still matches what we last installed, so the host
        // hasn't touched it — safe to update.
        if ($recordedSha !== null && $targetSha === $recordedSha) {
            return 'update';
        }

        // Differs from source and from what we installed (or was never tracked):
        // treat as host-modified and leave it alone.
        return 'modified';
    }

    private function packageVersion(): ?string
    {
        return InstalledVersions::isInstalled('xuple/evolayer-base')
            ? InstalledVersions::getPrettyVersion('xuple/evolayer-base')
            : null;
    }

    /** @param array<string, mixed> $manifest */
    private function hasUnresolvedLegacyProvenance(array $manifest, PublishMap $map): bool
    {
        if (($manifest['package_version'] ?? null) === $this->packageVersion()) {
            return false;
        }

        foreach ($map->managedFiles() as $key => $file) {
            if (isset($manifest['files'][$key])
                || ($manifest['surfaces'][$file['surface']] ?? null) === 'ejected') {
                continue;
            }

            if (FilePrecondition::capture($file['target'])->exists) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function manifestChanged(array $before, array $after): bool
    {
        unset($before['generated_at'], $after['generated_at']);

        return $before !== $after;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function renderSummary(array $counts, bool $dryRun): void
    {
        $this->newLine();
        $this->components->info($dryRun ? 'Resync dry-run — no files written:' : 'Resync complete:');

        foreach ($counts as $label => $count) {
            $this->components->twoColumnDetail(ucfirst($label), (string) $count);
        }
    }
}
