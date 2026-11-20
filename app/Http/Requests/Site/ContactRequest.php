<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'message' => ['required', 'string', 'max:3000'],
            'marketing_consent' => ['nullable', 'boolean'],
            // Honeypot: real people never see or fill this field.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => 'Please give an email address or a phone number.',
            'phone.required_without' => 'Please give a phone number or an email address.',
            'phone.regex' => 'Please enter a valid phone number.',
        ];
    }
}
