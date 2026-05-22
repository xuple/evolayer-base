<?php

namespace EvoDevOps\Base\Support;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Centralised schema-hash and probe-schema derivation for the AI capability ledger.
 *
 * All write sites (ai:probe --persist, AiCapabilitySeeder) must call these
 * helpers — never compute the hash or probe_schema label ad-hoc at call sites.
 */
final class AiCapabilityHash
{
    /**
     * Compute the canonical SHA-256 hash of an agent's JSON Schema output.
     *
     * The hash is derived from the *resolved* Type tree (what the SDK serialises
     * into the request), not from the agent's PHP source. This mirrors what
     * ThreadStudioComposer already passes to the recorder as:
     *   'schema' => $agent->schema(new JsonSchemaTypeFactory)
     *
     * Canonical encoding: recursive ksort, then json_encode with
     * JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR.
     *
     * @param  array<string, mixed>  $schema  Raw output of $agent->schema(new JsonSchemaTypeFactory)
     */
    public static function fromSchema(array $schema): string
    {
        return hash('sha256', self::canonicalise($schema));
    }

    /**
     * Resolve the schema hash directly from an agent instance.
     */
    public static function fromAgent(HasStructuredOutput $agent): string
    {
        return self::fromSchema($agent->schema(new JsonSchemaTypeFactory));
    }

    /**
     * Derive the snake_cased probe_schema label from an agent class name.
     *
     * Convention: strip the namespace and the "Agent" suffix, then convert
     * the remainder to snake_case. ThreadStudioAgent → thread_studio.
     */
    public static function probeSchema(string $agentClass): string
    {
        $shortName = class_basename($agentClass);

        // Strip trailing "Agent" suffix if present.
        if (str_ends_with($shortName, 'Agent')) {
            $shortName = substr($shortName, 0, -strlen('Agent'));
        }

        // Convert PascalCase → snake_case.
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName) ?? $shortName);
    }

    /**
     * Recursively ksort and json_encode for a stable, canonical representation.
     *
     * @param  array<string, mixed>  $data
     */
    private static function canonicalise(array $data): string
    {
        self::deepKsort($data);

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function deepKsort(array &$data): void
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::deepKsort($value);
            }
        }
        ksort($data);
    }
}
