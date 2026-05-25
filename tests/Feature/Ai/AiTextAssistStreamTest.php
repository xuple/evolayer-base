<?php

use Laravel\Ai\Prompts\AgentPrompt;
use Xuple\EvoLayer\Base\Ai\Agents\TextAssistAgent;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;

$validPayload = [
    'field_hint' => 'Product context for a PRD document',
    'context' => 'Audience: developers',
];

// ---- Access control ----

test('guests cannot stream text assist suggestions', function () use ($validPayload) {
    $this->postJson('/ai/text-assist/stream', $validPayload)
        ->assertUnauthorized();
});

test('non-admin users cannot stream text assist suggestions', function () use ($validPayload) {
    $user = TestUser::factory()->create();

    $this->actingAs($user)->postJson('/ai/text-assist/stream', $validPayload)
        ->assertForbidden();
});

// ---- SSE headers ----

test('the text assist stream returns SSE headers', function () use ($validPayload) {
    $user = makeAdmin();
    TextAssistAgent::fake(['A concise product description.'])->preventStrayPrompts();

    $this->actingAs($user)
        ->post('/ai/text-assist/stream', $validPayload)
        ->assertSuccessful()
        ->assertStreamed()
        ->assertHeaderContains('Content-Type', 'text/event-stream')
        ->assertHeaderContains('Cache-Control', 'no-cache')
        ->assertHeader('X-Accel-Buffering', 'no');
});

// ---- Success path ----

test('the text assist stream emits start then text_delta then done events', function () use ($validPayload) {
    $user = makeAdmin();
    TextAssistAgent::fake(['A concise product description for your PRD.'])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/text-assist/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    expect($content)
        ->toContain("event: start\ndata: {}")
        ->toContain('event: text_delta')
        ->toContain('event: done');
});

test('accumulating text_delta events reproduces the done text', function () use ($validPayload) {
    $user = makeAdmin();
    TextAssistAgent::fake(['A concise product description for your PRD.'])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/text-assist/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    $accumulated = '';
    preg_match_all('/event: text_delta\ndata: ({.+})/U', $content, $matches);
    foreach ($matches[1] as $raw) {
        $frame = json_decode($raw, true);
        $accumulated .= $frame['delta'];
    }

    preg_match('/event: done\ndata: ({.+})/s', $content, $doneMatches);
    $doneText = json_decode($doneMatches[1], true)['text'];

    expect($accumulated)->toBe($doneText);
});

test('the field_hint and context are forwarded to the agent', function () use ($validPayload) {
    $user = makeAdmin();
    TextAssistAgent::fake(['Generated text.'])->preventStrayPrompts();

    $this->actingAs($user)
        ->post('/ai/text-assist/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    TextAssistAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('Product context for a PRD document')
            && $prompt->contains('Audience: developers');
    });
});

test('context is optional and the endpoint still succeeds without it', function () {
    $user = makeAdmin();
    TextAssistAgent::fake(['Standalone suggestion.'])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/text-assist/stream', [
            'field_hint' => 'Short bio for a user profile',
        ])
        ->assertSuccessful()
        ->streamedContent();

    expect($content)->toContain('event: done');

    TextAssistAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('Short bio for a user profile');
    });
});

// ---- Error path ----

test('a provider failure emits an error event', function () use ($validPayload) {
    $user = makeAdmin();
    TextAssistAgent::fake(fn () => throw new RuntimeException('Provider down.'));

    $content = $this->actingAs($user)
        ->post('/ai/text-assist/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    expect($content)
        ->toContain('event: start')
        ->toContain('event: error')
        ->not->toContain('event: done');

    preg_match('/event: error\ndata: ({.+})/s', $content, $matches);
    $errorData = json_decode($matches[1], true);

    expect($errorData['message'])->toBeString()->not->toBeEmpty();
});

// ---- Validation ----

test('field_hint is required', function () {
    $user = makeAdmin();

    $this->actingAs($user)->postJson('/ai/text-assist/stream', [
        'field_hint' => '',
    ])->assertUnprocessable()->assertInvalid(['field_hint']);
});

test('field_hint must be at least 5 characters', function () {
    $user = makeAdmin();

    $this->actingAs($user)->postJson('/ai/text-assist/stream', [
        'field_hint' => 'bio',
    ])->assertUnprocessable()->assertInvalid(['field_hint']);
});

// ---- Rate limiting ----

test('the stream endpoint rate limits requests', function () use ($validPayload) {
    $user = makeAdmin();
    TextAssistAgent::fake(fn () => 'Response.');

    for ($i = 1; $i <= 20; $i++) {
        $this->actingAs($user)
            ->post('/ai/text-assist/stream', $validPayload)
            ->assertSuccessful()
            ->streamedContent();
    }

    $this->actingAs($user)
        ->post('/ai/text-assist/stream', $validPayload)
        ->assertTooManyRequests();
});
