<?php

namespace Xuple\EvoLayer\Base\Support;

final readonly class ManagedSurface
{
    /**
     * @param  array<string, string>  $paths
     */
    public function __construct(
        public string $id,
        public string $configKey,
        public string $routeFile,
        public bool $ejectable,
        public array $paths,
    ) {}
}
