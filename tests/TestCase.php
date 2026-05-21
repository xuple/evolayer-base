<?php

namespace EvoDevOps\Base\Tests;

use EvoDevOps\Base\BaseServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BaseServiceProvider::class,
        ];
    }
}
