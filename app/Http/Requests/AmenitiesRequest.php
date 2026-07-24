<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AmenitiesRequest extends FormRequest
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
            'search_id' => 'required|string',
            'result_id' => 'required|string',
            'amenity_key' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'search_id.required' => 'The search ID is required.',
            'search_id.string' => 'The search ID must be a valid text string.',
            'result_id.required' => 'The search ID is required.',
            'result_id.string' => 'The search ID must be a valid text string.',
        ];
    }
}
