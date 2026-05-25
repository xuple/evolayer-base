<?php

namespace Xuple\EvoLayer\Base\Http\Requests\Ai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Xuple\EvoLayer\Base\Contracts\AdminGate;

class TranscribeAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Delegate to the pluggable AdminGate contract rather than hardcoding
        // a Spatie role check (the route already enforces evolayer.admin; this keeps
        // request-level authorization consistent for custom gate bindings).
        return app(AdminGate::class)->isAdmin($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'max:10240',
                'extensions:webm,ogg,wav,mp3,mp4,m4a,flac',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.required' => 'Please attach a recorded audio clip.',
            'audio.mimetypes' => 'Audio must be webm, ogg, wav, mp3, mp4, m4a, or flac.',
            'audio.max' => 'Audio recordings are limited to 10 MB.',
        ];
    }
}
