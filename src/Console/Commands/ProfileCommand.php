<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('evolayer:profile
    {profile : The install profile to apply (demo|lean)}
    {--path= : Path to the .env file to rewrite (defaults to the app .env)}')]
#[Description('Switch between the demo (kitchen-sink) and lean (examples off) install profiles by toggling EVOLAYER_BASE_EXAMPLE_* flags.')]
class ProfileCommand extends Command
{
    private const PROFILES = ['demo', 'lean'];

    public function handle(): int
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

        // Demo is the kitchen-sink default (every example surface on); lean
        // turns them all off for a production-credible baseline.
        $value = $profile === 'demo' ? 'true' : 'false';

        $keys = array_map(
            fn (string $key): string => 'EVOLAYER_BASE_EXAMPLE_'.strtoupper($key),
            array_keys((array) config('evolayer.base.examples'))
        );

        $env = (string) file_get_contents($envPath);

        foreach ($keys as $key) {
            $env = $this->setEnvValue($env, $key, $value);
        }

        file_put_contents($envPath, $env);

        $this->components->info("Applied the '{$profile}' profile: set ".count($keys)." EVOLAYER_BASE_EXAMPLE_* flag(s) to {$value}.");
        $this->components->warn('Run `php artisan config:clear` to apply (rebuild assets if example routes changed).');

        return self::SUCCESS;
    }

    /**
     * Set KEY=value in the .env contents, replacing an existing line or
     * appending a new one.
     */
    private function setEnvValue(string $env, string $key, string $value): string
    {
        $line = "{$key}={$value}";
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $env)) {
            return (string) preg_replace($pattern, $line, $env);
        }

        return rtrim($env, "\n")."\n".$line."\n";
    }
}
