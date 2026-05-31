<?php

use Laravel\Ai\Prompts\AgentPrompt;
use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;
use Xuple\EvoLayer\Base\Models\AiInvocation;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;

$fakeResult = [
    'summary' => 'Customer cannot download invoices after their plan changed.',
    'urgency' => 'medium',
    'sentiment' => 'frustrated',
    'recommended_tone' => 'calm and accountable',
    'customer_reply' => 'Thanks for flagging this. We are looking into the invoice access issue now.',
    'internal_note' => 'Investigate invoice download permissions after billing plan upgrade.',
];

$validPayload = [
    'customer_message' => 'I upgraded this morning and now I cannot download any of my invoices.',
    'tone' => 'warm',
];

// ---- Access control ----

test('guests cannot compose thread studio stream replies', function () use ($validPayload) {
    $this->postJson('/ai/thread-studio/stream', $validPayload)
        ->assertUnauthorized();
});

test('unverified users cannot compose thread studio stream replies', function () use ($validPayload) {
    $user = TestUser::factory()->unverified()->create();

    $this->actingAs($user)->postJson('/ai/thread-studio/stream', $validPayload)
        ->assertForbidden();
});

// ---- SSE headers ----

test('the stream endpoint returns SSE headers', function () use ($fakeResult, $validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->assertStreamed()
        ->assertHeaderContains('Content-Type', 'text/event-stream')
        ->assertHeaderContains('Cache-Control', 'no-cache')
        ->assertHeader('X-Accel-Buffering', 'no');
});

// ---- Success path ----

test('the stream emits a start event then a done event with the full result', function () use ($fakeResult, $validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    expect($content)
        ->toContain("event: start\ndata: {}")
        ->toContain('event: done');

    preg_match('/event: done\ndata: ({.+})/s', $content, $matches);
    $doneData = json_decode($matches[1], true);

    expect($doneData)
        ->toHaveKey('result')
        ->and($doneData['result'])->toMatchArray([
            'urgency' => 'medium',
            'sentiment' => 'frustrated',
            'recommended_tone' => 'calm and accountable',
            'customer_reply' => 'Thanks for flagging this. We are looking into the invoice access issue now.',
            'internal_note' => 'Investigate invoice download permissions after billing plan upgrade.',
        ]);
});

test('the stream done event carries the invocation id and duration', function () use ($fakeResult, $validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    preg_match('/event: done\ndata: ({.+})/s', $content, $matches);
    $doneData = json_decode($matches[1], true);

    $invocation = AiInvocation::first();

    expect($doneData)
        ->toHaveKey('invocation_id')
        ->toHaveKey('duration_ms')
        ->and($doneData['invocation_id'])->toBe($invocation->id)
        ->and($doneData['duration_ms'])->toBeInt();
});

test('the stream endpoint prompts the agent with the customer message and tone', function () use ($fakeResult) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $this->actingAs($user)->post('/ai/thread-studio/stream', [
        'customer_message' => 'Hot reload is broken when using the nginx lane.',
        'tone' => 'firm',
    ])->assertSuccessful()->streamedContent();

    ThreadStudioAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->contains('Hot reload is broken when using the nginx lane.')
            && $prompt->contains('firm');
    });
});

test('the stream endpoint uses the selected provider', function () use ($fakeResult) {
    $user = makeAdmin();

    // Default is gemini; select the other curated provider (openai) to prove
    // the explicitly-selected provider/model is the one used.
    config()->set('ai.thread_studio.provider', 'gemini');
    config()->set('ai.providers.openai.models.text.default', 'gpt-4o-mini');

    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $this->actingAs($user)->post('/ai/thread-studio/stream', [
        'customer_message' => 'A customer asks whether OpenAI is used when selected in ThreadStudio.',
        'model' => 'gpt-4o-mini',
        'provider' => 'openai',
        'tone' => 'balanced',
    ])->assertSuccessful()->streamedContent();

    ThreadStudioAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        return $prompt->provider->name() === 'openai'
            && $prompt->model === 'gpt-4o-mini';
    });
});

test('the stream endpoint rejects a non-curated provider (anthropic blocked/pending)', function () {
    $user = makeAdmin();

    // Anthropic is diagnostic-known but not curated for ThreadStudio (ADR-020)
    // — its structured streaming emits no usable TextDelta events. It must not
    // be selectable in the curated runtime.
    $this->actingAs($user)->postJson('/ai/thread-studio/stream', [
        'customer_message' => 'A customer asks how to install the starter kit locally.',
        'provider' => 'anthropic',
        'tone' => 'balanced',
    ])->assertUnprocessable()->assertInvalid(['provider']);
});

// ---- Invocation recording ----

test('a successful stream compose creates an invocation record', function () use ($fakeResult, $validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    expect(AiInvocation::count())->toBe(1);

    $invocation = AiInvocation::first();

    expect($invocation->status)->toBe('succeeded')
        ->and($invocation->feature_key)->toBe('thread_studio')
        ->and($invocation->user_id)->toBe($user->id);
});

// ---- Error path ----

test('a provider failure emits an agent_exception error event on the stream', function () use ($validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake(fn () => throw new RuntimeException('Provider unavailable.'));

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    expect($content)
        ->toContain('event: start')
        ->toContain('event: error');

    preg_match('/event: error\ndata: ({.+})/s', $content, $matches);
    $errorData = json_decode($matches[1], true);

    expect($errorData)
        ->toMatchArray([
            'message' => 'The AI provider did not return a usable reply. Try again in a moment.',
            'failure_type' => 'agent_exception',
            'missing_fields' => [],
            'invalid_fields' => [],
        ])
        ->and($errorData['provider'])->toBeString()->not->toBeEmpty()
        ->and($errorData['model'])->toBeString()->not->toBeEmpty();
});

test('an incomplete provider response emits an incomplete_response error event with diagnostics', function () use ($validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([
        [
            'summary' => 'Customer needs help.',
            // urgency, sentiment, recommended_tone, customer_reply, internal_note all missing
        ],
    ])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    preg_match('/event: error\ndata: ({.+})/s', $content, $matches);
    $errorData = json_decode($matches[1], true);

    expect($errorData['failure_type'])->toBe('incomplete_response')
        ->and($errorData['missing_fields'])->toContain('urgency')
        ->and($errorData['missing_fields'])->toContain('sentiment')
        ->and($errorData['missing_fields'])->toContain('recommended_tone')
        ->and($errorData['missing_fields'])->toContain('customer_reply')
        ->and($errorData['missing_fields'])->toContain('internal_note')
        ->and($errorData['invalid_fields'])->toBe([]);
});

test('an out-of-enum field emits an incomplete_response error event listing the invalid field', function () use ($validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([
        [
            'summary' => 'Customer needs help.',
            'urgency' => 'critical', // not in enum
            'sentiment' => 'frustrated',
            'recommended_tone' => 'calm',
            'customer_reply' => 'Thanks for flagging this.',
            'internal_note' => 'Investigate.',
        ],
    ])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    preg_match('/event: error\ndata: ({.+})/s', $content, $matches);
    $errorData = json_decode($matches[1], true);

    expect($errorData['failure_type'])->toBe('incomplete_response')
        ->and($errorData['missing_fields'])->toBe([])
        ->and($errorData['invalid_fields'])->toHaveCount(1)
        ->and($errorData['invalid_fields'][0]['field'])->toBe('urgency');
});

test('a provider failure on the stream does not produce a done event', function () use ($validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake(fn () => throw new RuntimeException('Provider unavailable.'));

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    expect($content)->not->toContain('event: done');
});

// ---- Validation ----

test('the stream endpoint validates the customer message is required', function () {
    $user = makeAdmin();

    $this->actingAs($user)->postJson('/ai/thread-studio/stream', [
        'customer_message' => '',
        'tone' => 'balanced',
    ])->assertUnprocessable()->assertInvalid(['customer_message']);
});

test('the stream endpoint validates the customer message minimum length', function () {
    $user = makeAdmin();

    $this->actingAs($user)->postJson('/ai/thread-studio/stream', [
        'customer_message' => 'short',
        'tone' => 'balanced',
    ])->assertUnprocessable()->assertInvalid(['customer_message']);
});

test('the stream endpoint validates the tone value', function () {
    $user = makeAdmin();

    $this->actingAs($user)->postJson('/ai/thread-studio/stream', [
        'customer_message' => 'A customer says invoice downloads stopped working after their plan changed.',
        'tone' => 'aggressive',
    ])->assertUnprocessable()->assertInvalid(['tone']);
});

test('the stream endpoint validates the selected provider', function () {
    $user = makeAdmin();

    $this->actingAs($user)->postJson('/ai/thread-studio/stream', [
        'customer_message' => 'A customer asks how to install the starter kit locally.',
        'provider' => 'unsupported-ai',
        'tone' => 'balanced',
    ])->assertUnprocessable()->assertInvalid(['provider']);
});

test('the stream endpoint validates the selected model belongs to the selected provider', function () {
    $user = makeAdmin();

    config()->set('ai.providers.openai.models.text.default', 'gpt-4o-mini');

    // openai is curated, so the provider passes; a gemini model does not belong
    // to openai, so model validation fails.
    $this->actingAs($user)->postJson('/ai/thread-studio/stream', [
        'customer_message' => 'A customer asks how to install the starter kit locally.',
        'model' => 'gemini-3-flash-preview',
        'provider' => 'openai',
        'tone' => 'balanced',
    ])->assertUnprocessable()->assertInvalid(['model']);
});

// ---- Field-level streaming events ----

test('the stream emits field_delta and field_complete events for all six schema fields', function () use ($fakeResult, $validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    $expectedFields = ['summary', 'urgency', 'sentiment', 'recommended_tone', 'customer_reply', 'internal_note'];

    foreach ($expectedFields as $field) {
        expect($content)
            ->toContain("\"name\":\"{$field}\"");
    }

    expect($content)
        ->toContain('event: field_delta')
        ->toContain('event: field_complete');
});

test('accumulating field_delta events for each field reproduces the done result', function () use ($fakeResult, $validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    // Accumulate deltas per field
    $accumulated = [];
    preg_match_all('/event: field_delta\ndata: ({.+})/U', $content, $deltaMatches);
    foreach ($deltaMatches[1] as $raw) {
        $frame = json_decode($raw, true);
        $accumulated[$frame['name']] = ($accumulated[$frame['name']] ?? '').$frame['delta'];
    }

    // Extract the done result
    preg_match('/event: done\ndata: ({.+})/s', $content, $doneMatches);
    $doneResult = json_decode($doneMatches[1], true)['result'];

    // Each field's accumulated deltas must equal the final result value
    foreach ($doneResult as $field => $value) {
        expect($accumulated)->toHaveKey($field)
            ->and($accumulated[$field])->toBe($value);
    }
});

test('field_complete events are emitted after the last delta for each field and before done', function () use ($fakeResult, $validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake([$fakeResult])->preventStrayPrompts();

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    $donePos = strpos($content, 'event: done');
    $completedFields = [];

    preg_match_all('/event: field_complete\ndata: ({.+})/U', $content, $completeMatches, PREG_OFFSET_CAPTURE);
    foreach ($completeMatches[0] as [$match, $offset]) {
        expect($offset)->toBeLessThan($donePos);
        $frame = json_decode(substr($match, strlen("event: field_complete\ndata: ")), true);
        $completedFields[] = $frame['name'];
    }

    expect($completedFields)->toContain('summary')
        ->toContain('urgency')
        ->toContain('sentiment')
        ->toContain('recommended_tone')
        ->toContain('customer_reply')
        ->toContain('internal_note');
});

test('a provider failure emits no field_delta or field_complete events', function () use ($validPayload) {
    $user = makeAdmin();
    ThreadStudioAgent::fake(fn () => throw new RuntimeException('Provider unavailable.'));

    $content = $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $validPayload)
        ->assertSuccessful()
        ->streamedContent();

    expect($content)
        ->not->toContain('event: field_delta')
        ->not->toContain('event: field_complete');
});

// ---- Rate limiting ----

test('it rate limits stream compose requests', function () use ($fakeResult) {
    $user = makeAdmin();
    ThreadStudioAgent::fake(fn () => $fakeResult);

    $payload = [
        'customer_message' => 'I upgraded this morning and cannot download any of my invoices.',
        'tone' => 'balanced',
    ];

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->actingAs($user)
            ->post('/ai/thread-studio/stream', $payload)
            ->assertSuccessful()
            ->streamedContent();
    }

    $this->actingAs($user)
        ->post('/ai/thread-studio/stream', $payload)
        ->assertTooManyRequests();
});
