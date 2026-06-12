<?php

namespace Xuple\EvoLayer\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Xuple\EvoLayer\Base\Console\Commands\Concerns\InteractsWithResyncManifest;
use Xuple\EvoLayer\Base\Support\PublishMap;

#[Signature('evolayer:eject {surface : The example surface to take ownership of}')]
#[Description('Take ownership of a managed example surface so evolayer:resync no longer overwrites it.')]
class EjectCommand extends Command
{
    use InteractsWithResyncManifest;

    public function handle(PublishMap $map): int
    {
        $surface = (string) $this->argument('surface');
        $features = $map->features();

        if (! isset($features[$surface])) {
            $this->components->error("Unknown or non-ejectable surface [{$surface}]. Core runtime is never ejectable; pick an example surface:");
            $this->components->bulletList($map->ejectableSurfaces());

            return self::FAILURE;
        }

        $manifest = $this->readManifest($map->manifestPath());

        if (($manifest['surfaces'][$surface] ?? null) === 'ejected') {
            $this->components->info("[{$surface}] is already ejected — you own it.");

            return self::SUCCESS;
        }

        foreach ($map->expand($features[$surface]) as $source => $target) {
            // Make sure the host actually has the files before handing ownership
            // over; if they never published this surface, materialise it now.
            if (! file_exists($target)) {
                @mkdir(dirname($target), 0755, true);
                copy($source, $target);
            }

            $manifest['files'][$this->relativeKey($target)] = [
                'surface' => $surface,
                'source_sha' => hash_file('sha256', $source),
                'installed_sha' => hash_file('sha256', $target),
            ];
        }

        $manifest['surfaces'][$surface] = 'ejected';
        $this->writeManifest($map->manifestPath(), $manifest);

        $this->components->warn("Ejected [{$surface}]. Those files are now app-owned — evolayer:resync will no longer update them, so you forfeit managed updates for this surface.");

        return self::SUCCESS;
    }
}
