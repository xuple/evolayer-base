<?php

use Xuple\EvoLayer\Base\Models\AiInvocation;
use Xuple\EvoLayer\Base\Models\AiInvocationAttempt;
use Xuple\EvoLayer\Base\Models\FormSubmission;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

test('taggable_id stores the ulid of a tagged form submission', function () {
    $submission = FormSubmission::factory()->create();

    $submission->attachTag('billing', 'ai');

    $row = DB::table('taggables')
        ->where('taggable_type', FormSubmission::class)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->taggable_id)->toBe($submission->id);
});

test('tags are retrievable via the morph relationship on a ulid submission', function () {
    $submission = FormSubmission::factory()->create();

    $submission->attachTag('urgent', 'ai');
    $submission->attachTag('billing', 'ai');

    expect($submission->fresh()->tagsWithType('ai'))->toHaveCount(2);
});

test('activity_log subject_id stores the ulid of an ai invocation', function () {
    $invocation = AiInvocation::create([
        'feature_key' => 'thread_studio',
        'status' => 'started',
        'request_projection' => ['customer_message' => 'test'],
        'started_at' => now(),
    ]);

    $activity = Activity::where('subject_type', AiInvocation::class)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($invocation->id);
});

test('activity_log subject_id stores the ulid of an ai invocation attempt', function () {
    $invocation = AiInvocation::create([
        'feature_key' => 'thread_studio',
        'status' => 'started',
        'request_projection' => ['customer_message' => 'test'],
        'started_at' => now(),
    ]);

    $attempt = AiInvocationAttempt::create([
        'ai_invocation_id' => $invocation->id,
        'attempt' => 1,
        'provider' => 'gemini',
        'model' => 'gemini-3-flash-preview',
        'status' => 'started',
        'started_at' => now(),
    ]);

    $activity = Activity::where('subject_type', AiInvocationAttempt::class)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($attempt->id);
});

test('activity_log causer_id stores the integer id of the acting user', function () {
    $user = TestUser::factory()->create();
    $this->actingAs($user);

    AiInvocation::create([
        'user_id' => $user->id,
        'feature_key' => 'thread_studio',
        'status' => 'started',
        'request_projection' => ['customer_message' => 'test'],
        'started_at' => now(),
    ]);

    $activity = Activity::where('causer_type', TestUser::class)->first();

    expect($activity)->not->toBeNull()
        ->and((int) $activity->causer_id)->toBe($user->id);
});

test('media model_id stores the ulid of the owning form submission', function () {
    Storage::fake('public');

    $submission = FormSubmission::factory()->create();
    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
    $submission->addMedia($file)->toMediaCollection('attachments');

    $row = DB::table('media')
        ->where('model_type', FormSubmission::class)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->model_id)->toBe($submission->id);
});

test('change_events subject_id stores and resolves the ulid of a form submission', function () {
    Queue::fake();

    $this->post('/contact', [
        'type' => 'enquiry',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@example.com',
        'subject' => 'Schema correctness check',
        'message' => 'Verifying that subject_id stores a ULID correctly.',
    ]);

    $submission = FormSubmission::first();

    $row = DB::table('change_events')
        ->where('event_name', 'form_submission.created')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->subject_type)->toBe(FormSubmission::class)
        ->and($row->subject_id)->toBe($submission->id);
});
