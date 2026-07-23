<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Xuple\EvoLayer\Base\Support\LegacyManifestAdoptionCatalog;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\FilePrecondition;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataRepository;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\ResyncManifestException;
use Xuple\EvoLayer\Base\Support\ResyncManifestRepository;

#[Signature('evolayer:manifest:inspect {--json : Emit machine-readable provenance findings}')]
#[Description('Inspect missing managed-file provenance without changing files or manifests.')]
final class ManifestInspectCommand extends Command
{
    public function handle(
        PublishMap $map,
        ResyncManifestRepository $manifests,
        LegacyManifestAdoptionCatalog $catalog,
        ManagedPathPolicy $paths,
        ProjectMetadataRepository $metadata,
    ): int {
        try {
            $manifest = $manifests->read($map);
            $projectMetadata = $metadata->read($map);
            $packageVersion = is_string($manifest['package_version'] ?? null)
                ? $manifest['package_version']
                : '';
            $findings = [];

            foreach ($map->managedFiles() as $key => $file) {
                if (isset($manifest['files'][$key])
                    || ($manifest['surfaces'][$file['surface']] ?? null) === 'ejected') {
                    continue;
                }

                $paths->assertReadableDescriptorTarget($file['target'], $map);
                $precondition = FilePrecondition::capture($file['target']);

                if (! $precondition->exists) {
                    continue;
                }

                $checksum = $precondition->sha256;
                $findings[$key] = [
                    'surface' => $file['surface'],
                    'status' => is_string($checksum)
                        && $catalog->sourceChecksum($packageVersion, $projectMetadata, $key, $checksum) !== null
                            ? 'adoptable-pristine'
                            : 'selection-required',
                ];
            }

            $hasConflict = collect($findings)->contains(
                fn (array $finding): bool => $finding['status'] === 'selection-required',
            );
            $status = $hasConflict ? 'selection-required' : ($findings === [] ? 'clean' : 'adoptable');

            if ((bool) $this->option('json')) {
                $this->line(json_encode([
                    'schema_version' => 1,
                    'status' => $status,
                    'package_version' => $packageVersion !== '' ? $packageVersion : null,
                    'findings' => $findings,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } elseif ($findings === []) {
                $this->components->info('No unresolved managed-file provenance was found.');
            } else {
                foreach ($findings as $key => $finding) {
                    $this->components->twoColumnDetail($key, $finding['status']);
                }
            }

            return $hasConflict ? self::FAILURE : self::SUCCESS;
        } catch (ManagedPathException|ProjectMetadataException|ResyncManifestException $exception) {
            if ((bool) $this->option('json')) {
                $this->line(json_encode([
                    'schema_version' => 1,
                    'status' => 'conflict',
                    'error' => 'invalid-manifest-state',
                    'findings' => [],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                return self::FAILURE;
            }

            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
