<?php

namespace App\Http\Requests\Hotel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HotelFilterSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer'],
            'perPage' => ['nullable', 'integer'],
            'filters' => ['nullable', 'array'],
            'filters.min_price' => ['nullable', 'numeric'],
            'filters.max_price' => ['nullable', 'numeric'],
            'filters.max_distance' => ['nullable', 'numeric'],
            'filters.refundable' => ['nullable', 'boolean'],
            'filters.free_cancellation' => ['nullable', 'boolean'],
            'filters.free_breakfast' => ['nullable', 'boolean'],
            'filters.pay_at_hotel' => ['nullable', 'boolean'],
            'filters.star_ratings' => ['nullable', 'array'],
            'filters.star_ratings.*' => ['integer', 'min:1', 'max:5'],
            'filters.hotel_types' => ['nullable', 'array'],
            'filters.facilities' => ['nullable', 'array'],
            'filters.suppliers' => ['nullable', 'array'],
        ];
    }
}
