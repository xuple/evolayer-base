<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use InvalidArgumentException;
use Throwable;
use Xuple\EvoLayer\Base\Contracts\ProfileVerificationCheck;

final readonly class ProfileVerificationManager
{
    public const CONTRACT_VERSION = 1;

    public const CHECK_TAG = 'evolayer.profile-verification-checks';

    /** @var list<string> */
    public const BASE_CAPABILITIES = [
        'committed-intent',
        'effective-configuration',
        'managed-source',
        'managed-routes',
        'route-collisions',
    ];

    /** @var list<ProfileVerificationCheck> */
    private array $checks;

    public function __construct(ProfileVerificationCheck ...$checks)
    {
        $this->checks = $checks;
    }

    /**
     * @return list<array{id: string, capability: string, passed: bool, error: ?string, corrective_action: ?string, fingerprint: ?string}>
     */
    public function verify(ProfileVerificationContext $context): array
    {
        $resolved = $this->resolve($this->requirements($context));
        $results = [];

        foreach ($resolved as $capability => $check) {
            if ($check === null) {
                $results[] = [
                    'id' => 'required.'.$capability,
                    'capability' => $capability,
                    'passed' => false,
                    'error' => 'verification-capability-missing',
                    'corrective_action' => "Register a profile verification check that provides [{$capability}].",
                    'fingerprint' => null,
                ];

                continue;
            }

            try {
                $result = $check->verify($context);
                $results[] = [
                    'id' => $check->id(),
                    'capability' => $capability,
                    'passed' => $result->passed,
                    'error' => $result->errorCode,
                    'corrective_action' => $this->redactedCorrectiveAction($check, $result),
                    'fingerprint' => $this->hash($check->fingerprint($context)),
                ];
            } catch (Throwable) {
                $results[] = [
                    'id' => $check->id(),
                    'capability' => $capability,
                    'passed' => false,
                    'error' => 'verification-check-error',
                    'corrective_action' => "Review and rerun verification check [{$check->id()}].",
                    'fingerprint' => null,
                ];
            }
        }

        return $results;
    }

    /** @return array<string, string>|null */
    public function currentFingerprints(ProfileVerificationContext $context): ?array
    {
        $resolved = $this->resolve($this->requirements($context));
        $fingerprints = [];

        foreach ($resolved as $check) {
            if ($check === null) {
                return null;
            }

            try {
                $fingerprints[$check->id()] = $this->hash($check->fingerprint($context));
            } catch (Throwable) {
                return null;
            }
        }

        ksort($fingerprints);

        return $fingerprints;
    }

    /**
     * @param  list<string>  $requirements
     * @return array<string, ?ProfileVerificationCheck>
     */
    private function resolve(array $requirements): array
    {
        $byId = [];
        $byCapability = [];

        foreach ($this->checks as $check) {
            if (preg_match('/^[a-z][a-z0-9.-]*$/', $check->id()) !== 1
                || preg_match('/^[a-z][a-z0-9.-]*$/', $check->capability()) !== 1) {
                throw new InvalidArgumentException('Profile verification check IDs and capabilities must be stable lowercase identifiers.');
            }

            if ($check->apiVersion() !== self::CONTRACT_VERSION) {
                throw new InvalidArgumentException("Profile verification check [{$check->id()}] uses unsupported API version [{$check->apiVersion()}].");
            }

            if (isset($byId[$check->id()])) {
                throw new InvalidArgumentException("Duplicate profile verification check ID [{$check->id()}].");
            }

            if (isset($byCapability[$check->capability()])) {
                throw new InvalidArgumentException("Verification capability [{$check->capability()}] has more than one provider.");
            }

            $byId[$check->id()] = true;
            $byCapability[$check->capability()] = $check;
        }

        $resolved = [];

        foreach ($requirements as $capability) {
            if (! is_string($capability) || preg_match('/^[a-z][a-z0-9.-]*$/', $capability) !== 1) {
                throw new InvalidArgumentException('Profile verification requirements must be stable lowercase identifiers.');
            }

            $resolved[$capability] = $byCapability[$capability] ?? null;
        }

        return $resolved;
    }

    /** @return list<string> */
    private function requirements(ProfileVerificationContext $context): array
    {
        return array_values(array_unique([
            ...self::BASE_CAPABILITIES,
            ...$context->expected->definition->verificationRequirements,
        ]));
    }

    /** @param array<string, mixed> $material */
    private function hash(array $material): string
    {
        return ProfileVerificationFingerprint::hash($material);
    }

    private function redactedCorrectiveAction(
        ProfileVerificationCheck $check,
        ProfileVerificationResult $result,
    ): ?string {
        if ($result->passed) {
            return null;
        }

        $action = (string) $result->correctiveAction;

        if (strlen($action) > 500
            || preg_match('/[\x00-\x1F\x7F]/', $action) === 1
            || preg_match('#(?:^|\s)/(?:[^\s]+)#', $action) === 1
            || preg_match('#(?:^|\s)[A-Za-z]:[\\\\/]#', $action) === 1) {
            return "Review and rerun verification check [{$check->id()}].";
        }

        return $action;
    }
}
