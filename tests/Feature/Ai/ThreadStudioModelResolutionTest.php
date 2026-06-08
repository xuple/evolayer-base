<?php

use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;

test('defaultModel resolves a real model from the package config even when the SDK shallow-merge drops models from config(ai)', function () {
    // Reproduce the real-host condition: the laravel/ai SDK's config/ai.php
    // provider blocks win the shallow mergeConfigFrom into 'ai', so
    // ai.providers.*.models is absent. The package's full provider config —
    // including models.text.default — lives under the 'evolayer-ai' namespace.
    config()->set('ai.providers.gemini.models', null);
    config()->set('ai.providers.openai.models', null);

    $config = new ThreadStudioAiConfig;

    // Must resolve the package's configured model, NOT the 'provider default'
    // sentinel — which, sent to the provider as a model name, breaks ThreadStudio
    // UI compose for the default provider (the bug the first-hour rehearsal caught).
    expect($config->defaultModel('gemini'))
        ->toBe(config('evolayer-ai.providers.gemini.models.text.default'))
        ->not->toBe('provider default')
        ->and($config->defaultModel('openai'))
        ->toBe(config('evolayer-ai.providers.openai.models.text.default'))
        ->not->toBe('provider default');

    // selectableModelNames is derived from defaultModel, so it must not surface
    // the sentinel either.
    expect($config->selectableModelNames('gemini'))->not->toContain('provider default');
});
