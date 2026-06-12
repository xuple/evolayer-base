<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Xuple\EvoLayer\Base\Console\Commands\Concerns\InteractsWithResyncManifest;
use Xuple\EvoLayer\Base\Support\PublishMap;

#[Signature('evolayer:resync
    {--dry-run : Show what would change without writing any files}
    {--force : Overwrite files you have modified locally}')]
#[Description('Re-publish package-managed frontend stubs without overwriting app-owned or ejected files.')]
class ResyncCommand extends Command
{
    use InteractsWithResyncManifest;

    public function handle(PublishMap $map): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $manifest = $this->readManifest($map->manifestPath());

        // Core first, then each ejectable feature surface.
        $surfaces = array_merge(['core' => $map->core()], $map->features());

        $counts = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'kept (modified)' => 0,
            'skipped (ejected)' => 0,
        ];

        foreach ($surfaces as $surface => $pairs) {
            $ejected = ($manifest['surfaces'][$surface] ?? 'managed') === 'ejected';

            foreach ($map->expand($pairs) as $source => $target) {
                if ($ejected) {
                    $counts['skipped (ejected)']++;

                    continue;
                }

                $key = $this->relativeKey($target);
                $sourceSha = hash_file('sha256', $source);
                $targetExists = file_exists($target);

                $action = $this->decide(
                    targetExists: $targetExists,
                    targetSha: $targetExists ? hash_file('sha256', $target) : null,
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
                } else {
                    $counts[$action === 'create' ? 'created' : 'updated']++;

                    if (! $dryRun) {
                        $this->copyFile($source, $target);
                    }
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
        $manifest['generated_at'] = date('c');

        if (! $dryRun) {
            $this->writeManifest($map->manifestPath(), $manifest);
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

    private function copyFile(string $source, string $target): void
    {
        @mkdir(dirname($target), 0755, true);
        copy($source, $target);
    }

    private function packageVersion(): ?string
    {
        return InstalledVersions::isInstalled('xuple/evolayer-base')
            ? InstalledVersions::getPrettyVersion('xuple/evolayer-base')
            : null;
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
