<?php

namespace Xuple\EvoLayer\Base\Http\Requests\Ai;

use Closure;
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
     * ADR-019/ADR-020 — the policy is the consumer-facing seam. The provider
     * rule uses `explain()` so a rejected provider gets a per-provider reason
     * (e.g. Anthropic is blocked because structured streaming emits no usable
     * TextDeltas) instead of the framework's generic "selected provider is
     * invalid".
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ThreadStudioAiConfig $aiConfig, ThreadStudioProviderPolicy $policy): array
    {
        $runtimeApproved = $policy->runtimeApprovedProviders();
        $providerInput = $this->string('provider')->toString() ?: null;
        $provider = in_array($providerInput, $runtimeApproved, true)
            ? $providerInput
            : null;

        return [
            'customer_message' => ['required', 'string', 'min:10', 'max:10000'],
            'tone' => ['required', 'string', Rule::in(['balanced', 'warm', 'firm'])],
            'provider' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) use ($policy): void {
                if ($value === null || $value === '') {
                    return;
                }

                $availability = $policy->explain((string) $value);

                if (! $availability->allowed) {
                    $fail($availability->message);
                }
            }],
            'model' => ['nullable', 'string', Rule::in($aiConfig->selectableModelNames($provider))],
        ];
    }
}
