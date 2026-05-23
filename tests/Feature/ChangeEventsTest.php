<?php

use EvoDevOps\Base\Jobs\TriageFormSubmissionJob;
use EvoDevOps\Base\Models\ChangeEvent;
use EvoDevOps\Base\Models\FormSubmission;
use EvoDevOps\Base\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Queue;

$validContactPayload = [
    'type' => 'enquiry',
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'email' => 'jane@example.com',
    'phone' => '+44 20 0000 0000',
    'subject' => 'Question about billing',
    'message' => 'I would like to understand how billing works for my account.',
];

test('contact submissions record a created lineage event', function () use ($validContactPayload) {
    Queue::fake();

    $this->post('/contact', $validContactPayload)
        ->assertRedirect('/contact/thank-you');

    Queue::assertPushed(TriageFormSubmissionJob::class);

    $submission = FormSubmission::first();
    $event = ChangeEvent::where('event_name', 'form_submission.created')->first();

    expect($event)->not->toBeNull()
        ->and($event->subject->is($submission))->toBeTrue()
        ->and($event->actor_user_id)->toBeNull()
        ->and($event->after)->toBe([
            'status' => 'new',
            'type' => 'enquiry',
        ])
        ->and($event->properties['has_user'])->toBeFalse();
});

test('authenticated contact submissions record the acting user', function () use ($validContactPayload) {
    Queue::fake();

    $user = TestUser::factory()->create();

    $this->actingAs($user)
        ->post('/contact', $validContactPayload)
        ->assertRedirect('/contact/thank-you');

    $event = ChangeEvent::where('event_name', 'form_submission.created')->first();

    expect($event->actor_user_id)->toBe($user->id)
        ->and($event->properties['has_user'])->toBeTrue();
});

test('mark read records before and after lineage snapshots', function () {
    $user = TestUser::factory()->create();
    $submission = FormSubmission::factory()->create(['status' => 'new']);

    $this->actingAs($user)
        ->patch("/admin/submissions/{$submission->id}/mark-read")
        ->assertRedirect();

    $event = ChangeEvent::where('event_name', 'form_submission.marked_read')->first();

    expect($event)->not->toBeNull()
        ->and($event->subject->is($submission))->toBeTrue()
        ->and($event->actor_user_id)->toBe($user->id)
        ->and($event->before)->toBe(['status' => 'new'])
        ->and($event->after)->toBe(['status' => 'read']);
});

test('archive records before and after lineage snapshots', function () {
    $user = TestUser::factory()->create();
    $submission = FormSubmission::factory()->create(['status' => 'read']);

    $this->actingAs($user)
        ->patch("/admin/submissions/{$submission->id}/archive")
        ->assertRedirect();

    $event = ChangeEvent::where('event_name', 'form_submission.archived')->first();

    expect($event)->not->toBeNull()
        ->and($event->subject->is($submission))->toBeTrue()
        ->and($event->actor_user_id)->toBe($user->id)
        ->and($event->before)->toBe(['status' => 'read'])
        ->and($event->after)->toBe(['status' => 'archived']);
});
