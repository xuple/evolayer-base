<?php

namespace Xuple\EvoLayer\Base\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Canonical source→target map for EvoLayer Base's publishable frontend.
 *
 * Single source of truth shared by BaseServiceProvider's vendor:publish tags
 * and the evolayer:resync / evolayer:eject commands, so the two never drift.
 */
class PublishMap
{
    /**
     * Canonical definitions for every flag-gated example surface.
     *
     * @return array<string, ManagedSurface>
     */
    public function surfaces(): array
    {
        $r = $this->packageRoot();

        return [
            'marketing-pages' => new ManagedSurface(
                id: 'marketing-pages',
                configKey: 'marketing_pages',
                routeFile: $r.'/routes/features/marketing_pages.php',
                ejectable: true,
                paths: [
                    $r.'/resources/js/pages/evolayer/base.tsx' => resource_path('js/pages/evolayer/base.tsx'),
                ],
                routes: [new ManagedRoute(['GET', 'HEAD'], 'about', 'evolayer.base.about')],
            ),
            'contact-ai' => new ManagedSurface(
                id: 'contact-ai',
                configKey: 'contact_ai',
                routeFile: $r.'/routes/features/contact_ai.php',
                ejectable: true,
                paths: [
                    $r.'/resources/js/pages/evolayer/contact.tsx' => resource_path('js/pages/evolayer/contact.tsx'),
                    $r.'/resources/js/pages/evolayer/contact-thank-you.tsx' => resource_path('js/pages/evolayer/contact-thank-you.tsx'),
                ],
                routes: [
                    new ManagedRoute(['GET', 'HEAD'], 'contact', 'evolayer.base.contact'),
                    new ManagedRoute(['POST'], 'contact', 'evolayer.base.contact.store'),
                    new ManagedRoute(['GET', 'HEAD'], 'contact/thank-you', 'evolayer.base.contact.thank-you'),
                    new ManagedRoute(['GET', 'HEAD'], 'contact/subject-hints', 'evolayer.base.contact.subject-hints'),
                ],
            ),
            'admin-inbox' => new ManagedSurface(
                id: 'admin-inbox',
                configKey: 'admin_inbox',
                routeFile: $r.'/routes/features/admin_inbox.php',
                ejectable: true,
                paths: [
                    $r.'/resources/js/pages/evolayer/admin/inbox' => resource_path('js/pages/evolayer/admin/inbox'),
                    $r.'/resources/js/pages/evolayer/admin/submissions' => resource_path('js/pages/evolayer/admin/submissions'),
                ],
                routes: [
                    new ManagedRoute(['GET', 'HEAD'], 'admin/inbox', 'evolayer.base.admin.inbox.show'),
                    new ManagedRoute(['GET', 'HEAD'], 'admin/inbox/search', 'evolayer.base.admin.inbox.search'),
                    new ManagedRoute(['GET', 'HEAD'], 'admin/inbox/{submission}', 'evolayer.base.admin.inbox.detail'),
                    new ManagedRoute(['GET', 'HEAD'], 'admin/submissions', 'evolayer.base.admin.submissions.index'),
                    new ManagedRoute(['GET', 'HEAD'], 'admin/submissions/{submission}', 'evolayer.base.admin.submissions.show'),
                    new ManagedRoute(['PATCH'], 'admin/submissions/{submission}/mark-read', 'evolayer.base.admin.submissions.mark-read'),
                    new ManagedRoute(['PATCH'], 'admin/submissions/{submission}/archive', 'evolayer.base.admin.submissions.archive'),
                ],
            ),
            'prd-studio' => new ManagedSurface(
                id: 'prd-studio',
                configKey: 'prd_studio',
                routeFile: $r.'/routes/features/prd_studio.php',
                ejectable: true,
                paths: [
                    $r.'/resources/js/pages/evolayer/admin/prd.tsx' => resource_path('js/pages/evolayer/admin/prd.tsx'),
                ],
                routes: [
                    new ManagedRoute(['GET', 'HEAD'], 'admin/prd', 'evolayer.base.admin.prd.show'),
                    new ManagedRoute(['POST'], 'admin/prd/generate', 'evolayer.base.admin.prd.generate'),
                ],
            ),
            'thread-studio' => new ManagedSurface(
                id: 'thread-studio',
                configKey: 'thread_studio',
                routeFile: $r.'/routes/features/thread_studio.php',
                ejectable: true,
                paths: [
                    $r.'/resources/js/pages/evolayer/ai/thread-studio.tsx' => resource_path('js/pages/evolayer/ai/thread-studio.tsx'),
                    $r.'/resources/js/hooks/use-thread-studio-stream.ts' => resource_path('js/hooks/use-thread-studio-stream.ts'),
                    $r.'/resources/js/hooks/use-typewriter.ts' => resource_path('js/hooks/use-typewriter.ts'),
                ],
                routes: [
                    new ManagedRoute(['GET', 'HEAD'], 'ai/thread-studio', 'evolayer.base.ai.thread-studio.show'),
                    new ManagedRoute(['POST'], 'ai/thread-studio', 'evolayer.base.ai.thread-studio.store'),
                    new ManagedRoute(['POST'], 'ai/thread-studio/stream', 'evolayer.base.ai.thread-studio.stream'),
                ],
            ),
            'voice-input' => new ManagedSurface(
                id: 'voice-input',
                configKey: 'voice_input',
                routeFile: $r.'/routes/features/voice_input.php',
                ejectable: false,
                paths: [],
                routes: [
                    new ManagedRoute(['POST'], 'ai/voice-input/transcribe', 'evolayer.base.ai.voice-input.transcribe'),
                ],
            ),
            'ai-text-field' => new ManagedSurface(
                id: 'ai-text-field',
                configKey: 'ai_text_field',
                routeFile: $r.'/routes/features/ai_text_field.php',
                ejectable: false,
                paths: [],
                routes: [
                    new ManagedRoute(['POST'], 'ai/text-assist/stream', 'evolayer.base.ai.text-assist.stream'),
                ],
            ),
        ];
    }

    public function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Core frontend primitives (no feature flag). Never ejectable.
     *
     * @return array<string, string> absolute source => host target
     */
    public function core(): array
    {
        $r = $this->packageRoot();

        return [
            $r.'/resources/js/blocks' => resource_path('js/blocks'),
            $r.'/resources/js/components' => resource_path('js/components'),
            $r.'/resources/js/providers' => resource_path('js/providers'),
            $r.'/resources/js/config/command-palette.ts' => resource_path('js/config/command-palette.ts'),
            $r.'/resources/js/config/docs.ts' => resource_path('js/config/docs.ts'),
            $r.'/resources/js/hooks/use-brand.ts' => resource_path('js/hooks/use-brand.ts'),
            $r.'/resources/js/hooks/use-evolayer-props.ts' => resource_path('js/hooks/use-evolayer-props.ts'),
            $r.'/resources/js/hooks/use-example-nav-items.ts' => resource_path('js/hooks/use-example-nav-items.ts'),
            $r.'/resources/js/types/layout.ts' => resource_path('js/types/layout.ts'),
            $r.'/resources/js/types/evolayer.d.ts' => resource_path('js/types/evolayer.d.ts'),
            $r.'/resources/js/lib/appearance.ts' => resource_path('js/lib/appearance.ts'),
            $r.'/resources/js/lib/platform.ts' => resource_path('js/lib/platform.ts'),
        ];
    }

    /**
     * Per-feature page sets, keyed by ejectable surface name. Each surface
     * mirrors a routes/features/*.php file and an EVOLAYER_BASE_EXAMPLE_* flag.
     *
     * @return array<string, array<string, string>>
     */
    public function features(): array
    {
        return collect($this->surfaces())
            ->filter(fn (ManagedSurface $surface): bool => $surface->ejectable)
            ->map(fn (ManagedSurface $surface): array => $surface->paths)
            ->all();
    }

    /**
     * Surfaces a host may eject (take ownership of). Core is never ejectable.
     *
     * @return list<string>
     */
    public function ejectableSurfaces(): array
    {
        return array_keys($this->features());
    }

    /**
     * Absolute path to the host app's resync manifest.
     */
    public function manifestPath(): string
    {
        return base_path('.evolayer/resync.lock.json');
    }

    public function projectMetadataPath(): string
    {
        return $this->hostRoot().'/.evolayer/project.json';
    }

    public function verificationReceiptPath(): string
    {
        return $this->hostRoot().'/storage/framework/cache/data/evolayer-profile-verification.json';
    }

    public function hostRoot(): string
    {
        return base_path();
    }

    public function manifestKey(string $target): string
    {
        $root = rtrim(str_replace('\\', '/', $this->hostRoot()), '/');
        $target = str_replace('\\', '/', $target);

        if (! str_starts_with($target, $root.'/')) {
            throw new ResyncManifestException("Managed target [{$target}] escapes the host root [{$root}].");
        }

        $key = substr($target, strlen($root) + 1);
        $this->assertValidManifestKey($key);

        return $key;
    }

    public function assertValidManifestKey(string $key): void
    {
        if ($key === ''
            || str_contains($key, "\0")
            || str_contains($key, '\\')
            || str_starts_with($key, '/')
            || preg_match('/^[A-Za-z]:\//', $key) === 1
            || preg_match('#(^|/)(?:\.|\.\.)(?:/|$)#', $key) === 1
            || preg_match('#(^|/)/#', $key) === 1) {
            throw new ResyncManifestException("Invalid resync manifest path [{$key}].");
        }
    }

    /**
     * @return array<string, array{surface: string, source: string, target: string}>
     */
    public function managedFiles(): array
    {
        $files = [];

        foreach (array_merge(['core' => $this->core()], $this->features()) as $surface => $pairs) {
            foreach ($this->expand($pairs) as $source => $target) {
                $key = $this->manifestKey($target);

                if (isset($files[$key])) {
                    throw new ResyncManifestException("Managed path [{$key}] is owned by more than one descriptor.");
                }

                $files[$key] = compact('surface', 'source', 'target');
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Expand a source=>target pair list into individual file pairs, recursing
     * into directories so the resync command can checksum each file.
     *
     * @param  array<string, string>  $pairs
     * @return array<string, string> absolute source file => target file
     */
    public function expand(array $pairs): array
    {
        $out = [];

        foreach ($pairs as $source => $target) {
            if (is_dir($source)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $relative = substr($file->getPathname(), strlen($source) + 1);
                        $out[$file->getPathname()] = $target.'/'.str_replace('\\', '/', $relative);
                    }
                }
            } elseif (is_file($source)) {
                $out[$source] = $target;
            }
        }

        return $out;
    }
}
