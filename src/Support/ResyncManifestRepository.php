<?php

namespace Xuple\EvoLayer\Base\Support;

use JsonException;
use Throwable;

final class ResyncManifestRepository
{
    public const SCHEMA_VERSION = 1;

    public function __construct(private readonly ManagedPathPolicy $paths) {}

    /** @return array<string, mixed> */
    public function read(PublishMap $map): array
    {
        $path = $map->manifestPath();
        $this->paths->assertReadableFileOrMissing($path);

        if (! file_exists($path) && ! is_link($path)) {
            $manifest = $this->emptyManifest();
            $this->validateAgainstDescriptors($manifest, $map, $path);

            return $manifest;
        }

        if (is_link($path) || ! is_file($path)) {
            throw new ResyncManifestException("Resync manifest [{$path}] must be a regular, non-linked file.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new ResyncManifestException("Unable to read resync manifest [{$path}].");
        }

        try {
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ResyncManifestException("Resync manifest [{$path}] contains invalid JSON.", previous: $exception);
        }

        if (! is_array($manifest)) {
            throw new ResyncManifestException("Resync manifest [{$path}] must contain a JSON object.");
        }

        $this->validateSchema($manifest, $path);
        $this->validateAgainstDescriptors($manifest, $map, $path);

        return $manifest + $this->emptyManifest();
    }

    /** @param array<string, mixed> $manifest */
    public function encode(array $manifest): string
    {
        $manifest['schema_version'] = self::SCHEMA_VERSION;

        if (is_array($manifest['files'] ?? null)) {
            ksort($manifest['files']);
        }

        try {
            return json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (JsonException $exception) {
            throw new ResyncManifestException('Unable to encode the resync manifest.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $manifest */
    public function write(PublishMap $map, array $manifest): void
    {
        $path = $map->manifestPath();
        $this->paths->assertMutationTarget($path);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new ResyncManifestException("Unable to create resync manifest directory [{$directory}].");
        }

        $this->paths->assertMutationTarget($path);
        $temporary = tempnam($directory, '.evolayer-manifest-');

        if ($temporary === false) {
            throw new ResyncManifestException("Unable to stage resync manifest [{$path}].");
        }

        try {
            if (file_put_contents($temporary, $this->encode($manifest), LOCK_EX) === false) {
                throw new ResyncManifestException("Unable to write staged resync manifest [{$temporary}].");
            }

            $this->paths->assertMutationTarget($path);

            if (! rename($temporary, $path)) {
                throw new ResyncManifestException("Unable to atomically replace resync manifest [{$path}].");
            }
        } catch (Throwable $exception) {
            @unlink($temporary);

            throw $exception;
        }
    }

    /** @return array{schema_version: int, surfaces: array<string, string>, files: array<string, array<string, string>>} */
    private function emptyManifest(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'surfaces' => [],
            'files' => [],
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function validateSchema(array $manifest, string $path): void
    {
        $schemaVersion = $manifest['schema_version'] ?? self::SCHEMA_VERSION;

        if (! is_int($schemaVersion) || $schemaVersion !== self::SCHEMA_VERSION) {
            throw new ResyncManifestException("Resync manifest [{$path}] uses an unsupported schema version.");
        }

        if (! is_array($manifest['surfaces'] ?? null) || ! is_array($manifest['files'] ?? null)) {
            throw new ResyncManifestException("Resync manifest [{$path}] must contain surfaces and files objects.");
        }

        foreach ($manifest['surfaces'] as $surface => $status) {
            if (! is_string($surface) || ! is_string($status) || ! in_array($status, ['managed', 'ejected'], true)) {
                throw new ResyncManifestException("Resync manifest [{$path}] contains an invalid surface state.");
            }
        }

        foreach ($manifest['files'] as $key => $record) {
            if (! is_string($key) || ! is_array($record)) {
                throw new ResyncManifestException("Resync manifest [{$path}] contains an invalid file record.");
            }

            foreach (['surface', 'source_sha', 'installed_sha'] as $field) {
                if (! is_string($record[$field] ?? null)) {
                    throw new ResyncManifestException("Resync manifest file [{$key}] has an invalid [{$field}] value.");
                }
            }

            foreach (['source_sha', 'installed_sha'] as $field) {
                if (preg_match('/^[a-f0-9]{64}$/', $record[$field]) !== 1) {
                    throw new ResyncManifestException("Resync manifest file [{$key}] has an invalid [{$field}] checksum.");
                }
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function validateAgainstDescriptors(array $manifest, PublishMap $map, string $path): void
    {
        $allowed = array_map(fn (array $file): string => $file['surface'], $map->managedFiles());
        $surfaces = array_merge(['core' => $map->core()], $map->features());

        foreach ($manifest['surfaces'] as $surface => $status) {
            if ($surface === 'core' || ! array_key_exists($surface, $surfaces)) {
                throw new ResyncManifestException("Resync manifest [{$path}] references unknown ejectable surface [{$surface}].");
            }
        }

        foreach ($manifest['files'] as $key => $record) {
            $map->assertValidManifestKey($key);

            if (! isset($allowed[$key])) {
                throw new ResyncManifestException("Resync manifest [{$path}] references undescribed managed path [{$key}].");
            }

            if ($record['surface'] !== $allowed[$key]) {
                throw new ResyncManifestException("Resync manifest file [{$key}] disagrees with its descriptor surface.");
            }
        }
    }
}
