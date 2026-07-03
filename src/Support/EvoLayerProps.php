<?php

namespace Xuple\EvoLayer\Base\Support;

/**
 * Assembles the `evolayer.base` Inertia shared-prop payload from config.
 *
 * Host apps share this from HandleInertiaRequests::share() (under the
 * `evolayer.base` key) so published pages can read brand, example, and feature
 * state. Brand-via-props is what lets a host rebrand the public explainer
 * (evolayer/base) without the package overwriting the page file — see
 * config('evolayer.base.brand').
 */
class EvoLayerProps
{
    /**
     * @return array{examples: mixed, features: mixed, brand: mixed}
     */
    public static function base(): array
    {
        return [
            'examples' => config('evolayer.base.examples'),
            'features' => config('evolayer.base.features'),
            'brand' => config('evolayer.base.brand'),
        ];
    }
}
