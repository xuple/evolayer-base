<?php

namespace EvoDevOps\Base\Http\Requests\Ai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TranscribeAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
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
