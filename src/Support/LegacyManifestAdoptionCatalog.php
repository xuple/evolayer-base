<?php

namespace Xuple\EvoLayer\Base\Support;

use InvalidArgumentException;

final class LegacyManifestAdoptionCatalog
{
    /**
     * Exact public-distribution checksums. Keys are Base version, Starter
     * version, manifest key, then an installed checksum; values are the
     * historical package source SHA.
     *
     * @var array<string, array<string, array<string, array<string, string>>>>
     */
    private const RECORDS = [
        '0.1.9' => [
            '0.1.19' => [
                'resources/js/pages/evolayer/contact.tsx' => [
                    'fd4168d98fe6c5f5e17883e801e75c02a169ffe080c5c843fe5eac208e5e356f' => 'fd4168d98fe6c5f5e17883e801e75c02a169ffe080c5c843fe5eac208e5e356f',
                    'f0f1e6bc09931c1ca1ba0422dfe2c75c0d5bc37792880f212c49e6efb8d5f88d' => 'fd4168d98fe6c5f5e17883e801e75c02a169ffe080c5c843fe5eac208e5e356f',
                ],
            ],
        ],
    ];

    /** @var array<string, array<string, array<string, array<string, string>>>> */
    private readonly array $records;

    /** @param array<string, array<string, array<string, array<string, string>>>>|null $records */
    public function __construct(?array $records = null)
    {
        $records ??= self::RECORDS;

        foreach ($records as $version => $distributions) {
            if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) !== 1 || ! is_array($distributions)) {
                throw new InvalidArgumentException('Legacy adoption catalog contains an invalid package version.');
            }

            foreach ($distributions as $distributionVersion => $files) {
                if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $distributionVersion) !== 1 || ! is_array($files)) {
                    throw new InvalidArgumentException('Legacy adoption catalog contains an invalid distribution version.');
                }

                foreach ($files as $key => $checksums) {
                    if (! is_string($key)
                        || $key === ''
                        || str_contains($key, "\0")
                        || str_contains($key, '\\')
                        || str_starts_with($key, '/')
                        || preg_match('#(^|/)(?:\.|\.\.)(?:/|$)#', $key) === 1
                        || ! is_array($checksums)) {
                        throw new InvalidArgumentException('Legacy adoption catalog contains an invalid managed path.');
                    }

                    foreach ($checksums as $installedChecksum => $sourceChecksum) {
                        if (! is_string($installedChecksum)
                            || ! is_string($sourceChecksum)
                            || preg_match('/^[a-f0-9]{64}$/', $installedChecksum) !== 1
                            || preg_match('/^[a-f0-9]{64}$/', $sourceChecksum) !== 1) {
                            throw new InvalidArgumentException('Legacy adoption catalog contains an invalid checksum.');
                        }
                    }
                }
            }
        }

        $this->records = $records;
    }

    /** @param array<string, mixed> $projectMetadata */
    public function sourceChecksum(
        string $packageVersion,
        array $projectMetadata,
        string $key,
        string $installedChecksum,
    ): ?string {
        $version = ltrim($packageVersion, 'v');
        $frameworkVersion = ltrim((string) ($projectMetadata['framework_version'] ?? ''), 'v');
        $distributionVersion = ltrim((string) ($projectMetadata['distribution_version'] ?? ''), 'v');

        if (($projectMetadata['distribution'] ?? null) !== 'xuple/evolayer-base-starter'
            || ($projectMetadata['framework'] ?? null) !== 'xuple/evolayer-base'
            || $frameworkVersion !== $version) {
            return null;
        }

        return $this->records[$version][$distributionVersion][$key][$installedChecksum] ?? null;
    }
}
