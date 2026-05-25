<?php

namespace Xuple\EvoLayer\Base\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Xuple\EvoLayer\Base\Http\Controllers\Controller;
use Xuple\EvoLayer\Base\Models\FormSubmission;
use Xuple\EvoLayer\Base\Support\ChangeEventRecorder;

class SubmissionsController extends Controller
{
    public function index(): Response
    {
        $submissions = FormSubmission::query()
            ->with('tags')
            ->latest()
            ->paginate(25);

        return Inertia::render('evodevops/admin/submissions/index', [
            'submissions' => $submissions,
        ]);
    }

    public function show(FormSubmission $submission): Response
    {
        $submission->load('user', 'tags', 'media');

        return Inertia::render('evodevops/admin/submissions/show', [
            'submission' => $submission,
            'attachments' => $submission->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->human_readable_size,
                'url' => $media->getUrl(),
                'ai_analysis' => $media->getCustomProperty('ai_analysis'),
            ]),
            'activity' => $submission->activitiesAsSubject()->latest()->get()->map(fn ($log) => [
                'id' => $log->id,
                'description' => $log->description,
                'causer' => $log->causer?->name,
                'properties' => $log->properties->toArray(),
                'created_at' => $log->created_at->toISOString(),
            ]),
        ]);
    }

    public function markRead(FormSubmission $submission, Request $request, ChangeEventRecorder $events): RedirectResponse
    {
        $before = ['status' => $submission->status];

        $submission->update(['status' => 'read']);

        activity()
            ->performedOn($submission)
            ->causedBy($request->user())
            ->log('Marked as read');

        $events->record(
            eventName: 'form_submission.marked_read',
            subject: $submission,
            actor: $request->user(),
            before: $before,
            after: ['status' => $submission->status],
        );

        return back();
    }

    public function archive(FormSubmission $submission, Request $request, ChangeEventRecorder $events): RedirectResponse
    {
        $before = ['status' => $submission->status];

        $submission->update(['status' => 'archived']);

        activity()
            ->performedOn($submission)
            ->causedBy($request->user())
            ->log('Archived');

        $events->record(
            eventName: 'form_submission.archived',
            subject: $submission,
            actor: $request->user(),
            before: $before,
            after: ['status' => $submission->status],
        );

        return back();
    }
}
