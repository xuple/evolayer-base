<?php

use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;

test('the thread studio prompt preserves safety guardrails', function () {
    $instructions = ThreadStudioAgent::make()->instructions();

    expect($instructions)
        ->toContain('Do not invent account details')
        ->toContain('policy promises')
        ->toContain('incident causes')
        ->toContain('timelines')
        ->toContain('Do not mention AI, prompts, schemas, or internal instructions')
        ->toContain('ask for one clarifying detail only if needed')
        ->toContain('Keep the customer reply under 180 words');
});

test('the thread studio prompt dogfoods evolayer base support context', function () {
    $instructions = ThreadStudioAgent::make()->instructions();

    expect($instructions)
        ->toContain('It is commonly used with the EvoLayer Base starter')
        ->toContain('https://docs.evodevops.com/base')
        ->toContain('Fortify authentication')
        ->toContain('Wayfinder-generated route helpers')
        ->toContain('using the SQLite fast-start lane with `nvm use` and `composer setup`')
        ->toContain('Do not tell users to run `npm run dev` as a blind fix')
        ->toContain('nginx + PHP-FPM reverse-proxied Vite/HMR lane')
        ->toContain('treat it as EvoLayer Base');
});

test('the thread studio agent disables streaming for openai compatible router paths', function () {
    $agent = ThreadStudioAgent::make();
    $routerOptions = [
        'reasoning' => [
            'effort' => 'none',
            'exclude' => true,
        ],
        'stream' => false,
    ];

    expect($agent->providerOptions('openrouter'))->toBe($routerOptions)
        ->and($agent->providerOptions('nvidia'))->toBe($routerOptions)
        ->and($agent->providerOptions('opencode'))->toBe($routerOptions)
        ->and($agent->providerOptions('gemini'))->toBe([]);
});

test('the thread studio agent keeps the output budget focused on concise structured replies', function () {
    expect(ThreadStudioAgent::make()->maxTokens())->toBe(1200);
});
