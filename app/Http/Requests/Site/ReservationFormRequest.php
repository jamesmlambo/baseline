<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReservationFormRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', Rule::exists('extras', 'id')->where('is_active', true)->where('is_bookable_online', true)],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::onlineOrder()))],
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
            'phone.regex' => 'Please enter a valid phone number.',
            'payment_method.required' => 'Please choose how you would like to pay.',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function extraQuantities(): array
    {
        return array_fill_keys(array_map('intval', $this->validated('extras') ?? []), 0);
    }
}
