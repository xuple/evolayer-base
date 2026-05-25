<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Xuple\EvoLayer\Base\Models\FormSubmission;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;

beforeEach(fn () => Queue::fake());

$validPayload = [
    'type' => 'enquiry',
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'email' => 'jane@example.com',
    'phone' => '+44 20 0000 0000',
    'subject' => 'Question about the product',
    'message' => 'I would like to find out more about your offering.',
];

test('contact page is publicly accessible', function () {
    $this->get('/contact')->assertSuccessful();
});

test('thank you page is publicly accessible', function () {
    $this->get('/contact/thank-you')->assertSuccessful();
});

test('valid submission redirects to thank you page', function () use ($validPayload) {
    $this->post('/contact', $validPayload)
        ->assertRedirect('/contact/thank-you');

    expect(FormSubmission::count())->toBe(1);
});

test('submission is stored with correct field values', function () use ($validPayload) {
    $this->post('/contact', $validPayload);

    $submission = FormSubmission::first();

    expect($submission->type)->toBe('enquiry')
        ->and($submission->first_name)->toBe('Jane')
        ->and($submission->last_name)->toBe('Smith')
        ->and($submission->email)->toBe('jane@example.com')
        ->and($submission->phone)->toBe('+44 20 0000 0000')
        ->and($submission->subject)->toBe('Question about the product')
        ->and($submission->status)->toBe('new');
});

test('submission links to authenticated user', function () use ($validPayload) {
    $user = TestUser::factory()->create();

    $this->actingAs($user)->post('/contact', $validPayload);

    expect(FormSubmission::first()->user_id)->toBe($user->id);
});

test('submission user_id is null for guests', function () use ($validPayload) {
    $this->post('/contact', $validPayload);

    expect(FormSubmission::first()->user_id)->toBeNull();
});

test('phone is optional', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['phone' => null]);

    $this->post('/contact', $payload)->assertRedirect('/contact/thank-you');

    expect(FormSubmission::first()->phone)->toBeNull();
});

test('honeypot filled silently discards submission', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['honeypot' => 'bot-was-here']);

    $this->post('/contact', $payload)
        ->assertRedirect('/contact/thank-you');

    expect(FormSubmission::count())->toBe(0);
});

test('invalid type is rejected', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['type' => 'spam']);

    $this->post('/contact', $payload)->assertSessionHasErrors('type');
});

test('missing required fields returns validation errors', function () {
    $this->post('/contact', [])
        ->assertSessionHasErrors(['type', 'first_name', 'last_name', 'email', 'subject', 'message']);
});

test('message shorter than 10 characters is rejected', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['message' => 'Short']);

    $this->post('/contact', $payload)->assertSessionHasErrors('message');
});

test('invalid email is rejected', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['email' => 'not-an-email']);

    $this->post('/contact', $payload)->assertSessionHasErrors('email');
});

test('file attachment is stored against the submission when contact_attachments is enabled', function () use ($validPayload) {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('document.pdf', 512, 'application/pdf');

    $this->post('/contact', array_merge($validPayload, [
        'attachments' => [$file],
    ]))->assertRedirect('/contact/thank-you');

    $submission = FormSubmission::first();

    expect($submission->getMedia('attachments'))->toHaveCount(1)
        ->and($submission->getMedia('attachments')->first()->file_name)->toBe('document.pdf');
});

test('multiple attachments can be submitted', function () use ($validPayload) {
    Storage::fake('public');

    $files = [
        UploadedFile::fake()->create('file1.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('file2.png', 200, 'image/png'),
    ];

    $this->post('/contact', array_merge($validPayload, [
        'attachments' => $files,
    ]))->assertRedirect('/contact/thank-you');

    expect(FormSubmission::first()->getMedia('attachments'))->toHaveCount(2);
});

test('attachments are not required', function () use ($validPayload) {
    $this->post('/contact', $validPayload)->assertRedirect('/contact/thank-you');

    expect(FormSubmission::first()->getMedia('attachments'))->toHaveCount(0);
});

test('file exceeding 10 MB is rejected', function () use ($validPayload) {
    Storage::fake('public');

    $oversized = UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf');

    $this->post('/contact', array_merge($validPayload, [
        'attachments' => [$oversized],
    ]))->assertSessionHasErrors('attachments.0');
});

test('no attachments are stored when contact_attachments feature is disabled', function () use ($validPayload) {
    Storage::fake('public');
    config()->set('evolayer.base.features.contact_attachments', false);

    $file = UploadedFile::fake()->create('document.pdf', 512, 'application/pdf');

    $this->post('/contact', array_merge($validPayload, [
        'attachments' => [$file],
    ]))->assertRedirect('/contact/thank-you');

    expect(FormSubmission::first()->getMedia('attachments'))->toHaveCount(0);
});
