<?php

namespace EvoDevOps\Base;

use EvoDevOps\Base\Auth\DefaultUserResolver;
use EvoDevOps\Base\Auth\SpatieAdminGate;
use EvoDevOps\Base\Console\Commands\DoctorCommand;
use EvoDevOps\Base\Contracts\AdminGate;
use EvoDevOps\Base\Contracts\UserResolver;
use Illuminate\Support\ServiceProvider;

class BaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminGate::class, SpatieAdminGate::class);
        $this->app->singleton(UserResolver::class, DefaultUserResolver::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DoctorCommand::class,
            ]);
        }
    }
}
