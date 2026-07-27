<?php

namespace Xuple\EvoLayer\Base\Support;

use InvalidArgumentException;

final readonly class ManagedRoute
{
    /** @param non-empty-list<string> $methods */
    public function __construct(
        public array $methods,
        public string $uri,
        public string $name,
        public ?string $domain = null,
    ) {
        if ($methods === [] || $uri === '' || $name === '') {
            throw new InvalidArgumentException('Managed route contracts require methods, a URI, and a name.');
        }
    }
}
