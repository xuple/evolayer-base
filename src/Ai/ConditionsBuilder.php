<?php

namespace Xuple\EvoLayer\Base\Ai;

/**
 * Synthesises conditions-lite observation tuples for the capability ledger
 * (ADR-019). A condition is `(type, status ∈ {True, False, Unknown},
 * reason, message, schema_hash, observed_at)`.
 *
 * The trichotomy is load-bearing: `Unknown` ("not exercised") must never
 * collapse into `False` ("exercised and failed"). A probe that short-
 * circuits before running the agent — e.g. missing credentials — observed
 * nothing about structured streaming and records `Unknown`, not `False`.
 */
class ConditionsBuilder
{
    public const STATUS_TRUE = 'True';

    public const STATUS_FALSE = 'False';

    public const STATUS_UNKNOWN = 'Unknown';

    public const TYPE_STRUCTURED_STREAMING = 'StructuredStreaming';

    /**
     * Build the `StructuredStreaming` condition from a probe outcome.
     *
     * @param  bool  $exercised  Did the probe actually run the agent against the provider/model?
     * @param  bool  $passed  If exercised, did structured output validate?
     * @return array<string, string>
     */
    public function structuredStreaming(
        bool $exercised,
        bool $passed,
        string $reason,
        string $message,
        string $schemaHash,
    ): array {
        $status = match (true) {
            ! $exercised => self::STATUS_UNKNOWN,
            $passed => self::STATUS_TRUE,
            default => self::STATUS_FALSE,
        };

        return [
            'type' => self::TYPE_STRUCTURED_STREAMING,
            'status' => $status,
            'reason' => $reason,
            'message' => $message,
            'schema_hash' => $schemaHash,
            'observed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The `probe_passed` boolean projection: true only when the
     * StructuredStreaming condition is observed `True`. Keeps the legacy
     * boolean and the conditions array derived from one source so they
     * cannot drift (an `Unknown` row projects to `false`, not a silent
     * pass).
     *
     * @param  list<array<string, mixed>>  $conditions
     */
    public function structuredStreamingPassed(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (($condition['type'] ?? null) === self::TYPE_STRUCTURED_STREAMING) {
                return ($condition['status'] ?? null) === self::STATUS_TRUE;
            }
        }

        return false;
    }
}
