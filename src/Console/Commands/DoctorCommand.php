<?php

namespace EvoDevOps\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('evodevops:doctor')]
#[Description('Check the EvoDevOps Base package installation for known issues. Full checks land in Phase C3.')]
class DoctorCommand extends Command
{
    public function handle(): int
    {
        $this->info('evodevops:doctor: nothing to check yet (Phase B scaffolding).');

        return self::SUCCESS;
    }
}
