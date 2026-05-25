<?php

namespace EvoDevOps\Base\Http\Requests\Ai;

use EvoDevOps\Base\Contracts\AdminGate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StreamTextAssistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Route already enforces evo.admin; delegate here too so request-level
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
