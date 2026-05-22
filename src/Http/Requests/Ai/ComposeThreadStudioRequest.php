<?php

namespace EvoDevOps\Base\Http\Requests\Ai;

use EvoDevOps\Base\Support\ThreadStudioAiConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComposeThreadStudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ThreadStudioAiConfig $aiConfig): array
    {
        $providerInput = $this->string('provider')->toString() ?: null;
        $provider = in_array($providerInput, $aiConfig->supportedProviders(), true)
            ? $providerInput
            : null;

        return [
            'customer_message' => ['required', 'string', 'min:10', 'max:10000'],
            'tone' => ['required', 'string', Rule::in(['balanced', 'warm', 'firm'])],
            'provider' => ['nullable', 'string', Rule::in($aiConfig->supportedProviders())],
            'model' => ['nullable', 'string', Rule::in($aiConfig->selectableModelNames($provider))],
        ];
    }
}
