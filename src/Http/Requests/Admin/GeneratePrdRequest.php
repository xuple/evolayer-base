<?php

namespace Xuple\EvoLayer\Base\Http\Requests\Admin;

use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePrdRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Delegate to the pluggable AdminGate contract rather than hardcoding
        // a Spatie role check (the route already enforces evo.admin; this keeps
        // request-level authorization consistent for custom gate bindings).
        return app(AdminGate::class)->isAdmin($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_context' => ['required', 'string', 'min:20', 'max:5000'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'constraints' => ['nullable', 'string', 'max:1000'],
            'tone' => ['nullable', 'string', Rule::in(['concise', 'detailed', 'technical'])],
        ];
    }
}
