<?php

namespace Xuple\EvoLayer\Base\Support;

final class ManagedPathPolicy
{
    private const DIRECTORY = 0040000;

    private const REGULAR_FILE = 0100000;

    private const TYPE_MASK = 0170000;

    public function assertDescriptorSource(string $path, PublishMap $map): void
    {
        $this->assertWithinRoot($path, $map->packageRoot(), 'package');
        $this->assertRegularFile($path);
    }

    public function assertDescriptorTarget(string $path, PublishMap $map): void
    {
        $this->assertWithinRoot($path, $map->hostRoot(), 'host');
        $this->assertMutationTarget($path);
    }

    public function assertReadableDescriptorTarget(string $path, PublishMap $map): void
    {
        $this->assertWithinRoot($path, $map->hostRoot(), 'host');
        $this->assertReadableFileOrMissing($path);
    }

    public function assertHostMutationTarget(string $path, PublishMap $map): void
    {
        $this->assertWithinRoot($path, $map->hostRoot(), 'host');
        $this->assertMutationTarget($path);
    }

    public function assertReadableHostTarget(string $path, PublishMap $map): void
    {
        $this->assertWithinRoot($path, $map->hostRoot(), 'host');
        $this->assertReadableFileOrMissing($path);
    }

    public function assertRegularFile(string $path): void
    {
        $this->assertMutationTarget($path, mustExist: true);
    }

    public function assertReadableFileOrMissing(string $path): void
    {
        $this->inspect($path, mustExist: false, mutation: false);
    }

    public function assertMutationTarget(string $path, bool $mustExist = false): void
    {
        $this->inspect($path, $mustExist, mutation: true);
    }

    private function inspect(string $path, bool $mustExist, bool $mutation): void
    {
        if ($mutation && PHP_OS_FAMILY === 'Windows') {
            throw new ManagedPathException('Managed file mutation is unsupported on Windows until replacement and reparse-point safety is verified in Windows CI.');
        }

        $normalized = $this->normalizeAbsolutePath($path);
        $parts = explode('/', ltrim($normalized, '/'));
        $current = '';
        $missing = false;

        foreach ($parts as $index => $part) {
            $current .= '/'.$part;
            $isTarget = $index === array_key_last($parts);
            $stat = @lstat($current);

            if ($stat === false) {
                $missing = true;

                continue;
            }

            if ($missing) {
                throw new ManagedPathException("Managed path [{$path}] changed while its ancestors were inspected.");
            }

            $type = $stat['mode'] & self::TYPE_MASK;

            if (! $isTarget && $type !== self::DIRECTORY) {
                throw new ManagedPathException("Managed path [{$path}] has a linked or non-directory ancestor [{$current}].");
            }

            if ($isTarget && $type !== self::REGULAR_FILE) {
                throw new ManagedPathException("Managed target [{$path}] must be a regular, non-linked file.");
            }

            if ($isTarget && ($stat['nlink'] ?? 0) !== 1) {
                throw new ManagedPathException("Managed target [{$path}] has multiple hard links and cannot be mutated safely.");
            }
        }

        if ($mustExist && $missing) {
            throw new ManagedPathException("Managed source [{$path}] does not exist.");
        }
    }

    private function assertWithinRoot(string $path, string $root, string $label): void
    {
        $normalizedPath = $this->normalizeAbsolutePath($path);
        $normalizedRoot = rtrim($this->normalizeAbsolutePath($root), '/');

        if (! str_starts_with($normalizedPath, $normalizedRoot.'/')) {
            throw new ManagedPathException("Managed path [{$path}] escapes the trusted {$label} root [{$root}].");
        }
    }

    private function normalizeAbsolutePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || ! str_starts_with($path, '/')) {
            throw new ManagedPathException("Managed path [{$path}] is not a supported absolute path.");
        }

        if (preg_match('#(^|/)(?:\.|\.\.)(?:/|$)#', $path) === 1 || str_contains($path, '//')) {
            throw new ManagedPathException("Managed path [{$path}] contains an unsafe lexical alias.");
        }

        return rtrim($path, '/');
    }
}
