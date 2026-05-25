<?php

use Xuple\EvoLayer\Base\Models\FormSubmission;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;

test('form submission can be created with factory defaults', function () {
    $submission = FormSubmission::factory()->create();

    expect($submission->id)->toBeString()
        ->and($submission->status)->toBe('new')
        ->and($submission->type)->toBeIn(['contact', 'enquiry', 'complaint'])
        ->and($submission->user_id)->toBeNull();
});

test('form submission factory states set correct type', function () {
    expect(FormSubmission::factory()->contact()->create()->type)->toBe('contact');
    expect(FormSubmission::factory()->enquiry()->create()->type)->toBe('enquiry');
    expect(FormSubmission::factory()->complaint()->create()->type)->toBe('complaint');
});

test('form submission factory states set correct status', function () {
    expect(FormSubmission::factory()->read()->create()->status)->toBe('read');
    expect(FormSubmission::factory()->archived()->create()->status)->toBe('archived');
});

test('form submission can be linked to a user via the configured auth user model', function () {
    $user = TestUser::factory()->create();
    $submission = FormSubmission::factory()->create(['user_id' => $user->id]);

    expect($submission->user->id)->toBe($user->id);
});

test('form submission user_id is nulled when the user is deleted', function () {
    $user = TestUser::factory()->create();
    $submission = FormSubmission::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect($submission->fresh()->user_id)->toBeNull();
});

test('form submission is soft deleted', function () {
    $submission = FormSubmission::factory()->create();
    $submission->delete();

    expect(FormSubmission::find($submission->id))->toBeNull()
        ->and(FormSubmission::withTrashed()->find($submission->id))->not->toBeNull();
});

test('form submission phone is optional', function () {
    $submission = FormSubmission::factory()->create(['phone' => null]);

    expect($submission->phone)->toBeNull();
});
