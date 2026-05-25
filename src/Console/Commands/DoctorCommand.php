<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Tags\HasTags;
use Xuple\EvoLayer\Base\Auth\SpatieAdminGate;
use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Contracts\UserResolver;

#[Signature('evolayer:doctor')]
#[Description('Check the EvoLayer Base installation for common configuration problems.')]
class DoctorCommand extends Command
{
    public function handle(): int
    {
        $checks = [
            $this->checkAdminGate(),
            $this->checkUserResolver(),
            $this->checkStructuredStreamingPatch(),
            $this->checkOntologyCompiled(),
            ...$this->checkSpatieFeatureMatrix(),
        ];

        $this->newLine();
        foreach ($checks as [$ok, $label, $hint]) {
            $this->line(sprintf(
                '  %s %s',
                $ok ? '<fg=green>✓</>' : '<fg=yellow>!</>',
                $label,
            ));
            if (! $ok && $hint !== null) {
                $this->line("      <fg=gray>{$hint}</>");
            }
        }
        $this->newLine();

        // Doctor never fails the process — it advises. Unresolved items are
        // warnings, since many depend on which features the host has enabled.
        $failed = collect($checks)->reject(fn ($c) => $c[0])->count();
        $this->components->info($failed === 0
            ? 'All checks passed.'
            : "{$failed} advisory item(s) — review the hints above.");

        return self::SUCCESS;
    }

    /** @return array{0: bool, 1: string, 2: ?string} */
    private function checkAdminGate(): array
    {
        $bound = $this->laravel->bound(AdminGate::class) && $this->laravel->make(AdminGate::class) instanceof AdminGate;
        $isDefault = $bound && $this->laravel->make(AdminGate::class) instanceof SpatieAdminGate;

        return [
            $bound,
            'AdminGate is bound'.($isDefault ? ' (default SpatieAdminGate)' : ' (custom implementation)'),
            $bound ? null : 'Bind Xuple\EvoLayer\Base\Contracts\AdminGate in a service provider.',
        ];
    }

    /** @return array{0: bool, 1: string, 2: ?string} */
    private function checkUserResolver(): array
    {
        $bound = $this->laravel->bound(UserResolver::class) && $this->laravel->make(UserResolver::class) instanceof UserResolver;

        return [
            $bound,
            'UserResolver is bound',
            $bound ? null : 'Bind Xuple\EvoLayer\Base\Contracts\UserResolver in a service provider.',
        ];
    }

    /** @return array{0: bool, 1: string, 2: ?string} */
    private function checkStructuredStreamingPatch(): array
    {
        $file = base_path('vendor/laravel/ai/src/Providers/Concerns/StreamsText.php');
        $applied = is_file($file) && str_contains((string) file_get_contents($file), 'JsonSchemaTypeFactory');

        return [
            $applied,
            'laravel/ai structured-streaming patch applied',
            $applied ? null : 'Run: patch -p1 -d vendor/laravel/ai --forward < patches/laravel-ai-structured-streaming.patch',
        ];
    }

    /** @return array{0: bool, 1: string, 2: ?string} */
    private function checkOntologyCompiled(): array
    {
        $compiled = is_file(base_path('bootstrap/cache/ontology.php'));

        return [
            $compiled,
            'Ontology compiled (bootstrap/cache/ontology.php)',
            $compiled ? null : 'Run: php artisan evolayer:ontology:compile',
        ];
    }

    /** @return list<array{0: bool, 1: string, 2: ?string}> */
    private function checkSpatieFeatureMatrix(): array
    {
        $rows = [];

        $contactAttachments = (bool) config('evolayer.base.features.contact_attachments');
        $mediaInstalled = interface_exists(HasMedia::class);
        $rows[] = [
            ! $contactAttachments || $mediaInstalled,
            'Contact attachments: '.($contactAttachments ? 'enabled' : 'disabled')
                .' / medialibrary '.($mediaInstalled ? 'installed' : 'absent'),
            ($contactAttachments && ! $mediaInstalled)
                ? 'Feature is on but spatie/laravel-medialibrary is missing — composer require spatie/laravel-medialibrary.'
                : null,
        ];

        $contactAi = (bool) config('evolayer.base.examples.contact_ai');
        $tagsInstalled = trait_exists(HasTags::class);
        $rows[] = [
            ! $contactAi || $tagsInstalled,
            'AI auto-tagging: contact_ai '.($contactAi ? 'enabled' : 'disabled')
                .' / tags '.($tagsInstalled ? 'installed' : 'absent'),
            ($contactAi && ! $tagsInstalled)
                ? 'contact_ai is on but spatie/laravel-tags is missing — auto-tagging will no-op. composer require spatie/laravel-tags.'
                : null,
        ];

        return $rows;
    }
}
