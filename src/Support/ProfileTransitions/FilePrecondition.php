<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use RuntimeException;
use Xuple\EvoLayer\Base\Support\ManagedPathException;

final readonly class FilePrecondition
{
    private function __construct(
        public bool $exists,
        public ?int $device,
        public ?int $inode,
        public ?int $mode,
        public ?int $links,
        public ?int $size,
        public ?string $sha256,
    ) {}

    public static function capture(string $path): self
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);

        if ($stat === false) {
            return new self(false, null, null, null, null, null, null);
        }

        if (($stat['mode'] & 0170000) !== 0100000 || ($stat['nlink'] ?? 0) !== 1) {
            throw new ManagedPathException("Transition path [{$path}] must be a regular file with one hard link.");
        }

        $checksum = hash_file('sha256', $path);

        if ($checksum === false) {
            throw new ManagedPathException("Unable to fingerprint transition path [{$path}].");
        }

        return new self(
            true,
            $stat['dev'],
            $stat['ino'],
            $stat['mode'],
            $stat['nlink'],
            $stat['size'],
            $checksum,
        );
    }

    public function assertMatches(string $path): void
    {
        $current = self::capture($path);

        if ($current != $this) {
            throw new RuntimeException("Transition path [{$path}] changed after the plan was created.");
        }
    }
}
