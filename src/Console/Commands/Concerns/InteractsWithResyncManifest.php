<?php

namespace Xuple\EvoLayer\Base\Console\Commands\Concerns;

/**
 * Read/write helpers for the host app's resync manifest
 * (.evolayer/resync.lock.json). Shared by evolayer:resync and evolayer:eject.
 */
trait InteractsWithResyncManifest
{
    /**
     * @return array{surfaces: array<string, string>, files: array<string, array<string, string>>}
     */
    protected function readManifest(string $path): array
    {
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);

            if (is_array($data)) {
                return $data + ['surfaces' => [], 'files' => []];
            }
        }

        return ['surfaces' => [], 'files' => []];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function writeManifest(string $path, array $manifest): void
    {
        if (isset($manifest['files']) && is_array($manifest['files'])) {
            ksort($manifest['files']);
        }

        @mkdir(dirname($path), 0755, true);

        file_put_contents(
            $path,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    /**
     * Manifest key for a target file: path relative to the host base_path,
     * normalised to forward slashes.
     */
    protected function relativeKey(string $target): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        $relative = str_starts_with($target, $base) ? substr($target, strlen($base)) : $target;

        return str_replace('\\', '/', $relative);
    }
}
