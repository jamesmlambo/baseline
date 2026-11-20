<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingEnquiryRequest extends FormRequest
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
            'accommodation_type_id' => ['required', 'integer', Rule::exists('accommodation_types', 'id')->where('is_active', true)],
            'arrival' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'departure' => ['required', 'date_format:Y-m-d', 'after:arrival'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:12'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'message' => ['nullable', 'string', 'max:2000'],
            'accept_terms' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'Please confirm you have read the booking and cancellation policies.',
            'email.required_without' => 'Please give an email address or a phone number.',
            'phone.required_without' => 'Please give a phone number or an email address.',
            'phone.regex' => 'Please enter a valid phone number.',
        ];
    }
}
