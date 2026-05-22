<?php

namespace EvoDevOps\Base\Support;

use InvalidArgumentException;
use Throwable;

class ThreadStudioIncompleteResponseException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $missingFields
     * @param  list<array{field: string, reason: string}>  $invalidFields
     */
    public function __construct(
        public readonly array $missingFields,
        public readonly array $invalidFields,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
