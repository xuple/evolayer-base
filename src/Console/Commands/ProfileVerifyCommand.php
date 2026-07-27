<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;
use Xuple\EvoLayer\Base\Support\ManagedMutationLock;
use Xuple\EvoLayer\Base\Support\ManagedMutationLockException;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileStateException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileStatusInspector;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutionException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutor;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionPlan;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationManager;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileVerificationReceiptRepository;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataException;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\ResyncManifestException;

#[Signature('evolayer:profile:verify {--json : Emit redacted machine-readable verification results}')]
#[Description('Verify current profile state and write a non-authoritative local evidence receipt when every required check passes.')]
final class ProfileVerifyCommand extends Command
{
    public function handle(
        ProfileStatusInspector $status,
        ProfileVerificationManager $verification,
        ProfileVerificationReceiptRepository $receipts,
        ProfileTransitionExecutor $executor,
        ManagedMutationLock $lock,
        PublishMap $map,
    ): int {
        try {
            return $lock->run($map, function () use ($status, $verification, $receipts, $executor, $map): int {
                $context = $status->verificationContext();

                if ($context === null) {
                    return $this->renderFailure(null, [[
                        'id' => 'base.committed-intent',
                        'capability' => 'committed-intent',
                        'passed' => false,
                        'error' => 'profile-selection-required',
                        'corrective_action' => 'Select and apply a registered profile before running verification.',
                        'fingerprint' => null,
                    ]], 'missing');
                }

                $invalidation = new ProfileTransitionPlan;
                $receipts->planInvalidation($invalidation, $map);
                $executor->execute($invalidation);

                $results = $verification->verify($context);

                if (collect($results)->contains(fn (array $result): bool => ! $result['passed'])) {
                    return $this->renderFailure($context->expected->definition->id, $results, 'invalidated');
                }

                $freshContext = $status->verificationContext();
                $freshFingerprints = $freshContext === null
                    ? null
                    : $verification->currentFingerprints($freshContext);
                $verifiedFingerprints = collect($results)
                    ->mapWithKeys(fn (array $result): array => [$result['id'] => $result['fingerprint']])
                    ->sortKeys()
                    ->all();

                if ($freshContext === null
                    || $freshContext->expected->definition->id !== $context->expected->definition->id
                    || $freshContext->bindings !== $context->bindings
                    || $freshContext->baseVersion !== $context->baseVersion
                    || $freshContext->baseReference !== $context->baseReference
                    || $freshContext->hostVersion !== $context->hostVersion
                    || $freshContext->hostReference !== $context->hostReference
                    || $freshFingerprints !== $verifiedFingerprints) {
                    return $this->renderFailure($context->expected->definition->id, [[
                        'id' => 'base.verification-inputs',
                        'capability' => 'verification-inputs',
                        'passed' => false,
                        'error' => 'verification-input-changed',
                        'corrective_action' => 'Stop concurrent changes and rerun profile verification.',
                        'fingerprint' => null,
                    ]], 'invalidated');
                }

                $plan = new ProfileTransitionPlan;
                $receipts->planWrite($plan, $map, $freshContext, $results);
                $executor->execute($plan);

                return $this->renderSuccess($context->expected->definition->id, $results);
            });
        } catch (ProjectMetadataException|ProfileStateException $exception) {
            return $this->renderStateError('committed-intent-invalid', 'Repair or explicitly select committed profile intent, then rerun verification.');
        } catch (ResyncManifestException|ManagedPathException $exception) {
            return $this->renderStateError('managed-state-invalid', 'Repair the unsafe manifest, receipt, or managed path state, then rerun verification.');
        } catch (ManagedMutationLockException) {
            return $this->renderTopLevelError('mutation-lock-unavailable');
        } catch (ProfileTransitionExecutionException) {
            return $this->renderTopLevelError('receipt-mutation-failed');
        } catch (Throwable) {
            return $this->renderTopLevelError('internal-error');
        }
    }

    /** @param list<array<string, mixed>> $results */
    private function renderSuccess(string $profile, array $results): int
    {
        return $this->render('verified', $profile, $results, 'written', self::SUCCESS);
    }

    /** @param list<array<string, mixed>> $results */
    private function renderFailure(?string $profile, array $results, string $receiptState): int
    {
        return $this->render('verification-failed', $profile, $results, $receiptState, self::FAILURE);
    }

    private function renderStateError(string $error, string $correctiveAction): int
    {
        return $this->renderFailure(null, [[
            'id' => 'base.committed-intent',
            'capability' => 'committed-intent',
            'passed' => false,
            'error' => $error,
            'corrective_action' => $correctiveAction,
            'fingerprint' => null,
        ]], 'invalid');
    }

    private function renderTopLevelError(string $error): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'schema_version' => 1,
                'lifecycle_status' => 'verification-failed',
                'profile' => null,
                'checks' => [],
                'receipt_state' => 'unchanged',
                'error' => $error,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->components->error("Profile verification failed [{$error}].");
            $this->line('  Rerun with --json for the stable error code, then correct the reported state.');
        }

        return self::FAILURE;
    }

    /** @param list<array<string, mixed>> $results */
    private function render(string $status, ?string $profile, array $results, string $receiptState, int $exitCode): int
    {
        $checks = array_map(fn (array $result): array => [
            'id' => $result['id'],
            'capability' => $result['capability'],
            'passed' => $result['passed'],
            'error' => $result['error'],
            'corrective_action' => $result['corrective_action'],
        ], $results);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'schema_version' => 1,
                'lifecycle_status' => $status,
                'profile' => $profile,
                'checks' => $checks,
                'receipt_state' => $receiptState,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $exitCode;
        }

        foreach ($checks as $check) {
            $this->line(sprintf('  %s %s', $check['passed'] ? '<fg=green>✓</>' : '<fg=red>×</>', $check['id']));

            if (! $check['passed']) {
                $this->line("      <fg=gray>{$check['corrective_action']}</>");
            }
        }

        $status === 'verified'
            ? $this->components->info("Profile [{$profile}] verified; local receipt written.")
            : $this->components->error('Profile verification failed.');

        return $exitCode;
    }
}
