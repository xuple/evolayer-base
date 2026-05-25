<?php

namespace Xuple\EvoLayer\Base\Support;

use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Generator;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use InvalidArgumentException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

/**
 * @evo-example thread_studio
 */
class ThreadStudioComposer
{
    public function __construct(
        private readonly ThreadStudioAiConfig $aiConfig,
        private readonly AiInvocationRecorder $recorder,
    ) {}

    /**
     * Stream a ThreadStudio composition. Yields events for the controller to surface as SSE frames.
     *
     * Event shapes:
     *   ['type' => 'start']
     *   ['type' => 'field_delta',    'name' => string, 'delta' => string]
     *   ['type' => 'field_complete', 'name' => string]
     *   ['type' => 'done',  'result' => array<string, string>, 'invocation_id' => string, 'duration_ms' => int|null]
     *   ['type' => 'error', 'message' => string, 'failure_type' => 'agent_exception'|'malformed_response'|'incomplete_response',
     *                       'provider' => string, 'model' => string,
     *                       'missing_fields' => list<string>,
     *                       'invalid_fields' => list<array{field: string, reason: string}>]
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function streamCompose(string $customerMessage, string $tone, ?string $provider = null, ?string $model = null): Generator
    {
        yield ['type' => 'start'];

        $resolvedProvider = $this->aiConfig->provider($provider);
        $resolvedModel = $this->aiConfig->resolveModel($resolvedProvider, $model);
        $timeout = $this->aiConfig->timeout();

        $agent = ThreadStudioAgent::make();
        $extractor = new PartialJsonExtractor;

        $context = [
            'feature_key' => 'thread_studio',
            'route_name' => request()?->route()?->getName(),
            'request_projection' => [
                'feature_key' => 'thread_studio',
                'tone' => $tone,
                'customer_message_length' => strlen($customerMessage),
                'provider' => $resolvedProvider,
                'model' => $resolvedModel,
                'schema_field_count' => 6,
            ],
            'raw_request' => [
                'instructions' => (string) $agent->instructions(),
                'prompt' => $this->userPrompt($customerMessage, $tone),
                'provider' => $resolvedProvider,
                'model' => $resolvedModel,
                'provider_options' => $agent->providerOptions($resolvedProvider),
                'schema' => $agent->schema(new JsonSchemaTypeFactory),
                'max_tokens' => $agent->maxTokens(),
                'temperature' => $agent->temperature(),
            ],
            'provider' => $resolvedProvider,
            'provider_driver' => config("ai.providers.{$resolvedProvider}.driver"),
            'model' => $resolvedModel,
            'capability_status' => null,
            'output_mode' => 'stream',
        ];

        $invocation = $this->recorder->start($context);

        try {
            $stream = $agent->stream(
                $this->userPrompt($customerMessage, $tone),
                provider: $resolvedProvider,
                model: $resolvedModel,
                timeout: $timeout,
            );

            foreach ($stream as $event) {
                if (! ($event instanceof TextDelta)) {
                    continue;
                }

                foreach ($extractor->feed($event->delta) as $delta) {
                    if ($delta['complete']) {
                        yield ['type' => 'field_complete', 'name' => $delta['name']];
                    } elseif ($delta['delta'] !== '') {
                        yield ['type' => 'field_delta', 'name' => $delta['name'], 'delta' => $delta['delta']];
                    }
                }
            }

            $fullText = (string) $stream->text;

            try {
                $payload = json_decode($fullText, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new ThreadStudioMalformedResponseException(
                    'The AI provider did not return valid structured ThreadStudio data.'
                );
            }

            if (! is_array($payload)) {
                throw new ThreadStudioMalformedResponseException(
                    'The AI provider did not return structured ThreadStudio data.'
                );
            }

            $diagnostics = ThreadStudioResult::diagnostics($payload);
            $attemptContext = ['response_keys' => array_keys($payload)];

            try {
                $result = ThreadStudioResult::fromArray($payload);
            } catch (InvalidArgumentException $exception) {
                $wrapped = new ThreadStudioIncompleteResponseException(
                    missingFields: $diagnostics['missing_fields'],
                    invalidFields: $diagnostics['invalid_fields'],
                    message: $exception->getMessage(),
                    previous: $exception,
                );

                $this->recorder->recordFailure(
                    invocation: $invocation,
                    failureType: 'incomplete_response',
                    exception: $wrapped,
                    attemptContext: $attemptContext,
                    rawResponse: ['structured' => $payload, 'response_keys' => array_keys($payload)],
                    missingFields: $diagnostics['missing_fields'],
                    invalidFields: $diagnostics['invalid_fields'],
                );

                throw $wrapped;
            }

            $this->recorder->recordSuccess(
                invocation: $invocation,
                responseProjection: $result->toArray(),
                rawResponse: ['structured' => $payload, 'response_keys' => array_keys($payload)],
                attemptContext: array_merge($attemptContext, ['missing_fields' => [], 'invalid_fields' => []]),
            );

            yield [
                'type' => 'done',
                'result' => $result->toArray(),
                'invocation_id' => $invocation->id,
                'duration_ms' => $invocation->duration_ms,
            ];

        } catch (ThreadStudioIncompleteResponseException $exception) {
            report($exception);
            yield $this->errorFrame(
                'incomplete_response',
                $resolvedProvider,
                $resolvedModel,
                missingFields: $exception->missingFields,
                invalidFields: $exception->invalidFields,
            );
        } catch (ThreadStudioMalformedResponseException $exception) {
            report($exception);
            $this->recorder->recordFailure(
                invocation: $invocation,
                failureType: 'malformed_response',
                exception: $exception,
                attemptContext: [],
            );
            yield $this->errorFrame('malformed_response', $resolvedProvider, $resolvedModel);
        } catch (Throwable $exception) {
            report($exception);
            $this->recorder->recordFailure(
                invocation: $invocation,
                failureType: 'agent_exception',
                exception: $exception,
                attemptContext: [],
            );
            yield $this->errorFrame('agent_exception', $resolvedProvider, $resolvedModel);
        }
    }

    /**
     * @param  list<string>  $missingFields
     * @param  list<array{field: string, reason: string}>  $invalidFields
     * @return array{type: 'error', message: string, failure_type: string, provider: string, model: string, missing_fields: list<string>, invalid_fields: list<array{field: string, reason: string}>}
     */
    private function errorFrame(
        string $failureType,
        string $provider,
        string $model,
        array $missingFields = [],
        array $invalidFields = [],
    ): array {
        return [
            'type' => 'error',
            'message' => 'The AI provider did not return a usable reply. Try again in a moment.',
            'failure_type' => $failureType,
            'provider' => $provider,
            'model' => $model,
            'missing_fields' => $missingFields,
            'invalid_fields' => $invalidFields,
        ];
    }

    public function compose(string $customerMessage, string $tone, ?string $provider = null, ?string $model = null): ThreadStudioComposition
    {
        $provider = $this->aiConfig->provider($provider);
        $effectiveModel = $this->aiConfig->resolveModel($provider, $model);
        $timeout = $this->aiConfig->timeout();

        $agent = ThreadStudioAgent::make();

        $capabilityStatus = null;
        $outputMode = null;

        if ($provider === 'opencode') {
            $compatibility = ThreadStudioAiConfig::opencodeModelCompatibility();
            if (isset($compatibility[$effectiveModel])) {
                $capabilityStatus = $compatibility[$effectiveModel]['status'];
                $outputMode = $compatibility[$effectiveModel]['output_mode'];
            }
        }

        $rawRequest = [
            'instructions' => (string) $agent->instructions(),
            'prompt' => $this->userPrompt($customerMessage, $tone),
            'provider' => $provider,
            'model' => $effectiveModel,
            'provider_options' => $agent->providerOptions($provider),
            'schema' => $agent->schema(new JsonSchemaTypeFactory),
            'max_tokens' => $agent->maxTokens(),
            'temperature' => $agent->temperature(),
        ];

        $requestProjection = [
            'feature_key' => 'thread_studio',
            'tone' => $tone,
            'customer_message_length' => strlen($customerMessage),
            'provider' => $provider,
            'model' => $effectiveModel,
            'schema_field_count' => 6,
        ];

        $context = [
            'feature_key' => 'thread_studio',
            'route_name' => request()?->route()?->getName(),
            'request_projection' => $requestProjection,
            'raw_request' => $rawRequest,
            'provider' => $provider,
            'provider_driver' => config("ai.providers.{$provider}.driver"),
            'model' => $effectiveModel,
            'capability_status' => $capabilityStatus,
            'output_mode' => $outputMode,
        ];

        $invocation = $this->recorder->start($context);

        try {
            $response = $agent->prompt(
                prompt: $rawRequest['prompt'],
                provider: $provider,
                model: $effectiveModel,
                timeout: $timeout,
            );
        } catch (Throwable $exception) {
            $this->recorder->recordFailure(
                invocation: $invocation,
                failureType: 'agent_exception',
                exception: $exception,
                attemptContext: [],
            );

            throw $exception;
        }

        if (! $response instanceof StructuredAgentResponse) {
            $exception = new ThreadStudioMalformedResponseException('The AI provider did not return structured ThreadStudio data.');

            $this->recorder->recordFailure(
                invocation: $invocation,
                failureType: 'malformed_response',
                exception: $exception,
                attemptContext: [],
            );

            throw $exception;
        }

        $payload = $response->toArray();
        $diagnostics = ThreadStudioResult::diagnostics($payload);

        $attemptContext = [
            'response_keys' => array_keys($payload),
        ];

        try {
            $result = ThreadStudioResult::fromArray($payload);
        } catch (InvalidArgumentException $exception) {
            $wrapped = new ThreadStudioIncompleteResponseException(
                missingFields: $diagnostics['missing_fields'],
                invalidFields: $diagnostics['invalid_fields'],
                message: $exception->getMessage(),
                previous: $exception,
            );

            $this->recorder->recordFailure(
                invocation: $invocation,
                failureType: 'incomplete_response',
                exception: $wrapped,
                attemptContext: $attemptContext,
                rawResponse: [
                    'structured' => $payload,
                    'response_keys' => array_keys($payload),
                ],
                missingFields: $diagnostics['missing_fields'],
                invalidFields: $diagnostics['invalid_fields'],
            );

            throw $wrapped;
        }

        $rawResponse = [
            'structured' => $payload,
            'response_keys' => array_keys($payload),
        ];

        $this->recorder->recordSuccess(
            invocation: $invocation,
            responseProjection: $result->toArray(),
            rawResponse: $rawResponse,
            attemptContext: [
                'response_keys' => array_keys($payload),
                'missing_fields' => [],
                'invalid_fields' => [],
            ],
        );

        return new ThreadStudioComposition(
            result: $result,
            invocationId: $invocation->id,
            durationMs: $invocation->duration_ms,
        );
    }

    private function userPrompt(string $customerMessage, string $tone): string
    {
        return <<<PROMPT
Preferred reply tone: {$tone}

Support product: EvoDevOps Base Laravel + Inertia starter kit.

Customer message:
{$customerMessage}
PROMPT;
    }
}
