<?php

namespace Xuple\EvoLayer\Base\Ai;

/**
 * The outcome of a single capability probe — a pure observation of what
 * happened when an agent was prompted against a provider/model.
 *
 * Per ADR-019 this is an observation, not a policy decision. It carries
 * everything the ledger needs to persist (status, output_mode, latency,
 * conditions) plus the decoded payload so console consumers (the smoke
 * command) can rebuild their own messages without re-running the agent.
 *
 * `toLegacyArray()` preserves the `array{ok, message}` contract the three
 * commands passed around before the probe service was extracted.
 */
final class ProbeResult
{
    /**
     * @param  'supported'|'blocked'|'unsupported'|'unknown'  $status
     * @param  list<array<string, mixed>>  $conditions
     * @param  array<string, mixed>|null  $payload
     * @param  string|null  $model  The model actually probed (post provider-specific default resolution); null when the probe short-circuited before resolving one.
     * @param  string|null  $schemaHash  The agent schema hash the probe ran against.
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly string $outputMode,
        public readonly string $status,
        public readonly array $conditions = [],
        public readonly ?int $latencyMs = null,
        public readonly ?array $payload = null,
        public readonly ?string $model = null,
        public readonly ?string $schemaHash = null,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function toLegacyArray(): array
    {
        return ['ok' => $this->ok, 'message' => $this->message];
    }
}
