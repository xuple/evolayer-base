<?php

namespace EvoDevOps\Base\Support;

use EvoDevOps\Base\Models\AiInvocation;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AiInvocationRecorder
{
    public function start(array $context): AiInvocation
    {
        $started = now();

        $invocation = AiInvocation::create([
            'user_id' => Auth::id(),
            'feature_key' => $context['feature_key'],
            'route_name' => $context['route_name'] ?? null,
            'status' => 'started',
            'request_projection' => $context['request_projection'],
            'raw_request' => $context['raw_request'] ?? null,
            'started_at' => $started,
        ]);

        $invocation->attempts()->create([
            'attempt' => 1,
            'provider' => $context['provider'],
            'provider_driver' => $context['provider_driver'] ?? null,
            'model' => $context['model'] ?? null,
            'capability_status' => $context['capability_status'] ?? null,
            'output_mode' => $context['output_mode'] ?? null,
            'status' => 'started',
            'started_at' => $started,
        ]);

        return $invocation->load('attempts');
    }

    public function recordSuccess(
        AiInvocation $invocation,
        array $responseProjection,
        ?array $rawResponse,
        array $attemptContext,
    ): void {
        $finished = now();
        $started = $invocation->started_at;
        $durationMs = $started ? (int) $started->diffInMilliseconds($finished) : null;

        $invocation->update([
            'status' => 'succeeded',
            'response_projection' => $responseProjection,
            'raw_response' => $rawResponse,
            'finished_at' => $finished,
            'duration_ms' => $durationMs,
        ]);

        $attempt = $invocation->attempts->first();
        if ($attempt) {
            $attempt->update([
                'status' => 'succeeded',
                'response_keys' => $attemptContext['response_keys'] ?? null,
                'missing_fields' => $attemptContext['missing_fields'] ?? [],
                'invalid_fields' => $attemptContext['invalid_fields'] ?? [],
                'finished_at' => $finished,
                'duration_ms' => $durationMs,
            ]);
        }
    }

    public function recordFailure(
        AiInvocation $invocation,
        string $failureType,
        ?Throwable $exception,
        array $attemptContext,
        ?array $rawResponse = null,
        array $missingFields = [],
        array $invalidFields = [],
    ): void {
        $finished = now();
        $started = $invocation->started_at;
        $durationMs = $started ? (int) $started->diffInMilliseconds($finished) : null;

        $invocation->update([
            'status' => 'failed',
            'failure_type' => $failureType,
            'failure_message' => $exception?->getMessage(),
            'exception_class' => $exception ? get_class($exception) : null,
            'raw_response' => $rawResponse,
            'finished_at' => $finished,
            'duration_ms' => $durationMs,
        ]);

        $attempt = $invocation->attempts->first();
        if ($attempt) {
            $attempt->update([
                'status' => 'failed',
                'response_keys' => $attemptContext['response_keys'] ?? null,
                'missing_fields' => $missingFields,
                'invalid_fields' => $invalidFields,
                'exception_class' => $exception ? get_class($exception) : null,
                'exception_message' => $exception?->getMessage(),
                'finished_at' => $finished,
                'duration_ms' => $durationMs,
            ]);
        }
    }
}
