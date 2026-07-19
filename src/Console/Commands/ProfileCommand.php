<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionConflict;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionContext;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileTransitionManager;

#[Signature('evolayer:profile
    {profile : The install profile to apply (demo|lean)}
    {--path= : Path to the .env file to rewrite (defaults to the app .env)}
    {--dry-run : Show the complete transition plan without changing files}')]
#[Description('Switch between the demo (kitchen-sink) and lean (examples off) install profiles by toggling EVOLAYER_BASE_EXAMPLE_* flags.')]
class ProfileCommand extends Command
{
    private const PROFILES = ['demo', 'lean'];

    public function handle(ProfileTransitionManager $transitions): int
    {
        $profile = strtolower((string) $this->argument('profile'));

        if (! in_array($profile, self::PROFILES, true)) {
            $this->components->error("Unknown profile [{$profile}]. Choose: ".implode(', ', self::PROFILES).'.');

            return self::FAILURE;
        }

        $envPath = (string) ($this->option('path') ?: base_path('.env'));

        if (! is_file($envPath)) {
            $this->components->error("No .env file found at {$envPath}.");

            return self::FAILURE;
        }

        $current = collect((array) config('evolayer.base.examples'))
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all();
        $target = array_fill_keys(array_keys($current), $profile === 'demo');
        $context = new ProfileTransitionContext($profile, $envPath, $current, $target);

        try {
            $result = $transitions->execute($context, (bool) $this->option('dry-run'));
        } catch (ProfileTransitionConflict $exception) {
            $this->components->error($exception->getMessage());
            $this->components->bulletList($exception->conflicts);

            return self::FAILURE;
        }

        $verb = $result->dryRun ? 'Would apply' : 'Applied';
        $this->components->info("{$verb} the '{$profile}' profile across ".count($result->affectedPaths).' file(s).');

        if (! $result->dryRun) {
            $this->components->warn('Run `php artisan config:clear` to apply, then regenerate Wayfinder and rebuild assets.');
        }

        return self::SUCCESS;
    }
}
