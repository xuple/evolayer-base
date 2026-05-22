<?php

namespace EvoDevOps\Base\Support;

use Illuminate\Http\Request;

class Appearance
{
    public const COOKIE_NAME = 'appearance';

    public const DEFAULT_INTENT = 'system';

    /**
     * @var list<string>
     */
    public const INTENTS = ['light', 'dark', 'system'];

    public const LIGHT_THEME_COLOR = '#ffffff';

    public const DARK_THEME_COLOR = '#0a0a0a';

    public static function intentFromRequest(Request $request): string
    {
        return self::normalizeIntent(
            $request->cookie(self::cookieName(), self::defaultIntent()),
        );
    }

    public static function normalizeIntent(mixed $intent): string
    {
        return in_array($intent, self::intents(), true)
            ? $intent
            : self::defaultIntent();
    }

    public static function resolveForServer(string $intent): string
    {
        return $intent === 'dark' ? 'dark' : 'light';
    }

    public static function cookieName(): string
    {
        return (string) config('appearance.cookie_name', self::COOKIE_NAME);
    }

    public static function defaultIntent(): string
    {
        $defaultIntent = (string) config('appearance.default_intent', self::DEFAULT_INTENT);

        return in_array($defaultIntent, self::INTENTS, true)
            ? $defaultIntent
            : self::DEFAULT_INTENT;
    }

    /**
     * @return list<string>
     */
    public static function intents(): array
    {
        $configuredIntents = config('appearance.intents', self::INTENTS);

        if (! is_array($configuredIntents)) {
            return self::INTENTS;
        }

        $intents = array_values(array_filter(
            $configuredIntents,
            fn (mixed $intent): bool => in_array($intent, self::INTENTS, true),
        ));

        return $intents !== [] ? $intents : self::INTENTS;
    }

    /**
     * @return array{light: string, dark: string}
     */
    public static function themeColors(): array
    {
        return [
            'light' => (string) config('appearance.theme_color.light', self::LIGHT_THEME_COLOR),
            'dark' => (string) config('appearance.theme_color.dark', self::DARK_THEME_COLOR),
        ];
    }

    /**
     * @return array{
     *     intent: string,
     *     resolved: string,
     *     cookieName: string,
     *     defaultIntent: string,
     *     themeColors: array{light: string, dark: string}
     * }
     */
    public static function payloadFromRequest(Request $request): array
    {
        $intent = self::intentFromRequest($request);

        return [
            'intent' => $intent,
            'resolved' => self::resolveForServer($intent),
            'cookieName' => self::cookieName(),
            'defaultIntent' => self::defaultIntent(),
            'themeColors' => self::themeColors(),
        ];
    }
}
