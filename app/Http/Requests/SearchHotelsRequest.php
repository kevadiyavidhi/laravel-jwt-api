<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchHotelsRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'geoLat' => ['required', 'numeric'],
            'geoLong' => ['required', 'numeric'],
            'locationId' => ['nullable', 'string'],
            'locationType' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
            'radius' => ['nullable', 'numeric'],
            'checkinDate' => ['required', 'string'],
            'checkoutDate' => ['required', 'string'],
            'desiredResultCurrency' => ['nullable', 'string'],
            'residency' => ['nullable', 'string'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.adults' => ['required', 'integer', 'min:1'],
            'rooms.*.childs' => ['nullable', 'array'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
