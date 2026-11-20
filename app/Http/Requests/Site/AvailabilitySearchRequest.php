<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use App\Support\StayDates;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilitySearchRequest extends FormRequest
{
    public const MAX_NIGHTS = 30;

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
            'arrival' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+2 years', 'required_with:departure'],
            'departure' => ['nullable', 'date_format:Y-m-d', 'after:arrival', 'required_with:arrival'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'arrival.after_or_equal' => 'Arrival can’t be in the past.',
            'departure.after' => 'Departure must be after arrival.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $stay = $this->stayOrNull();

                if ($stay !== null && $stay->nights() > self::MAX_NIGHTS) {
                    $validator->errors()->add('departure', 'For stays longer than '.self::MAX_NIGHTS.' nights, please contact us.');
                }
            },
        ];
    }

    public function stay(): ?StayDates
    {
        return $this->stayOrNull();
    }

    public function adults(): int
    {
        return (int) ($this->validated('adults') ?? 2);
    }

    public function children(): int
    {
        return (int) ($this->validated('children') ?? 0);
    }

    private function stayOrNull(): ?StayDates
    {
        $arrival = $this->input('arrival');
        $departure = $this->input('departure');

        if (! is_string($arrival) || ! is_string($departure) || $departure <= $arrival) {
            return null;
        }

        return new StayDates($arrival, $departure);
    }
}
