<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Xuple\EvoLayer\Base\Database\Seeders\AiCapabilitySeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('evodevops:install
    {--no-migrate : Skip running migrations}
    {--no-seed : Skip seeding the AI capability ledger}')]
#[Description('Publish EvoDevOps Base assets, run migrations, compile the ontology, and print remaining manual steps.')]
class InstallCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Installing EvoDevOps Base…');

        // Always-on publishes. Per-feature frontend tags are intentionally NOT
        // published here — the dev opts into those alongside each feature flag.
        // Migrations are intentionally NOT published: the service provider
        // loads them from the package via loadMigrationsFrom(), so `migrate`
        // picks them up without copying. Hosts who want to OWN and customise the
        // schema can opt in explicitly with
        // `vendor:publish --tag=evodevops-base-migrations`.
        foreach ([
            'evodevops-base-config' => 'config',
            'evodevops-base-frontend-core' => 'core frontend',
            'evodevops-base-patches' => 'vendor patches',
            'evodevops-base-npm' => 'npm additions',
            'evodevops-base-ontology' => 'ontology',
        ] as $tag => $label) {
            $this->components->task("Publishing {$label}", fn () => $this->callSilent('vendor:publish', [
                '--tag' => $tag,
                '--force' => true,
            ]) === self::SUCCESS);
        }

        if (! $this->option('no-migrate')) {
            $this->components->task('Running migrations', fn () => $this->callSilent('migrate', [
                '--force' => true,
            ]) === self::SUCCESS);
        }

        if (! $this->option('no-seed')) {
            $this->components->task('Seeding AI capability ledger', fn () => $this->callSilent('db:seed', [
                '--class' => AiCapabilitySeeder::class,
                '--force' => true,
            ]) === self::SUCCESS);
        }

        $this->components->task('Compiling ontology', fn () => $this->callSilent('ontology:compile', [
            '--no-erd' => true,
        ]) === self::SUCCESS);

        $this->newLine();
        $this->components->info('EvoDevOps Base assets published. Remaining manual steps:');
        $this->line('');
        $this->line('  1. Apply the laravel/ai patch (structured streaming):');
        $this->line('       <fg=gray>patch -p1 -d vendor/laravel/ai --forward < patches/laravel-ai-structured-streaming.patch</>');
        $this->line('  2. Install the command-palette npm dependency:');
        $this->line('       <fg=gray>npm install cmdk   # see package-json-additions.evodevops.json</>');
        $this->line('  3. Share the evo prop in app/Http/Middleware/HandleInertiaRequests.php');
        $this->line('       <fg=gray>\'evo\' => [\'base\' => [\'examples\' => config(\'evo.base.examples\'), \'features\' => config(\'evo.base.features\')]]</>');
        $this->line('  4. Enable features one at a time — set the env flag AND publish its tag:');
        $this->line('       <fg=gray>EVO_BASE_EXAMPLE_THREAD_STUDIO=true  +  vendor:publish --tag=evodevops-base-frontend-thread-studio</>');
        $this->line('');
        $this->line('  Run <fg=cyan>php artisan evodevops:doctor</> to verify the install.');
        $this->newLine();

        return self::SUCCESS;
    }
}
