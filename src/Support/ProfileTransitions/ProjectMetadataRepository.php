<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use JsonException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;
use Xuple\EvoLayer\Base\Support\PublishMap;

final readonly class ProjectMetadataRepository
{
    public const SCHEMA_VERSION = 2;

    public function __construct(private ManagedPathPolicy $paths) {}

    /** @return array<string, mixed> */
    public function read(PublishMap $map): array
    {
        $path = $map->projectMetadataPath();
        $this->paths->assertReadableFileOrMissing($path);

        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new ProjectMetadataException("Unable to read project metadata [{$path}].");
        }

        try {
            $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProjectMetadataException("Project metadata [{$path}] contains invalid JSON.", previous: $exception);
        }

        if (! is_array($metadata)) {
            throw new ProjectMetadataException("Project metadata [{$path}] must contain a JSON object.");
        }

        if (array_key_exists('schema_version', $metadata)) {
            $this->validateSchemaTwo($metadata, $path);
        } elseif (array_key_exists('mode', $metadata) && $metadata['mode'] !== 'application') {
            throw new ProjectMetadataException("Legacy project metadata [{$path}] has an unsupported identity mode.");
        }

        return $metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function encode(array $metadata): string
    {
        try {
            return json_encode(
                $metadata,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (JsonException $exception) {
            throw new ProjectMetadataException('Unable to encode project metadata.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function validateSchemaTwo(array $metadata, string $path): void
    {
        if (($metadata['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new ProjectMetadataException("Project metadata [{$path}] uses an unsupported schema version.");
        }

        if (! is_string($metadata['kind'] ?? null)
            || ! is_string($metadata['profile'] ?? null)
            || ! is_array($metadata['overrides'] ?? null)
            || ! is_array($metadata['overrides']['examples'] ?? null)
            || ! is_array($metadata['overrides']['features'] ?? null)
            || ! is_array($metadata['applied_with'] ?? null)) {
            throw new ProjectMetadataException("Project metadata [{$path}] does not satisfy schema version 2.");
        }
    }
}
