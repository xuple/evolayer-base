<?php

use Xuple\EvoLayer\Base\Http\Requests\Admin\GeneratePrdRequest;
use Xuple\EvoLayer\Base\Http\Requests\Admin\SearchInboxRequest;
use Xuple\EvoLayer\Base\Http\Requests\Ai\ComposeThreadStudioRequest;
use Xuple\EvoLayer\Base\Http\Requests\Ai\StreamTextAssistRequest;
use Xuple\EvoLayer\Base\Http\Requests\Ai\TranscribeAudioRequest;
use Xuple\EvoLayer\Base\Models\FormSubmission;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;

/*
| Regression coverage for the admin authorization contract (ADR-004 / ADR-009).
| Two issues motivated these tests:
|   1. The admin inbox/submission routes lacked the `evolayer.admin` middleware, so
|      any authenticated, verified user could read and mutate private
|      submissions.
|   2. Several FormRequests authorized admin actions directly (hardcoded
|      hasRole or unconditional `return true`), bypassing the pluggable
|      AdminGate contract.
*/

test('admin inbox and submission routes deny a non-admin authenticated user', function (string $method, string $uri) {
    // No makeAdmin() here: the default SpatieAdminGate sees a user model
    // without hasRole() and denies, so evolayer.admin aborts 403.
    $user = TestUser::factory()->create();
    $submission = FormSubmission::factory()->create();

    $this->actingAs($user)
        ->call($method, str_replace('{id}', (string) $submission->id, $uri))
        ->assertForbidden();
})->with([
    'inbox' => ['get', '/admin/inbox'],
    'inbox detail' => ['get', '/admin/inbox/{id}'],
    'inbox search' => ['get', '/admin/inbox/search?q=test'],
    'submissions index' => ['get', '/admin/submissions'],
    'submission show' => ['get', '/admin/submissions/{id}'],
    'mark read' => ['patch', '/admin/submissions/{id}/mark-read'],
    'archive' => ['patch', '/admin/submissions/{id}/archive'],
]);

test('an admin user passes the gate on admin inbox routes', function () {
    $admin = makeAdmin();

    // search returns JSON, so this asserts the admin gets past evolayer.admin AND
    // the request-level authorize() without depending on Inertia rendering.
    $this->actingAs($admin)
        ->getJson('/admin/inbox/search?q=test')
        ->assertSuccessful();
});

test('admin form requests authorize through AdminGate, never a hardcoded role check', function (string $requestClass) {
    // makeAdmin() binds an AdminGate fake that recognises exactly this user.
    // TestUser has no hasRole() method, so if any request still called it the
    // assertions below would surface a BadMethodCallException instead of false.
    $admin = makeAdmin();
    $stranger = TestUser::factory()->create();

    $request = $requestClass::create('/', 'POST');

    $request->setUserResolver(fn () => $admin);
    expect($request->authorize())->toBeTrue();

    $request->setUserResolver(fn () => $stranger);
    expect($request->authorize())->toBeFalse();

    $request->setUserResolver(fn () => null);
    expect($request->authorize())->toBeFalse();
})->with([
    GeneratePrdRequest::class,
    TranscribeAudioRequest::class,
    SearchInboxRequest::class,
    ComposeThreadStudioRequest::class,
    StreamTextAssistRequest::class,
]);
