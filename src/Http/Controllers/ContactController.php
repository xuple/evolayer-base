<?php

namespace Xuple\EvoLayer\Base\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Xuple\EvoLayer\Base\Ai\Agents\SubjectHintsAgent;
use Xuple\EvoLayer\Base\Http\Requests\ContactFormRequest;
use Xuple\EvoLayer\Base\Jobs\ProcessMediaAttachmentsJob;
use Xuple\EvoLayer\Base\Jobs\TriageFormSubmissionJob;
use Xuple\EvoLayer\Base\Models\FormSubmission;
use Xuple\EvoLayer\Base\Support\ChangeEventRecorder;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('evolayer/contact');
    }

    public function store(ContactFormRequest $request, ChangeEventRecorder $events): RedirectResponse
    {
        if ($request->filled('honeypot')) {
            return redirect()->route('evolayer.base.contact.thank-you');
        }

        $submission = FormSubmission::create([
            'user_id' => $request->user()?->id,
            'type' => $request->string('type')->toString(),
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->string('phone')->toString() ?: null,
            'subject' => $request->string('subject')->toString(),
            'message' => $request->string('message')->toString(),
        ]);

        $events->record(
            eventName: 'form_submission.created',
            subject: $submission,
            actor: $request->user(),
            after: [
                'status' => $submission->status,
                'type' => $submission->type,
            ],
            properties: [
                'type' => $submission->type,
                'has_user' => $submission->user_id !== null,
            ],
        );

        if (config('evolayer.base.features.contact_attachments') && $request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $submission->addMedia($file)->toMediaCollection('attachments');
            }
        }

        if (config('evolayer.base.examples.contact_ai')) {
            TriageFormSubmissionJob::dispatch($submission);

            if ($submission->getMedia('attachments')->isNotEmpty()) {
                ProcessMediaAttachmentsJob::dispatch($submission);
            }
        }

        return redirect()->route('evolayer.base.contact.thank-you');
    }

    public function thankYou(): Response
    {
        return Inertia::render('evolayer/contact-thank-you');
    }

    public function subjectHints(Request $request): JsonResponse
    {
        $type = $request->string('type')->toString();

        if (! in_array($type, ['contact', 'enquiry', 'complaint'], true)) {
            return response()->json(['placeholder' => null]);
        }

        $placeholder = Cache::remember("subject-hints.{$type}", now()->addHour(), function () use ($type): ?string {
            try {
                $response = (new SubjectHintsAgent)->prompt(
                    "Generate 3 subject suggestions for a {$type} form submission.",
                );

                return trim((string) $response);
            } catch (\Throwable) {
                return null;
            }
        });

        return response()->json(['placeholder' => $placeholder]);
    }
}
