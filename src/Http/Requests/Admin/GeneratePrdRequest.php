<?php

namespace EvoDevOps\Base\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePrdRequest extends FormRequest
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
            'product_context' => ['required', 'string', 'min:20', 'max:5000'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'constraints' => ['nullable', 'string', 'max:1000'],
            'tone' => ['nullable', 'string', Rule::in(['concise', 'detailed', 'technical'])],
        ];
    }
}
