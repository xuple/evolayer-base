<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

use RuntimeException;
use Throwable;

final class ProfileTransitionExecutionException extends RuntimeException
{
    /**
     * @param  list<string>  $rollbackFailures
     */
    public function __construct(
        Throwable $applyFailure,
        public readonly array $rollbackFailures,
    ) {
        $message = 'Profile transition apply failed: '.$applyFailure->getMessage();

        if ($rollbackFailures !== []) {
            $message .= ' Rollback was incomplete: '.implode(' ', $rollbackFailures);
        }

        parent::__construct($message, previous: $applyFailure);
    }
}
