<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use RuntimeException;
use Throwable;

class ProfileTransitionExecutor
{
    public function execute(ProfileTransitionPlan $plan, bool $dryRun = false): ProfileTransitionResult
    {
        if ($plan->conflicts() !== []) {
            throw new ProfileTransitionConflict($plan->conflicts());
        }

        $paths = $plan->affectedPaths();

        if ($dryRun) {
            return new ProfileTransitionResult(true, $paths);
        }

        $snapshots = $this->snapshot($paths);
        $createdDirectories = [];
        $mutation = 0;

        try {
            foreach ($plan->replacements() as $path => $contents) {
                $createdDirectories = [...$createdDirectories, ...$this->ensureParentDirectory($path)];
                $this->atomicWrite($path, $contents, $snapshots[$path]['mode']);
                $this->afterMutation($path, ++$mutation);
            }

            foreach ($plan->deletions() as $path) {
                if (file_exists($path) && ! unlink($path)) {
                    throw new RuntimeException("Unable to delete [{$path}].");
                }

                $this->afterMutation($path, ++$mutation);
            }
        } catch (Throwable $exception) {
            $this->rollback($snapshots, array_values(array_unique($createdDirectories)));

            throw $exception;
        }

        return new ProfileTransitionResult(false, $paths);
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, array{exists: bool, contents: ?string, mode: ?int}>
     */
    private function snapshot(array $paths): array
    {
        $snapshots = [];

        foreach ($paths as $path) {
            $exists = is_file($path);
            $contents = $exists ? file_get_contents($path) : null;

            if ($exists && $contents === false) {
                throw new RuntimeException("Unable to snapshot [{$path}].");
            }

            $snapshots[$path] = [
                'exists' => $exists,
                'contents' => is_string($contents) ? $contents : null,
                'mode' => $exists ? (fileperms($path) & 0777) : null,
            ];
        }

        return $snapshots;
    }

    /**
     * @param  array<string, array{exists: bool, contents: ?string, mode: ?int}>  $snapshots
     * @param  list<string>  $createdDirectories
     */
    private function rollback(array $snapshots, array $createdDirectories): void
    {
        foreach (array_reverse($snapshots, true) as $path => $snapshot) {
            if ($snapshot['exists']) {
                $this->ensureParentDirectory($path);
                $this->atomicWrite($path, (string) $snapshot['contents'], $snapshot['mode']);
            } elseif (file_exists($path) && ! unlink($path)) {
                throw new RuntimeException("Unable to remove newly created file [{$path}] during rollback.");
            }
        }

        foreach ($createdDirectories as $directory) {
            if (is_dir($directory) && count(scandir($directory) ?: []) === 2) {
                rmdir($directory);
            }
        }
    }

    private function atomicWrite(string $path, string $contents, ?int $mode): void
    {
        $temporary = tempnam(dirname($path), '.evolayer-transition-');

        if ($temporary === false) {
            throw new RuntimeException("Unable to stage replacement for [{$path}].");
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new RuntimeException("Unable to write staged replacement for [{$path}].");
            }

            if (! chmod($temporary, $mode ?? 0644)) {
                throw new RuntimeException("Unable to set permissions on staged replacement for [{$path}].");
            }

            if (! rename($temporary, $path)) {
                throw new RuntimeException("Unable to atomically replace [{$path}].");
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @return list<string> */
    private function ensureParentDirectory(string $path): array
    {
        $directory = dirname($path);
        $missing = [];

        while (! is_dir($directory)) {
            $missing[] = $directory;
            $parent = dirname($directory);

            if ($parent === $directory) {
                throw new RuntimeException("Unable to resolve a writable parent for [{$path}].");
            }

            $directory = $parent;
        }

        foreach (array_reverse($missing) as $missingDirectory) {
            if (! mkdir($missingDirectory, 0755) && ! is_dir($missingDirectory)) {
                throw new RuntimeException("Unable to create directory [{$missingDirectory}].");
            }
        }

        return $missing;
    }

    protected function afterMutation(string $path, int $mutation): void {}
}
