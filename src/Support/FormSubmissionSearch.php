<?php

namespace EvoDevOps\Base\Support;

use EvoDevOps\Base\Models\FormSubmission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class FormSubmissionSearch
{
    /**
     * @return array{strategy: string, results: list<array<string, mixed>>}
     */
    public function search(string $query, int $limit = 8): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'strategy' => 'empty',
                'results' => [],
            ];
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                return [
                    'strategy' => 'vector',
                    'results' => $this->mapResults($this->vectorSearch($query, $limit)),
                ];
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'strategy' => 'like',
            'results' => $this->mapResults($this->likeSearch($query, $limit)),
        ];
    }

    /**
     * @return Collection<int, FormSubmission>
     */
    private function vectorSearch(string $query, int $limit): Collection
    {
        return FormSubmission::query()
            ->with('tags')
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo('embedding', $query, minSimilarity: 0.35)
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, FormSubmission>
     */
    private function likeSearch(string $query, int $limit): Collection
    {
        $like = '%'.$this->escapeLike($query).'%';

        return FormSubmission::query()
            ->with('tags')
            ->where(function ($builder) use ($like): void {
                $builder
                    ->where('subject', 'like', $like)
                    ->orWhere('message', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('type', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('triage_summary', 'like', $like);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param  Collection<int, FormSubmission>  $submissions
     * @return list<array<string, mixed>>
     */
    private function mapResults(Collection $submissions): array
    {
        return $submissions
            ->map(fn (FormSubmission $submission): array => [
                'id' => $submission->id,
                'title' => $submission->subject,
                'subtitle' => trim("{$submission->first_name} {$submission->last_name} - {$submission->email}"),
                'excerpt' => $submission->triage_summary ?: str($submission->message)->squish()->limit(160)->toString(),
                'status' => $submission->status,
                'urgency' => $submission->triage_urgency,
                'sentiment' => $submission->triage_sentiment,
                'created_at' => $submission->created_at?->toISOString(),
                'tags' => $submission->tags
                    ->map(fn ($tag): string => is_string($tag->name) ? $tag->name : ($tag->name['en'] ?? ''))
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
