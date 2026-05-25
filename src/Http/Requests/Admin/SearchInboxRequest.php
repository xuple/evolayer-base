<?php

namespace Xuple\EvoLayer\Base\Http\Requests\Admin;

use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already enforces evo.admin; delegate here too so request-level
        // authorization stays consistent with the AdminGate contract (ADR-004).
        return app(AdminGate::class)->isAdmin($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
