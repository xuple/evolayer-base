<?php

namespace Xuple\EvoLayer\Base\Http\Requests\Ai;

use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComposeThreadStudioRequest extends FormRequest
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
