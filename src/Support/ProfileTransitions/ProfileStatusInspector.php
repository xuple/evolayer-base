<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use Composer\InstalledVersions;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\ResyncManifestRepository;

final readonly class ProfileStatusInspector
{
    public function __construct(
        private PublishMap $map,
        private ProjectMetadataRepository $metadataRepository,
        private ResyncManifestRepository $manifestRepository,
        private ProfileRegistry $profiles,
        private ExpectedProfileStateResolver $resolver,
        private ManagedPathPolicy $paths,
        private ProfileVerificationManager $verification,
        private ProfileVerificationReceiptRepository $receipts,
    ) {}

    /**
     * @return array{
     *   status: string,
     *   repository_kind: ?string,
     *   profile: ?string,
     *   suggested_profile: ?string,
     *   effective_profile: ?string,
     *   effective_drift: ?bool,
     *   effective_mismatches: list<array{category: string, key: string, expected: bool|string|null, effective: bool|string|null}>,
     *   managed_state: string,
     *   managed_findings: list<array{key: string, surface: string, state: string}>,
     *   generated_state: string,
     *   verification_state: string,
     *   receipt_state: string
     * }
     */
    public function inspect(): array
    {
        $evaluation = $this->evaluate();
        $status = $evaluation['status'];
        $context = $evaluation['context'];

        if ($context === null) {
            return [...$status, 'receipt_state' => 'missing'];
        }

        $receiptState = $this->receipts->state(
            $this->map,
            $context,
            $this->verification->currentFingerprints($context),
        );

        if ($status['status'] === 'pending-verification' && $receiptState === 'current') {
            $status['status'] = 'verified';
            $status['generated_state'] = 'verified';
            $status['verification_state'] = 'verified';
        }

        return [...$status, 'receipt_state' => $receiptState];
    }

    public function verificationContext(): ?ProfileVerificationContext
    {
        return $this->evaluate()['context'];
    }

    /** @return array{status: array<string, mixed>, context: ?ProfileVerificationContext} */
    private function evaluate(): array
    {
        $metadata = $this->metadataRepository->read($this->map);

        if ($metadata === [] || ! array_key_exists('schema_version', $metadata)) {
            $effectiveExamples = $this->effectiveExamples();
            $effectiveFeatures = $this->effectiveFeatures();

            return ['status' => [
                'status' => 'selection-required',
                'repository_kind' => ($metadata['mode'] ?? null) === 'application'
                    ? 'generated-application'
                    : null,
                'profile' => null,
                'suggested_profile' => ($metadata['mode'] ?? null) === 'application'
                    ? $this->matchingProfile($effectiveExamples, $effectiveFeatures)
                    : null,
                'effective_profile' => $this->effectiveProfile(),
                'effective_drift' => null,
                'effective_mismatches' => [],
                'managed_state' => 'unknown',
                'managed_findings' => [],
                'generated_state' => 'unknown',
                'verification_state' => 'unverified',
            ], 'context' => null];
        }

        $expected = $this->resolver->resolve($metadata, $this->profiles, $this->map);

        if ($expected === null) {
            throw new ProfileStateException('Committed project intent could not be resolved.');
        }

        $manifest = $this->manifestRepository->read($this->map);
        $effectiveProfile = $this->effectiveProfile();
        $effectiveExamples = $this->effectiveExamples();
        $effectiveFeatures = $this->effectiveFeatures();
        $mismatches = [];

        if ($effectiveProfile !== $expected->definition->id) {
            $mismatches[] = [
                'category' => 'profile',
                'key' => 'profile',
                'expected' => $expected->definition->id,
                'effective' => $effectiveProfile,
            ];
        }

        $mismatches = [
            ...$mismatches,
            ...$this->mismatches('examples', $expected->examples, $effectiveExamples),
            ...$this->mismatches('features', $expected->features, $effectiveFeatures),
        ];
        $managedFindings = $this->managedFindings($manifest, $expected);
        $managedState = $managedFindings[0]['state'] ?? 'aligned';
        $drift = $mismatches !== [] || $managedFindings !== [];

        $status = [
            'status' => $drift ? 'drift' : 'pending-verification',
            'repository_kind' => is_string($metadata['kind'] ?? null) ? $metadata['kind'] : null,
            'profile' => $expected->definition->id,
            'suggested_profile' => null,
            'effective_profile' => $effectiveProfile,
            'effective_drift' => $mismatches !== [],
            'effective_mismatches' => $mismatches,
            'managed_state' => $managedState,
            'managed_findings' => $managedFindings,
            'generated_state' => 'pending-verification',
            'verification_state' => 'unverified',
        ];

        return [
            'status' => $status,
            'context' => new ProfileVerificationContext(
                expected: $expected,
                status: $status,
                bindings: [
                    'intent' => ProfileVerificationFingerprint::hash($metadata),
                    'effective' => ProfileVerificationFingerprint::hash([
                        'profile' => $effectiveProfile,
                        'examples' => $effectiveExamples,
                        'features' => $effectiveFeatures,
                    ]),
                    'manifest' => ProfileVerificationFingerprint::hash($manifest),
                    'managed' => ProfileVerificationFingerprint::hash($this->managedFingerprintMaterial($manifest)),
                ],
                baseVersion: $this->baseVersion(),
                baseReference: $this->baseReference(),
                hostVersion: $this->hostVersion(),
                hostReference: $this->hostReference(),
            ),
        ];
    }

    private function effectiveProfile(): ?string
    {
        $profile = config('evolayer.base.profile');

        return is_string($profile) && $profile !== '' ? $profile : null;
    }

    /** @return array<string, bool> */
    private function effectiveExamples(): array
    {
        return collect((array) config('evolayer.base.examples'))
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all();
    }

    /** @return array<string, bool> */
    private function effectiveFeatures(): array
    {
        return collect((array) config('evolayer.base.features'))
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all();
    }

    /** @param array<string, bool> $examples @param array<string, bool> $features */
    private function matchingProfile(array $examples, array $features): ?string
    {
        $matches = [];

        foreach ($this->profiles->ids() as $profile) {
            $definition = $this->profiles->get($profile);

            if ($definition !== null
                && $definition->examples === $examples
                && $definition->features === $features) {
                $matches[] = $profile;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @param  array<string, bool>  $expected
     * @param  array<string, bool>  $effective
     * @return list<array{category: string, key: string, expected: bool, effective: bool|null}>
     */
    private function mismatches(string $category, array $expected, array $effective): array
    {
        $mismatches = [];

        foreach ($expected as $key => $value) {
            if (($effective[$key] ?? null) !== $value) {
                $mismatches[] = [
                    'category' => $category,
                    'key' => $key,
                    'expected' => $value,
                    'effective' => $effective[$key] ?? null,
                ];
            }
        }

        return $mismatches;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<array{key: string, surface: string, state: string}>
     */
    private function managedFindings(array $manifest, ExpectedProfileState $expected): array
    {
        $findings = [];
        $ejected = [];

        foreach ($this->map->managedFiles() as $key => $file) {
            $surface = $file['surface'];

            if (($manifest['surfaces'][$surface] ?? null) === 'ejected') {
                if (! isset($ejected[$surface])) {
                    $findings[] = ['key' => $surface, 'surface' => $surface, 'state' => 'ejected'];
                    $ejected[$surface] = true;
                }

                continue;
            }

            $descriptor = $this->map->surfaces()[$surface] ?? null;
            $enabled = $surface === 'core'
                || ($descriptor !== null && ($expected->examples[$descriptor->configKey] ?? false));
            $this->paths->assertReadableDescriptorTarget($file['target'], $this->map);
            $precondition = FilePrecondition::capture($file['target']);
            $record = $manifest['files'][$key] ?? null;

            if (! $precondition->exists) {
                if ($enabled) {
                    $findings[] = ['key' => $key, 'surface' => $surface, 'state' => 'absent'];
                }

                continue;
            }

            if (! $enabled) {
                $findings[] = ['key' => $key, 'surface' => $surface, 'state' => 'stale-disabled'];

                continue;
            }

            if (! is_array($record)) {
                $findings[] = ['key' => $key, 'surface' => $surface, 'state' => 'unknown'];

                continue;
            }

            if ($precondition->sha256 !== $record['installed_sha']) {
                $findings[] = ['key' => $key, 'surface' => $surface, 'state' => 'modified'];

                continue;
            }

            $this->paths->assertDescriptorSource($file['source'], $this->map);

            if (hash_file('sha256', $file['source']) !== $record['source_sha']) {
                $findings[] = ['key' => $key, 'surface' => $surface, 'state' => 'stale-package-source'];
            }
        }

        return $findings;
    }

    /** @param array<string, mixed> $manifest @return array<string, array<string, mixed>> */
    private function managedFingerprintMaterial(array $manifest): array
    {
        $material = [];

        foreach ($this->map->managedFiles() as $key => $file) {
            $surface = $file['surface'];

            if (($manifest['surfaces'][$surface] ?? null) === 'ejected') {
                $material[$key] = ['surface' => $surface, 'state' => 'ejected'];

                continue;
            }

            $this->paths->assertReadableDescriptorTarget($file['target'], $this->map);
            $precondition = FilePrecondition::capture($file['target']);
            $material[$key] = [
                'surface' => $surface,
                'state' => $precondition->exists ? 'present' : 'absent',
                'sha256' => $precondition->sha256,
            ];
        }

        ksort($material);

        return $material;
    }

    private function baseVersion(): string
    {
        return InstalledVersions::isInstalled('xuple/evolayer-base')
            ? (InstalledVersions::getPrettyVersion('xuple/evolayer-base') ?? 'unknown')
            : 'unknown';
    }

    private function baseReference(): ?string
    {
        return InstalledVersions::isInstalled('xuple/evolayer-base')
            ? InstalledVersions::getReference('xuple/evolayer-base')
            : null;
    }

    private function hostVersion(): ?string
    {
        $root = InstalledVersions::getRootPackage();

        return is_string($root['pretty_version'] ?? null) ? $root['pretty_version'] : null;
    }

    private function hostReference(): ?string
    {
        $root = InstalledVersions::getRootPackage();

        return is_string($root['reference'] ?? null) ? $root['reference'] : null;
    }
}
