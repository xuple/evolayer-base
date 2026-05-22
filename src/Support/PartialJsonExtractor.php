<?php

namespace EvoDevOps\Base\Support;

/**
 * Streaming extractor for top-level JSON objects whose values are all strings.
 *
 * As chunks arrive, decoded characters are emitted as field deltas suitable
 * for progressive UI reveal. ThreadStudio's structured output schema is the
 * intended consumer (six string fields); the extractor does not handle nested
 * objects, arrays, numbers, or booleans because the schema does not need them.
 *
 * Once the closing `}` is seen, `tryParseComplete()` returns the assembled
 * field map. Trailing data after the closing brace is ignored.
 */
final class PartialJsonExtractor
{
    private const STATE_OUTSIDE = 0;

    private const STATE_SEEKING_KEY = 1;

    private const STATE_IN_KEY = 2;

    private const STATE_SEEKING_COLON = 3;

    private const STATE_SEEKING_VALUE = 4;

    private const STATE_IN_VALUE = 5;

    private const STATE_BETWEEN_FIELDS = 6;

    private const STATE_DONE = 7;

    private const ESCAPE_NONE = 0;

    private const ESCAPE_PENDING = 1;

    private const ESCAPE_UNICODE = 2;

    /** @var array<string, string> */
    private const SIMPLE_ESCAPES = [
        '"' => '"',
        '\\' => '\\',
        '/' => '/',
        'b' => "\x08",
        'f' => "\x0C",
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
    ];

    private int $state = self::STATE_OUTSIDE;

    private int $escape = self::ESCAPE_NONE;

    private string $unicodeHex = '';

    private string $currentKey = '';

    private string $currentValue = '';

    /** @var array<string, string> */
    private array $completed = [];

    /**
     * Feed a chunk and return any field deltas emitted while consuming it.
     *
     * Each entry is shaped:
     *   ['name' => string, 'delta' => string, 'complete' => bool]
     *
     * Multiple decoded characters within one chunk are batched into a single
     * delta entry per field. A `complete: true` entry is emitted with an
     * empty delta when a field's closing quote is seen.
     *
     * @return list<array{name: string, delta: string, complete: bool}>
     */
    public function feed(string $chunk): array
    {
        $deltas = [];
        $pendingKey = null;
        $pendingDelta = '';

        $flush = function () use (&$deltas, &$pendingKey, &$pendingDelta): void {
            if ($pendingKey !== null && $pendingDelta !== '') {
                $deltas[] = [
                    'name' => $pendingKey,
                    'delta' => $pendingDelta,
                    'complete' => false,
                ];
                $pendingDelta = '';
            }
        };

        $appendDecoded = function (string $decoded) use (&$pendingKey, &$pendingDelta, $flush): void {
            if ($pendingKey !== null && $pendingKey !== $this->currentKey) {
                $flush();
            }
            $pendingKey = $this->currentKey;
            $pendingDelta .= $decoded;
            $this->currentValue .= $decoded;
        };

        $len = strlen($chunk);
        for ($i = 0; $i < $len; $i++) {
            $char = $chunk[$i];

            switch ($this->state) {
                case self::STATE_OUTSIDE:
                    if ($char === '{') {
                        $this->state = self::STATE_SEEKING_KEY;
                    }
                    break;

                case self::STATE_SEEKING_KEY:
                    if ($char === '"') {
                        $this->state = self::STATE_IN_KEY;
                        $this->currentKey = '';
                    } elseif ($char === '}') {
                        $this->state = self::STATE_DONE;
                    }
                    break;

                case self::STATE_IN_KEY:
                    if ($char === '"') {
                        $this->state = self::STATE_SEEKING_COLON;
                    } else {
                        $this->currentKey .= $char;
                    }
                    break;

                case self::STATE_SEEKING_COLON:
                    if ($char === ':') {
                        $this->state = self::STATE_SEEKING_VALUE;
                    }
                    break;

                case self::STATE_SEEKING_VALUE:
                    if ($char === '"') {
                        $this->state = self::STATE_IN_VALUE;
                        $this->currentValue = '';
                        $this->escape = self::ESCAPE_NONE;
                    }
                    break;

                case self::STATE_IN_VALUE:
                    if ($this->escape === self::ESCAPE_NONE) {
                        if ($char === '\\') {
                            $this->escape = self::ESCAPE_PENDING;
                        } elseif ($char === '"') {
                            $flush();
                            $this->completed[$this->currentKey] = $this->currentValue;
                            $deltas[] = [
                                'name' => $this->currentKey,
                                'delta' => '',
                                'complete' => true,
                            ];
                            $pendingKey = null;
                            $this->state = self::STATE_BETWEEN_FIELDS;
                        } else {
                            $appendDecoded($char);
                        }
                    } elseif ($this->escape === self::ESCAPE_PENDING) {
                        if (isset(self::SIMPLE_ESCAPES[$char])) {
                            $appendDecoded(self::SIMPLE_ESCAPES[$char]);
                            $this->escape = self::ESCAPE_NONE;
                        } elseif ($char === 'u') {
                            $this->escape = self::ESCAPE_UNICODE;
                            $this->unicodeHex = '';
                        } else {
                            $appendDecoded($char);
                            $this->escape = self::ESCAPE_NONE;
                        }
                    } else {
                        $this->unicodeHex .= $char;
                        if (strlen($this->unicodeHex) === 4) {
                            $codepoint = hexdec($this->unicodeHex);
                            $utf8 = mb_chr($codepoint, 'UTF-8');
                            if (is_string($utf8)) {
                                $appendDecoded($utf8);
                            }
                            $this->escape = self::ESCAPE_NONE;
                            $this->unicodeHex = '';
                        }
                    }
                    break;

                case self::STATE_BETWEEN_FIELDS:
                    if ($char === ',') {
                        $this->state = self::STATE_SEEKING_KEY;
                    } elseif ($char === '}') {
                        $this->state = self::STATE_DONE;
                    }
                    break;

                case self::STATE_DONE:
                    break;
            }
        }

        $flush();

        return $deltas;
    }

    /**
     * @return array<string, string>|null
     */
    public function tryParseComplete(): ?array
    {
        return $this->state === self::STATE_DONE ? $this->completed : null;
    }

    public function isComplete(): bool
    {
        return $this->state === self::STATE_DONE;
    }
}
