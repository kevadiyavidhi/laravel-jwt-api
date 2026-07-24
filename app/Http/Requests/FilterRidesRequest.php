<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterRidesRequest extends FormRequest
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
            'filters' => 'nullable|array',
            'page' => 'required|integer|min:1',
            'perPage' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'search_id.required' => 'The search ID is required.',
            'search_id.string' => 'The search ID must be a valid text string.',
            'filters.array' => 'The filters must be submitted as an array.',
            'page.integer' => 'The page number must be a whole number.',
            'page.min' => 'The page number must be at least 1.',
            'perPage.integer' => 'The items per page must be a whole number.',
            'perPage.min' => 'The items per page must be at least 1.',
        ];
    }
}
