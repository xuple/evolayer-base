<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Tags\HasTags;
use Xuple\EvoLayer\Base\Auth\SpatieAdminGate;
use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Contracts\UserResolver;
use Xuple\EvoLayer\Base\Support\ManagedPathException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileStateException;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProfileStatusInspector;
use Xuple\EvoLayer\Base\Support\ProfileTransitions\ProjectMetadataException;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\ResyncManifestException;
use Xuple\EvoLayer\Base\Support\RouteCollisionInspector;

#[Signature('evolayer:doctor
    {--strict : Exit non-zero if any check is advisory (for CI use)}
    {--production : Add production profile and route safety checks and fail on any finding}
    {--json : Emit machine-readable checks without paths, secrets, or environment values}')]
#[Description('Check the EvoLayer Base installation for common configuration problems.')]
class DoctorCommand extends Command
{
    public function handle(
        Router $router,
        PublishMap $map,
        RouteCollisionInspector $routeCollisions,
        ProfileStatusInspector $profileStatus,
    ): int {
        $checks = [
            $this->checkAdminGate(),
            $this->checkUserResolver(),
            $this->checkStructuredStreamingPatch(),
            $this->checkOntologyCompiled(),
            ...$this->checkSpatieFeatureMatrix(),
            ...$this->checkPostgresUlidMorphColumns(),
        ];

        if ((bool) $this->option('production')) {
            $checks = [
                ...$checks,
                ...$this->checkProfileState($profileStatus),
                ...$this->checkManagedRouteState($router, $map, $routeCollisions),
                ...$this->checkRouteCollisions($router, $map, $routeCollisions),
                $this->checkContactAttachmentStorage(),
            ];
        }

        $failed = collect($checks)->reject(fn ($check) => $check[0])->count();

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'schema_version' => 1,
                'status' => $failed === 0 ? 'healthy' : 'findings',
                'mode' => (bool) $this->option('production') ? 'production' : 'informational',
                'checks' => array_map(fn (array $check): array => [
                    'ok' => $check[0],
                    'label' => $check[1],
                    'corrective_action' => $check[2],
                ], $checks),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return ($failed > 0 && ($this->option('strict') || $this->option('production')))
                ? self::FAILURE
                : self::SUCCESS;
        }

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
        $this->components->info($failed === 0
            ? 'All checks passed.'
            : "{$failed} advisory item(s) — review the hints above.");

        if (($this->option('strict') || $this->option('production')) && $failed > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return list<array{0: bool, 1: string, 2: ?string}> */
    private function checkProfileState(ProfileStatusInspector $inspector): array
    {
        try {
            $status = $inspector->inspect();
        } catch (ManagedPathException|ProfileStateException|ProjectMetadataException|ResyncManifestException) {
            return [[
                false,
                'Committed profile state is invalid or unsafe to inspect',
                'Run: php artisan evolayer:profile:status --json, then repair the reported metadata, manifest, or managed path conflict.',
            ]];
        }

        if ($status['status'] === 'selection-required') {
            return [[
                false,
                'Operational profile selection is required',
                'Run: php artisan evolayer:profile <registered-profile> --dry-run, then apply the selected profile explicitly.',
            ]];
        }

        $checks = [];

        foreach ($status['effective_mismatches'] as $mismatch) {
            $expected = $this->diagnosticValue($mismatch['expected']);
            $effective = $this->diagnosticValue($mismatch['effective']);
            $checks[] = [
                false,
                "Profile {$mismatch['category']}.{$mismatch['key']} expected {$expected}; effective {$effective}",
                'Correct the deployment projection, then run: php artisan config:clear (or rebuild config:cache) and php artisan evolayer:profile:status --json.',
            ];
        }

        foreach ($status['managed_findings'] as $finding) {
            $checks[] = [
                false,
                "Managed {$finding['key']} is {$finding['state']}",
                $this->managedCorrectiveAction($finding['state']),
            ];
        }

        if ($status['verification_state'] !== 'verified') {
            $checks[] = [
                false,
                "Profile verification is {$status['verification_state']}",
                'Run the profile verification phase after regenerating contracts and completing the required test and build gates.',
            ];
        }

        return $checks === []
            ? [[true, 'Committed profile matches effective and managed state', null]]
            : $checks;
    }

    private function diagnosticValue(bool|string|null $value): string
    {
        return match ($value) {
            true => 'true',
            false => 'false',
            null => 'unset',
            default => $value,
        };
    }

    private function managedCorrectiveAction(string $state): string
    {
        return match ($state) {
            'absent' => 'Run: php artisan evolayer:resync to restore the enabled package surface.',
            'stale-disabled' => 'Run the selected profile again to reconcile pristine disabled source; eject or repair any conflict it reports.',
            'modified', 'unknown' => 'Restore exact package bytes or explicitly eject the surface before changing its profile state.',
            'ejected' => 'Verify the host-owned surface separately; re-enable package management only after resolving ownership explicitly.',
            default => 'Run: php artisan evolayer:profile:status --json and follow the reported ownership correction.',
        };
    }

    /** @return array{0: bool, 1: string, 2: ?string} */
    private function checkContactAttachmentStorage(): array
    {
        if (! (bool) config('evolayer.base.features.contact_attachments')) {
            return [true, 'Contact evidence storage: skipped (attachments disabled)', null];
        }

        $disk = config('media-library.disk_name');
        $disk = is_string($disk) && $disk !== '' ? $disk : null;
        $configuration = $disk === null ? [] : (array) config("filesystems.disks.{$disk}", []);
        $root = $configuration['root'] ?? null;
        $publicRoot = rtrim(str_replace('\\', '/', public_path()), '/');
        $normalizedRoot = is_string($root) ? rtrim(str_replace('\\', '/', $root), '/') : null;
        $knownPublic = $disk === 'public'
            || ($configuration['visibility'] ?? null) === 'public'
            || ($normalizedRoot !== null
                && ($normalizedRoot === $publicRoot || str_starts_with($normalizedRoot, $publicRoot.'/')));

        return [
            ! $knownPublic,
            'Contact evidence storage is '.($knownPublic ? 'known-public' : 'not known-public'),
            $knownPublic
                ? 'Configure the effective medialibrary disk as private before enabling contact attachments in production.'
                : null,
        ];
    }

    /** @return list<array{0: bool, 1: string, 2: ?string}> */
    private function checkRouteCollisions(
        Router $router,
        PublishMap $map,
        RouteCollisionInspector $inspector,
    ): array {
        $collisions = $inspector->inspect($router->getRoutes(), $map);

        if ($collisions === []) {
            return [[true, 'Managed package routes have no host ownership collisions', null]];
        }

        return array_map(function (array $collision): array {
            $methods = implode('|', $collision['methods']);
            $location = ($collision['domain'] === null ? '' : $collision['domain']).'/'.$collision['uri'];
            $label = "Route ownership {$collision['type']} [{$methods} {$location}] ({$collision['id']})";

            if ($collision['allowed']) {
                return [true, $label.' (allowlisted)', null];
            }

            return [
                false,
                $label,
                "Rename the host route, disable the managed surface, or review and add [{$collision['id']}] to evolayer.base.route.collision_allowlist.",
            ];
        }, $collisions);
    }

    /** @return list<array{0: bool, 1: string, 2: ?string}> */
    private function checkManagedRouteState(
        Router $router,
        PublishMap $map,
        RouteCollisionInspector $inspector,
    ): array {
        $findings = $inspector->stateFindings($router->getRoutes(), $map);

        if ($findings === []) {
            return [[true, 'Managed package routes match effective profile state', null]];
        }

        return array_map(fn (array $finding): array => [
            false,
            "Route state {$finding['type']} [{$finding['package_route']}] ({$finding['id']})",
            'Run: php artisan route:clear, rebuild route:cache when required, then run php artisan evolayer:doctor --production again.',
        ], $findings);
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
                $ok ? null : 'Use ULID/string-compatible morph columns for EvoLayer ULID models before migrating on PostgreSQL.',
            ];
        })->all();
    }
}
