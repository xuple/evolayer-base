<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

final readonly class ProfileVerificationContext
{
    /**
     * @param  array<string, mixed>  $status
     * @param  array{intent: string, effective: string, manifest: string, managed: string}  $bindings
     */
    public function __construct(
        public ExpectedProfileState $expected,
        public array $status,
        public array $bindings,
        public string $baseVersion,
        public ?string $baseReference,
        public ?string $hostVersion,
        public ?string $hostReference,
    ) {}
}
