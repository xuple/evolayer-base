<?php

namespace Xuple\EvoLayer\Base\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'honeypot' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(['contact', 'enquiry', 'complaint'])],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
        ];

        if (config('evolayer.base.features.contact_attachments')) {
            $rules['attachments'] = ['nullable', 'array', 'max:5'];
            $rules['attachments.*'] = ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt,csv,mp3,wav,m4a,ogg'];
        }

        return $rules;
    }
}
