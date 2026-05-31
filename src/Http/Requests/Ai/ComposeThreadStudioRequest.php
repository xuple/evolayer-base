<?php

namespace Xuple\EvoLayer\Base\Http\Requests\Ai;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Xuple\EvoLayer\Base\Contracts\AdminGate;
use Xuple\EvoLayer\Base\Support\ThreadStudioAiConfig;
use Xuple\EvoLayer\Base\Support\ThreadStudioProviderPolicy;

class ComposeThreadStudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route already enforces evolayer.admin; delegate here too so request-level
        // authorization stays consistent with the AdminGate contract (ADR-004).
        return app(AdminGate::class)->isAdmin($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Provider eligibility flows through `ThreadStudioProviderPolicy` per
     * ADR-019 — the policy is the consumer-facing seam, not the underlying
     * curated list. Today the policy delegates to
     * `ThreadStudioAiConfig::supportedProviders()`; the seam exists so
     * future policy changes (capability-ledger gating, per-provider
     * rejection messages) land in one place.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ThreadStudioAiConfig $aiConfig, ThreadStudioProviderPolicy $policy): array
    {
        $curated = $policy->curatedProviders();
        $providerInput = $this->string('provider')->toString() ?: null;
        $provider = in_array($providerInput, $curated, true)
            ? $providerInput
            : null;

        return [
            'customer_message' => ['required', 'string', 'min:10', 'max:10000'],
            'tone' => ['required', 'string', Rule::in(['balanced', 'warm', 'firm'])],
            'provider' => ['nullable', 'string', Rule::in($curated)],
            'model' => ['nullable', 'string', Rule::in($aiConfig->selectableModelNames($provider))],
        ];
    }
}
