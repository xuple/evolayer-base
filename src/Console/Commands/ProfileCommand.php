<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Xuple\EvoLayer\Base\Support\ManagedMutationLock;
use Xuple\EvoLayer\Base\Support\ManagedMutationLockException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileRegistry;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileStateException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionConflict;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionExecutionException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionManager;
use Xuple\EvoLayer\Base\Support\PublishMap;

#[Signature('evolayer:profile
    {profile : The install profile to apply (demo|lean)}
    {--path= : Path to the .env file to rewrite (defaults to the app .env)}
    {--example=* : Override an example baseline with key=true or key=false}
    {--feature=* : Override a feature baseline with key=true or key=false}
    {--no-env : Update committed intent and managed state without editing an environment file}
    {--dry-run : Show the complete transition plan without changing files}
    {--json : Emit a redacted machine-readable plan or apply result}')]
#[Description('Switch between the demo (kitchen-sink) and lean (examples off) install profiles by toggling EVOLAYER_BASE_EXAMPLE_* flags.')]
class ProfileCommand extends Command
{
    public function handle(
        ProfileTransitionManager $transitions,
        ProfileRegistry $profiles,
        PublishMap $map,
        ManagedMutationLock $lock,
    ): int {
        $profile = strtolower((string) $this->argument('profile'));

        $definition = $profiles->get($profile);

        if ($definition === null) {
            if ((bool) $this->option('json')) {
                return $this->jsonError('unknown-profile', [
                    'available_profiles' => $profiles->ids(),
                ]);
            }

            $this->components->error("Unknown profile [{$profile}]. Choose: ".implode(', ', $profiles->ids()).'.');

            return self::FAILURE;
        }

        try {
            $exampleOverrides = $this->parseOverrides(
                category: 'examples',
                values: (array) $this->option('example'),
                knownKeys: array_values(array_unique([
                    ...array_keys($definition->examples),
                    ...array_map(fn ($surface): string => $surface->configKey, $map->surfaces()),
                ])),
                allowedCategories: $definition->allowedOverrides,
            );
            $featureOverrides = $this->parseOverrides(
                category: 'features',
                values: (array) $this->option('feature'),
                knownKeys: array_values(array_unique([
                    ...array_keys($definition->features),
                    ...array_keys((array) config('evolayer.base.features')),
                ])),
                allowedCategories: $definition->allowedOverrides,
            );
        } catch (ProfileStateException $exception) {
            if ((bool) $this->option('json')) {
                return $this->jsonError('invalid-profile-override');
            }

            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $envPath = (string) ($this->option('path') ?: base_path('.env'));

        $writeEnvironment = ! (bool) $this->option('no-env');

        if ($writeEnvironment && ! is_file($envPath)) {
            if ((bool) $this->option('json')) {
                return $this->jsonError('environment-file-missing');
            }

            $this->components->error("No .env file found at {$envPath}.");

            return self::FAILURE;
        }

        $current = collect((array) config('evolayer.base.examples'))
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all();
        $currentFeatures = collect((array) config('evolayer.base.features'))
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all();
        $context = new ProfileTransitionContext(
            profile: $profile,
            environmentPath: $envPath,
            currentExamples: $current,
            targetExamples: array_replace($definition->examples, $exampleOverrides),
            currentFeatures: $currentFeatures,
            targetFeatures: array_replace($definition->features, $featureOverrides),
            definition: $definition,
            writeEnvironment: $writeEnvironment,
            exampleOverrides: $exampleOverrides,
            featureOverrides: $featureOverrides,
        );

        try {
            $dryRun = (bool) $this->option('dry-run');
            $result = $dryRun
                ? $transitions->execute($context, true)
                : $lock->run($map, fn () => $transitions->execute($context));
        } catch (ProfileTransitionConflict $exception) {
            if ((bool) $this->option('json')) {
                return $this->jsonError('transition-conflict', [
                    'conflict_count' => count($exception->conflicts),
                ]);
            }

            $this->components->error($exception->getMessage());
            $this->components->bulletList($exception->conflicts);

            return self::FAILURE;
        } catch (ProfileTransitionExecutionException $exception) {
            if ((bool) $this->option('json')) {
                return $this->jsonError('transition-apply-failed', [
                    'rollback_failure_count' => count($exception->rollbackFailures),
                ]);
            }

            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (ManagedMutationLockException $exception) {
            if ((bool) $this->option('json')) {
                return $this->jsonError('mutation-lock-unavailable');
            }

            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'schema_version' => 1,
                'status' => $result->dryRun ? 'planned' : 'applied',
                'profile' => $profile,
                'dry_run' => $result->dryRun,
                'operations' => [
                    'replace' => $result->replacementCount,
                    'delete' => $result->deletionCount,
                    'total' => count($result->affectedPaths),
                ],
                'verification_state' => $result->dryRun ? 'not-applicable' : 'pending-verification',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $verb = $result->dryRun ? 'Would apply' : 'Applied';
        $this->components->info("{$verb} the '{$profile}' profile across ".count($result->affectedPaths).' file(s).');

        if (! $result->dryRun) {
            $this->components->warn('Run `php artisan config:clear` to apply, then regenerate Wayfinder and rebuild assets.');
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $details */
    private function jsonError(string $error, array $details = []): int
    {
        $this->line(json_encode([
            'schema_version' => 1,
            'status' => 'conflict',
            'error' => $error,
            ...$details,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::FAILURE;
    }

    /**
     * @param  list<mixed>  $values
     * @param  list<string>  $knownKeys
     * @param  list<string>  $allowedCategories
     * @return array<string, bool>
     */
    private function parseOverrides(
        string $category,
        array $values,
        array $knownKeys,
        array $allowedCategories,
    ): array {
        if ($values !== [] && ! in_array($category, $allowedCategories, true)) {
            throw new ProfileStateException("Profile does not allow [{$category}] overrides.");
        }

        $overrides = [];

        foreach ($values as $value) {
            if (! is_string($value)
                || preg_match('/^([a-z][a-z0-9_]*)=(true|false)$/', $value, $matches) !== 1) {
                throw new ProfileStateException("Profile [{$category}] overrides must use key=true or key=false.");
            }

            $key = $matches[1];

            if (! in_array($key, $knownKeys, true)) {
                throw new ProfileStateException("Unknown profile [{$category}] override [{$key}].");
            }

            if (array_key_exists($key, $overrides)) {
                throw new ProfileStateException("Profile [{$category}] override [{$key}] is defined more than once.");
            }

            $overrides[$key] = $matches[2] === 'true';
        }

        ksort($overrides);

        return $overrides;
    }
}
