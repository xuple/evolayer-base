<?php

namespace Xuple\EvoLayer\Base\Http\Requests\Ai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Xuple\EvoLayer\Base\Contracts\AdminGate;

class StreamTextAssistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Route already enforces evolayer.admin; delegate here too so request-level
        // authorization stays consistent with the AdminGate contract (ADR-004).
        return app(AdminGate::class)->isAdmin($this->user());
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
