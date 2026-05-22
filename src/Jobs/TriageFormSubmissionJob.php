<?php

namespace EvoDevOps\Base\Jobs;

use EvoDevOps\Base\Ai\Agents\TriageAgent;
use EvoDevOps\Base\Models\FormSubmission;
use EvoDevOps\Base\Support\ChangeEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TriageFormSubmissionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public FormSubmission $submission) {}

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(ChangeEventRecorder $events): void
    {
        $prompt = "Subject: {$this->submission->subject}\n\nMessage: {$this->submission->message}";

        $response = (new TriageAgent)->prompt($prompt);

        $this->submission->update([
            'triage_urgency' => $response['urgency'],
            'triage_sentiment' => $response['sentiment'],
            'triage_summary' => $response['summary'],
        ]);

        $tags = array_slice((array) $response['tags'], 0, 3);
        $this->submission->syncTagsWithType($tags, 'ai');

        activity()
            ->performedOn($this->submission)
            ->withProperties([
                'urgency' => $response['urgency'],
                'sentiment' => $response['sentiment'],
                'tags' => $tags,
            ])
            ->log('AI triage completed');

        $events->record(
            eventName: 'ai_triage.completed',
            subject: $this->submission,
            after: [
                'triage_urgency' => $this->submission->triage_urgency,
                'triage_sentiment' => $this->submission->triage_sentiment,
                'triage_summary' => $this->submission->triage_summary,
                'tags' => $tags,
            ],
            properties: [
                'urgency' => $response['urgency'],
                'sentiment' => $response['sentiment'],
                'tags' => $tags,
            ],
        );

        try {
            $this->generateEmbedding();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function generateEmbedding(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $text = trim("{$this->submission->subject}\n\n{$this->submission->message}");

        if ($text === '') {
            return;
        }

        $embedding = Str::of($text)->toEmbeddings(cache: true);

        $this->submission->update(['embedding' => $embedding]);
    }
}
