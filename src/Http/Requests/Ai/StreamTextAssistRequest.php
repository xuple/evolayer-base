<?php

namespace EvoDevOps\Base\Http\Requests\Ai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StreamTextAssistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'field_hint' => ['required', 'string', 'min:5', 'max:500'],
            'context' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
