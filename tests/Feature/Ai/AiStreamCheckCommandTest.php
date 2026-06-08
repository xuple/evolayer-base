<?php

use Xuple\EvoLayer\Base\Ai\Agents\ThreadStudioAgent;

$threadStudioResult = [
    'summary' => 'Customer cannot download invoices after their plan changed.',
    'urgency' => 'medium',
    'sentiment' => 'frustrated',
    'recommended_tone' => 'calm and accountable',
    'customer_reply' => 'Thanks for flagging this. We are looking into the invoice access issue now.',
    'internal_note' => 'Investigate invoice download permissions after billing plan upgrade.',
];

test('the stream-check command verifies a faked structured stream end to end', function () use ($threadStudioResult) {
    ThreadStudioAgent::fake([$threadStudioResult])->preventStrayPrompts();

    $this->artisan('evolayer:ai:stream-check', ['provider' => 'anthropic'])
        ->expectsOutputToContain('Starting live stream via anthropic')
        ->expectsOutputToContain('TextDelta events')
        ->expectsOutputToContain('Fields completed: summary, urgency, sentiment, recommended_tone, customer_reply, internal_note')
        ->expectsOutputToContain('Final payload keys: summary, urgency, sentiment, recommended_tone, customer_reply, internal_note')
        ->expectsOutputToContain('✓ Structured streaming verified end-to-end.')
        ->assertSuccessful();
});

test('the stream-check command fails when a provider returns an empty final payload', function () {
    ThreadStudioAgent::fake([''])->preventStrayPrompts();

    $this->artisan('evolayer:ai:stream-check', ['provider' => 'anthropic'])
        ->expectsOutputToContain('Starting live stream via anthropic')
        ->expectsOutputToContain('Could not decode final payload (length=0)')
        ->assertFailed();
});

test('the stream-check command rejects unknown providers before prompting', function () {
    ThreadStudioAgent::fake(fn () => throw new RuntimeException('The agent should not be called.'));

    $this->artisan('evolayer:ai:stream-check', ['provider' => 'not-a-provider'])
        ->expectsOutput("Unknown provider 'not-a-provider'.")
        ->assertFailed();
});
