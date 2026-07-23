<?php

namespace Xuple\EvoLayer\Base\Support\ProfileTransitions;

final class ProfileVerificationFingerprint
{
    public static function hash(mixed $material): string
    {
        return hash('sha256', json_encode(
            self::canonicalize($material),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => self::canonicalize($item), $value);
    }
}
