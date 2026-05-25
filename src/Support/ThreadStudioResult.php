<?php

namespace Xuple\EvoLayer\Base\Support;

use InvalidArgumentException;

class ThreadStudioResult
{
    public function __construct(
        public readonly string $summary,
        public readonly string $urgency,
        public readonly string $sentiment,
        public readonly string $recommendedTone,
        public readonly string $customerReply,
        public readonly string $internalNote,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{missing_fields: list<string>, invalid_fields: list<array{field: string, reason: string}>}
     */
    public static function diagnostics(array $payload): array
    {
        $missingFields = [];
        $invalidFields = [];

        $requiredFields = [
            'summary',
            'urgency',
            'sentiment',
            'recommended_tone',
            'customer_reply',
            'internal_note',
        ];

        foreach ($requiredFields as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || trim($payload[$field]) === '') {
                $missingFields[] = $field;
            }
        }

        if (isset($payload['urgency']) && is_string($payload['urgency']) && trim($payload['urgency']) !== '') {
            $validUrgency = ['low', 'medium', 'high'];
            if (! in_array($payload['urgency'], $validUrgency, true)) {
                $invalidFields[] = [
                    'field' => 'urgency',
                    'reason' => 'not in enum ['.implode(', ', $validUrgency).']',
                ];
            }
        }

        if (isset($payload['sentiment']) && is_string($payload['sentiment']) && trim($payload['sentiment']) !== '') {
            $validSentiment = ['calm', 'confused', 'frustrated', 'angry'];
            if (! in_array($payload['sentiment'], $validSentiment, true)) {
                $invalidFields[] = [
                    'field' => 'sentiment',
                    'reason' => 'not in enum ['.implode(', ', $validSentiment).']',
                ];
            }
        }

        return [
            'missing_fields' => $missingFields,
            'invalid_fields' => $invalidFields,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): static
    {
        $diagnostics = self::diagnostics($payload);

        if ($diagnostics['missing_fields'] !== [] || $diagnostics['invalid_fields'] !== []) {
            $parts = [];

            if ($diagnostics['missing_fields'] !== []) {
                $parts[] = 'missing: '.implode(', ', $diagnostics['missing_fields']);
            }

            if ($diagnostics['invalid_fields'] !== []) {
                $invalidLabels = array_column($diagnostics['invalid_fields'], 'field');
                $parts[] = 'invalid: '.implode(', ', $invalidLabels);
            }

            throw new InvalidArgumentException(
                'The AI provider response is incomplete or invalid ('.implode('; ', $parts).').'
            );
        }

        return new static(
            summary: $payload['summary'],
            urgency: $payload['urgency'],
            sentiment: $payload['sentiment'],
            recommendedTone: $payload['recommended_tone'],
            customerReply: $payload['customer_reply'],
            internalNote: $payload['internal_note'],
        );
    }

    /**
     * @return array{
     *     summary: string,
     *     urgency: string,
     *     sentiment: string,
     *     recommended_tone: string,
     *     customer_reply: string,
     *     internal_note: string
     * }
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'urgency' => $this->urgency,
            'sentiment' => $this->sentiment,
            'recommended_tone' => $this->recommendedTone,
            'customer_reply' => $this->customerReply,
            'internal_note' => $this->internalNote,
        ];
    }
}
