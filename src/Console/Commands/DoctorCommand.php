<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Tags\HasTags;
use Xuple\EvoLayer\Base\Auth\SpatieAdminGate;
use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Contracts\UserResolver;

#[Signature('evolayer:doctor {--strict : Exit non-zero if any check is advisory (for CI use). Default mode stays informational and always exits 0.}')]
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
            ...$this->checkPostgresUlidMorphColumns(),
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

        // Doctor is informational by default: many advisories depend on which
        // features the host has enabled, so a non-zero exit there would
        // false-flag legitimate configurations. CI surfaces that need a
        // hard fail (kitchen-sink starter contracts, release gates) opt in
        // with --strict.
        $failed = collect($checks)->reject(fn ($c) => $c[0])->count();
        $this->components->info($failed === 0
            ? 'All checks passed.'
            : "{$failed} advisory item(s) — review the hints above.");

        if ($this->option('strict') && $failed > 0) {
            return self::FAILURE;
        }

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

    /** @return list<array{0: bool, 1: string, 2: ?string}> */
    private function checkPostgresUlidMorphColumns(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [[
                true,
                'PostgreSQL ULID morph schema: skipped ('.DB::connection()->getDriverName().')',
                null,
            ]];
        }

        return collect([
            ['activity_log', 'subject_id', 'Spatie activitylog subjects'],
            ['taggables', 'taggable_id', 'Spatie tags taggables'],
            ['media', 'model_id', 'Spatie medialibrary owners'],
        ])->map(function (array $column): array {
            [$table, $name, $label] = $column;

            if (! Schema::hasColumn($table, $name)) {
                return [
                    true,
                    "{$label}: {$table}.{$name} not present",
                    null,
                ];
            }

            $type = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->where('column_name', $name)
                ->value('data_type');

            $ok = is_string($type) && ! str_contains(strtolower($type), 'bigint');

            return [
                $ok,
                "{$label}: {$table}.{$name} {$type}",
                $ok ? null : "Use ULID/string-compatible morph columns for EvoLayer ULID models before migrating on PostgreSQL.",
            ];
        })->all();
    }
}
