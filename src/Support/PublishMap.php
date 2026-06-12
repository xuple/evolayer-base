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
            $r.'/resources/js/layouts' => resource_path('js/layouts'),
            $r.'/resources/js/config' => resource_path('js/config'),
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
        $r = $this->packageRoot();

        return [
            'thread-studio' => [
                $r.'/resources/js/pages/evolayer/ai/thread-studio.tsx' => resource_path('js/pages/evolayer/ai/thread-studio.tsx'),
                $r.'/resources/js/hooks/use-thread-studio-stream.ts' => resource_path('js/hooks/use-thread-studio-stream.ts'),
                $r.'/resources/js/hooks/use-typewriter.ts' => resource_path('js/hooks/use-typewriter.ts'),
            ],
            'prd-studio' => [
                $r.'/resources/js/pages/evolayer/admin/prd.tsx' => resource_path('js/pages/evolayer/admin/prd.tsx'),
            ],
            'admin-inbox' => [
                $r.'/resources/js/pages/evolayer/admin/inbox' => resource_path('js/pages/evolayer/admin/inbox'),
                $r.'/resources/js/pages/evolayer/admin/submissions' => resource_path('js/pages/evolayer/admin/submissions'),
            ],
            'contact-ai' => [
                $r.'/resources/js/pages/evolayer/contact.tsx' => resource_path('js/pages/evolayer/contact.tsx'),
                $r.'/resources/js/pages/evolayer/contact-thank-you.tsx' => resource_path('js/pages/evolayer/contact-thank-you.tsx'),
            ],
            'marketing-pages' => [
                $r.'/resources/js/pages/evolayer/about.tsx' => resource_path('js/pages/evolayer/about.tsx'),
                $r.'/resources/js/pages/evolayer/home.tsx' => resource_path('js/pages/evolayer/home.tsx'),
            ],
        ];
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
