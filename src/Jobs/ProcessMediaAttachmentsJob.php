<?php

namespace EvoDevOps\Base\Jobs;

use EvoDevOps\Base\Ai\Agents\MediaAnalysisAgent;
use EvoDevOps\Base\Models\FormSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Transcription;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class ProcessMediaAttachmentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public FormSubmission $submission) {}

    public function backoff(): array
    {
        return [30, 90];
    }

    public function handle(): void
    {
        $this->submission->load('media');

        foreach ($this->submission->getMedia('attachments') as $media) {
            try {
                $analysis = $this->analyse($media);

                if ($analysis !== null) {
                    $media->setCustomProperty('ai_analysis', $analysis);
                    $media->save();
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function analyse(Media $media): ?string
    {
        $mimeType = (string) $media->mime_type;

        if (str_starts_with($mimeType, 'image/')) {
            return $this->describeImage($media);
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return $this->transcribeAudio($media);
        }

        if ($this->isDocument($mimeType)) {
            return $this->describeDocument($media);
        }

        return null;
    }

    private function describeImage(Media $media): string
    {
        $response = (new MediaAnalysisAgent)->prompt(
            'Describe what is in this image.',
            attachments: [Image::fromPath($media->getPath())],
        );

        return trim((string) $response);
    }

    private function transcribeAudio(Media $media): ?string
    {
        // STT requires OpenAI, ElevenLabs, or Mistral — falls back gracefully if unavailable.
        $transcript = Transcription::fromPath($media->getPath())->generate();

        $text = trim((string) $transcript);

        return $text !== '' ? $text : null;
    }

    private function describeDocument(Media $media): string
    {
        $response = (new MediaAnalysisAgent)->prompt(
            'Summarise the content of this document.',
            attachments: [Document::fromPath($media->getPath())],
        );

        return trim((string) $response);
    }

    private function isDocument(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'text/csv',
        ], true);
    }
}
