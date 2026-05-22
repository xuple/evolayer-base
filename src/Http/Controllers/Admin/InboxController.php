<?php

namespace EvoDevOps\Base\Http\Controllers\Admin;

use EvoDevOps\Base\Contracts\AdminGate;
use EvoDevOps\Base\Http\Controllers\Controller;
use EvoDevOps\Base\Http\Requests\Admin\SearchInboxRequest;
use EvoDevOps\Base\Models\FormSubmission;
use EvoDevOps\Base\Support\FormSubmissionSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @evo-example admin_inbox
 */
class InboxController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('evodevops/admin/inbox/index', [
            'submissions' => FormSubmission::with('tags')->latest()->paginate(20),
            'selected' => null,
            'attachments' => [],
            'activity' => [],
            'canCompose' => app(AdminGate::class)->isAdmin($request->user())
                && (bool) config('evo.examples.thread_studio'),
        ]);
    }

    public function search(SearchInboxRequest $request, FormSubmissionSearch $search): JsonResponse
    {
        return response()->json($search->search(
            query: $request->string('q')->toString(),
            limit: $request->integer('limit', 8),
        ));
    }

    public function detail(Request $request, FormSubmission $submission): Response
    {
        $submission->load('user', 'tags', 'media');

        return Inertia::render('evodevops/admin/inbox/index', [
            'submissions' => FormSubmission::with('tags')->latest()->paginate(20),
            'selected' => $submission,
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
                'created_at' => $log->created_at->toISOString(),
            ]),
            'canCompose' => app(AdminGate::class)->isAdmin($request->user())
                && (bool) config('evo.examples.thread_studio'),
        ]);
    }
}
