<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileStateException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileStatusInspector;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataException;
use Xuple\EvoLayer\Base\Support\ResyncManifestException;

#[Signature('evolayer:profile:status {--json : Emit machine-readable status without local paths or environment values}')]
#[Description('Compare committed profile intent with effective configuration and managed source state.')]
final class ProfileStatusCommand extends Command
{
    public function handle(ProfileStatusInspector $inspector): int
    {
        try {
            $status = $inspector->inspect();

            return $this->render(
                $status,
                $status['status'] === 'verified' ? self::SUCCESS : self::FAILURE,
            );
        } catch (ManagedPathException|ProfileStateException|ProjectMetadataException|ResyncManifestException $exception) {
            if (! (bool) $this->option('json')) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            return $this->render([
                'status' => 'conflict',
                'repository_kind' => null,
                'profile' => null,
                'suggested_profile' => null,
                'effective_profile' => null,
                'effective_drift' => null,
                'effective_mismatches' => [],
                'managed_state' => 'unknown',
                'managed_findings' => [],
                'generated_state' => 'unknown',
                'verification_state' => 'unverified',
                'receipt_state' => 'invalid',
                'error' => 'invalid-profile-state',
            ], self::FAILURE);
        } catch (Throwable) {
            if (! (bool) $this->option('json')) {
                $this->components->error('Unable to evaluate profile status [internal-error].');

                return self::FAILURE;
            }

            return $this->render([
                'status' => 'conflict',
                'repository_kind' => null,
                'profile' => null,
                'suggested_profile' => null,
                'effective_profile' => null,
                'effective_drift' => null,
                'effective_mismatches' => [],
                'managed_state' => 'unknown',
                'managed_findings' => [],
                'generated_state' => 'unknown',
                'verification_state' => 'unverified',
                'receipt_state' => 'invalid',
                'error' => 'internal-error',
            ], self::FAILURE);
        }
    }

    /** @param array<string, mixed> $status */
    private function render(array $status, int $exitCode): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $exitCode;
        }

        foreach ($status as $label => $value) {
            if (is_array($value)) {
                $value = $value === [] ? 'none' : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }

            $this->components->twoColumnDetail(
                str_replace('_', ' ', ucfirst($label)),
                is_bool($value) ? ($value ? 'yes' : 'no') : (string) ($value ?? 'n/a'),
            );
        }

        return $exitCode;
    }
}
