<?php

namespace Xuple\EvoLayer\Base\Support;

use Throwable;

final readonly class ManagedMutationLock
{
    public function __construct(private ManagedPathPolicy $paths) {}

    public function run(PublishMap $map, callable $callback): mixed
    {
        $path = rtrim($map->hostRoot(), '/').'/.evolayer/mutation.lock';
        $directory = dirname($path);

        try {
            $this->paths->assertMutationTarget($path);

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new ManagedMutationLockException("Unable to create mutation lock directory [{$directory}].");
            }

            $this->paths->assertMutationTarget($path);
            $handle = @fopen($path, 'c+');
        } catch (ManagedPathException $exception) {
            throw new ManagedMutationLockException('The EvoLayer mutation lock path is unsafe.', previous: $exception);
        }

        if ($handle === false) {
            throw new ManagedMutationLockException("Unable to open mutation lock [{$path}].");
        }

        try {
            clearstatcache(true, $path);
            $pathStat = @lstat($path);
            $handleStat = fstat($handle);

            if ($pathStat === false
                || $handleStat === false
                || ($pathStat['mode'] & 0170000) !== 0100000
                || ($pathStat['nlink'] ?? 0) !== 1
                || $pathStat['dev'] !== $handleStat['dev']
                || $pathStat['ino'] !== $handleStat['ino']) {
                throw new ManagedMutationLockException("Mutation lock [{$path}] is not a safe regular file.");
            }

            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                throw new ManagedMutationLockException('Another EvoLayer profile, resync, or eject operation is already running.');
            }

            try {
                return $callback();
            } finally {
                flock($handle, LOCK_UN);
            }
        } catch (Throwable $exception) {
            if ($exception instanceof ManagedMutationLockException) {
                throw $exception;
            }

            throw $exception;
        } finally {
            fclose($handle);
        }
    }
}
