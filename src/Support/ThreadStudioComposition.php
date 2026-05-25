<?php

namespace Xuple\EvoLayer\Base\Support;

final readonly class ThreadStudioComposition
{
    public function __construct(
        public ThreadStudioResult $result,
        public string $invocationId,
        public ?int $durationMs,
    ) {}
}
