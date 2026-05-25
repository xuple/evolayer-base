<?php

namespace Xuple\EvoLayer\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Models\AiCapability;
use Xuple\EvoLayer\Base\Support\AiCapabilityHash;
use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;

/**
 * Bootstraps ai_capabilities from the static ThreadStudioAiConfig::opencodeModelCompatibility() array.
 *
 * Rows are upserted so the seeder is safe to re-run; human-authored `note` values survive.
 */
class AiCapabilitySeeder extends Seeder
{
    public function run(): void
    {
        $agentClass = ThreadStudioAgent::class;
        $schemaHash = AiCapabilityHash::fromAgent(new ThreadStudioAgent);
        $probeSchema = AiCapabilityHash::probeSchema($agentClass);
        $now = now();

        foreach (ThreadStudioAiConfig::opencodeModelCompatibility() as $model => $info) {
            AiCapability::updateOrCreate(
                [
                    'agent_class' => $agentClass,
                    'provider' => 'opencode',
                    'model' => $model,
                    'schema_hash' => $schemaHash,
                ],
                [
                    'probe_schema' => $probeSchema,
                    'status' => $info['status'],
                    'output_mode' => $info['output_mode'],
                    'probe_passed' => $info['status'] === 'supported',
                    'failure_reason' => null,
                    'latency_ms' => null,
                    'probed_at' => $now,
                ],
            );

            AiCapability::where([
                'agent_class' => $agentClass,
                'provider' => 'opencode',
                'model' => $model,
                'schema_hash' => $schemaHash,
            ])->whereNull('note')->update(['note' => $info['note'] ?? null]);
        }
    }
}
