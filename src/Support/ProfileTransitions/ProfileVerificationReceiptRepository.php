<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use JsonException;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ManagedPathPolicy;
use Xuple\EvoLayer\Base\Support\PublishMap;

final readonly class ProfileVerificationReceiptRepository
{
    public const SCHEMA_VERSION = 1;

    public function __construct(private ManagedPathPolicy $paths) {}

    /** @param array<string, string>|null $fingerprints */
    public function state(PublishMap $map, ProfileVerificationContext $context, ?array $fingerprints): string
    {
        $receipt = $this->read($map);

        if (is_string($receipt)) {
            return $receipt;
        }

        if ($fingerprints === null) {
            return 'stale';
        }

        return $this->matches($receipt, $context, $fingerprints) ? 'current' : 'stale';
    }

    /**
     * @param  list<array{id: string, capability: string, passed: bool, error: ?string, corrective_action: ?string, fingerprint: ?string}>  $results
     */
    public function planWrite(
        ProfileTransitionPlan $plan,
        PublishMap $map,
        ProfileVerificationContext $context,
        array $results,
    ): void {
        $checks = [];

        foreach ($results as $result) {
            if (! $result['passed'] || $result['fingerprint'] === null) {
                continue;
            }

            $checks[$result['id']] = [
                'capability' => $result['capability'],
                'input_sha256' => $result['fingerprint'],
            ];
        }

        ksort($checks);
        $path = $map->verificationReceiptPath();
        $this->paths->assertHostMutationTarget($path, $map);
        $plan->replace($path, $this->encode([
            'schema_version' => self::SCHEMA_VERSION,
            'verifier_contract_version' => ProfileVerificationManager::CONTRACT_VERSION,
            'profile' => $context->expected->definition->id,
            'bindings' => [
                'profile_intent_sha256' => $context->bindings['intent'],
                'effective_state_sha256' => $context->bindings['effective'],
                'managed_manifest_sha256' => $context->bindings['manifest'],
                'managed_state_sha256' => $context->bindings['managed'],
                'base' => ['version' => $context->baseVersion, 'reference' => $context->baseReference],
                'host' => $context->hostVersion === null ? null : [
                    'version' => $context->hostVersion,
                    'reference' => $context->hostReference,
                ],
            ],
            'checks' => $checks,
            'verified_at' => date(DATE_ATOM),
        ]));
    }

    public function planInvalidation(ProfileTransitionPlan $plan, PublishMap $map): void
    {
        $path = $map->verificationReceiptPath();
        $this->paths->assertHostMutationTarget($path, $map);

        if (is_file($path)) {
            $plan->delete($path);
        }
    }

    /** @return array<string, mixed>|'missing'|'invalid' */
    private function read(PublishMap $map): array|string
    {
        $path = $map->verificationReceiptPath();

        try {
            $this->paths->assertReadableHostTarget($path, $map);
        } catch (ManagedPathException) {
            return 'invalid';
        }

        if (! is_file($path)) {
            return 'missing';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return 'invalid';
        }

        try {
            $receipt = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'invalid';
        }

        if (! is_array($receipt)
            || ($receipt['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ($receipt['verifier_contract_version'] ?? null) !== ProfileVerificationManager::CONTRACT_VERSION
            || ! is_string($receipt['profile'] ?? null)
            || ! is_array($receipt['bindings'] ?? null)
            || ! $this->validBindings($receipt['bindings'])
            || ! is_array($receipt['checks'] ?? null)
            || ! is_string($receipt['verified_at'] ?? null)) {
            return 'invalid';
        }

        return $receipt;
    }

    /** @param array<string, mixed> $receipt @param array<string, string> $fingerprints */
    private function matches(array $receipt, ProfileVerificationContext $context, array $fingerprints): bool
    {
        $checks = [];

        foreach ($receipt['checks'] as $id => $check) {
            if (! is_string($id)
                || ! is_array($check)
                || ! is_string($check['capability'] ?? null)
                || ! is_string($check['input_sha256'] ?? null)) {
                return false;
            }

            $checks[$id] = $check['input_sha256'];
        }

        ksort($checks);

        return $receipt['profile'] === $context->expected->definition->id
            && ($receipt['bindings']['profile_intent_sha256'] ?? null) === $context->bindings['intent']
            && ($receipt['bindings']['effective_state_sha256'] ?? null) === $context->bindings['effective']
            && ($receipt['bindings']['managed_manifest_sha256'] ?? null) === $context->bindings['manifest']
            && ($receipt['bindings']['managed_state_sha256'] ?? null) === $context->bindings['managed']
            && ($receipt['bindings']['base']['version'] ?? null) === $context->baseVersion
            && ($receipt['bindings']['base']['reference'] ?? null) === $context->baseReference
            && ($receipt['bindings']['host']['version'] ?? null) === $context->hostVersion
            && ($receipt['bindings']['host']['reference'] ?? null) === $context->hostReference
            && $checks === $fingerprints;
    }

    /** @param array<string, mixed> $bindings */
    private function validBindings(array $bindings): bool
    {
        foreach (['profile_intent_sha256', 'effective_state_sha256', 'managed_manifest_sha256', 'managed_state_sha256'] as $key) {
            if (! is_string($bindings[$key] ?? null) || preg_match('/^[a-f0-9]{64}$/', $bindings[$key]) !== 1) {
                return false;
            }
        }

        if (! $this->validVersionBinding($bindings['base'] ?? null)) {
            return false;
        }

        return array_key_exists('host', $bindings)
            && ($bindings['host'] === null || $this->validVersionBinding($bindings['host']));
    }

    private function validVersionBinding(mixed $binding): bool
    {
        return is_array($binding)
            && array_key_exists('reference', $binding)
            && is_string($binding['version'] ?? null)
            && ($binding['reference'] === null || is_string($binding['reference']));
    }

    /** @param array<string, mixed> $receipt */
    private function encode(array $receipt): string
    {
        return json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    }
}
