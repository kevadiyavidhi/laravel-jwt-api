<?php

namespace App\Http\Requests\Hotel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetHotelContentRequest extends FormRequest
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
            'hotel_ids' => ['nullable', 'array'],
            'hotel_ids.*' => ['string'],
            'circular_region' => ['nullable', 'array'],
            'polygonal_region' => ['nullable', 'array'],
            'multi_polygonal_region' => ['nullable', 'array'],
            'content_fields' => ['nullable', 'array'],
            'content_fields.*' => ['string', 'in:basic,facilities,nearByAttractions,images,neighbourhoods,masterfacilities,rooms,descriptions,packages'],
            'filter_by' => ['nullable', 'array'],
            'filter_by.facilities' => ['nullable', 'array'],
            'filter_by.ratings.min' => ['nullable', 'integer', 'min:1', 'max:5'],
            'distance_from' => ['nullable', 'array'],
            'distance_from.lat' => ['required_with:distance_from', 'numeric'],
            'distance_from.long' => ['required_with:distance_from', 'numeric'],
        ];
    }
}
