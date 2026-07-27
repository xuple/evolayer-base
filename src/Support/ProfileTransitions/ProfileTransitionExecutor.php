<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use RuntimeException;
use Throwable;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;

class ProfileTransitionExecutor
{
    public function __construct(private readonly ?ManagedPathPolicy $paths = null) {}

    public function execute(ProfileTransitionPlan $plan, bool $dryRun = false): ProfileTransitionResult
    {
        if ($plan->conflicts() !== []) {
            throw new ProfileTransitionConflict($plan->conflicts());
        }

        $paths = $plan->affectedPaths();

        if ($dryRun) {
            return new ProfileTransitionResult(
                true,
                $paths,
                count($plan->replacements()),
                count($plan->deletions()),
            );
        }

        try {
            $this->assertGuards($plan);
            $this->beforeSnapshot();
            $snapshots = $this->snapshot($plan, $paths);
        } catch (Throwable $exception) {
            throw new ProfileTransitionExecutionException($exception, []);
        }

        $createdDirectories = [];
        $mutatedPaths = [];
        $mutation = 0;

        try {
            foreach ($plan->replacements() as $path => $contents) {
                $createdDirectories = [...$createdDirectories, ...$this->ensureParentDirectory($path)];
                $this->beforeMutation($path, $mutation + 1);
                $this->atomicWrite(
                    $path,
                    $contents,
                    $snapshots[$path]['mode'],
                    $plan->precondition($path),
                    fn () => $this->assertGuards($plan),
                );
                $mutatedPaths[] = $path;
                $this->afterMutation($path, ++$mutation);
            }

            foreach ($plan->deletions() as $path) {
                $this->beforeMutation($path, $mutation + 1);
                $this->assertGuards($plan);
                $plan->precondition($path)->assertMatches($path);

                if (file_exists($path) && ! unlink($path)) {
                    throw new RuntimeException("Unable to delete [{$path}].");
                }

                if ($snapshots[$path]['exists']) {
                    $mutatedPaths[] = $path;
                }

                $this->afterMutation($path, ++$mutation);
            }
        } catch (Throwable $exception) {
            $rollbackFailures = $this->rollback(
                $snapshots,
                array_values(array_unique($mutatedPaths)),
                array_values(array_unique($createdDirectories)),
            );

            throw new ProfileTransitionExecutionException($exception, $rollbackFailures);
        }

        return new ProfileTransitionResult(
            false,
            $paths,
            count($plan->replacements()),
            count($plan->deletions()),
        );
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, array{exists: bool, contents: ?string, mode: ?int}>
     */
    private function snapshot(ProfileTransitionPlan $plan, array $paths): array
    {
        $snapshots = [];

        foreach ($paths as $path) {
            $this->pathPolicy()->assertMutationTarget($path);
            $plan->precondition($path)->assertMatches($path);
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
     * @param  list<string>  $mutatedPaths
     * @param  list<string>  $createdDirectories
     * @return list<string>
     */
    private function rollback(array $snapshots, array $mutatedPaths, array $createdDirectories): array
    {
        $failures = [];

        foreach (array_reverse($mutatedPaths) as $path) {
            try {
                $this->restoreSnapshot($path, $snapshots[$path]);
            } catch (Throwable $exception) {
                $failures[] = "[{$path}]: {$exception->getMessage()}";
            }
        }

        foreach ($createdDirectories as $directory) {
            try {
                if (is_dir($directory) && count(scandir($directory) ?: []) === 2 && ! rmdir($directory)) {
                    throw new RuntimeException("Unable to remove created directory [{$directory}].");
                }
            } catch (Throwable $exception) {
                $failures[] = "[{$directory}]: {$exception->getMessage()}";
            }
        }

        return $failures;
    }

    /** @param array{exists: bool, contents: ?string, mode: ?int} $snapshot */
    protected function restoreSnapshot(string $path, array $snapshot): void
    {
        $this->pathPolicy()->assertMutationTarget($path);

        if ($snapshot['exists']) {
            $this->ensureParentDirectory($path);
            $this->atomicWrite($path, (string) $snapshot['contents'], $snapshot['mode']);
        } elseif (file_exists($path) && ! unlink($path)) {
            throw new RuntimeException("Unable to remove newly created file [{$path}] during rollback.");
        }
    }

    private function atomicWrite(
        string $path,
        string $contents,
        ?int $mode,
        ?FilePrecondition $precondition = null,
        ?callable $beforeCommit = null,
    ): void {
        $this->pathPolicy()->assertMutationTarget($path);
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

            $beforeCommit?->__invoke();
            $this->pathPolicy()->assertMutationTarget($path);
            $precondition?->assertMatches($path);

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

            $this->pathPolicy()->assertMutationTarget($path);
        }

        $this->pathPolicy()->assertMutationTarget($path);

        return $missing;
    }

    protected function afterMutation(string $path, int $mutation): void {}

    protected function beforeSnapshot(): void {}

    protected function beforeMutation(string $path, int $mutation): void {}

    private function assertGuards(ProfileTransitionPlan $plan): void
    {
        foreach ($plan->guards() as $path => $precondition) {
            $precondition->assertMatches($path);
        }
    }

    private function pathPolicy(): ManagedPathPolicy
    {
        return $this->paths ?? new ManagedPathPolicy;
    }
}
