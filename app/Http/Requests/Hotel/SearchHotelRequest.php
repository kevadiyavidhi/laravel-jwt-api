<?php

namespace App\Http\Requests\Hotel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchHotelRequest extends FormRequest
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
            'currency' => ['required', 'string', 'size:3'],
            // 'check_in' => ['required', 'date_format:Y-m-d', 'after:today'],
            'check_in' => ['required', 'date_format:Y-m-d\TH:i:s\Z', 'after:today'],

            // 'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'check_out' => ['required', 'date_format:Y-m-d\TH:i:s\Z', 'after:check_in'],

            'occupancies' => ['required', 'array', 'min:1'],
            'occupancies.*.numOfAdults' => ['required', 'integer', 'min:1'],
            'occupancies.*.childAges' => ['nullable', 'array'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'country_of_residence' => ['nullable', 'string', 'size:2'],
        ];
    }
}
