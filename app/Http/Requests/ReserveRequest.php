<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReserveRequest extends FormRequest
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
        $rules = [
            'search_id' => ['required', 'string'],
            'result_id' => ['required', 'string'],
            'mode' => ['required', 'string', 'in:one_way,round_trip'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.first_name' => ['required', 'string', 'max:100'],
            'passengers.*.last_name' => ['required', 'string', 'max:100'],
            'passengers.*.email' => ['required', 'email', 'unique:customers,email'],
            'passengers.*.phone_number' => ['required', 'string', 'max:20', 'unique:customers,phone_number'],
            'passengers.*.birth_date' => ['required', 'date_format:Y-m-d'],
            'passengers.*.country_code_name' => ['required', 'string', 'size:2'],
            'airline' => ['nullable', 'string', 'max:100'],
            'flight_number' => ['nullable', 'string', 'max:20'],
        ];

        if ($this->input('mode') === 'round_trip') {
            $rules['return_airline'] = ['required', 'string', 'max:100'];
            $rules['return_flight_number'] = ['required', 'string', 'max:20'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'search_id.required' => 'The :attribute is required.',
            'search_id.string' => 'The :attribute must be a valid string.',
            'result_id.required' => 'The :attribute is required.',
            'result_id.string' => 'The :attribute must be a valid string.',
            'mode.required' => 'The :attribute is required.',
            'mode.in' => 'The :attribute must be either one_way or round_trip.',
            'passengers.required' => 'At least one passenger is required.',
            'passengers.array' => 'The :attribute must be a list.',
            'passengers.min' => 'At least one passenger is required.',
            'passengers.*.first_name.required' => 'Each passenger must have a :attribute.',
            'passengers.*.first_name.max' => 'The :attribute must not exceed 100 characters.',
            'passengers.*.last_name.required' => 'Each passenger must have a :attribute.',
            'passengers.*.last_name.max' => 'The :attribute must not exceed 100 characters.',
            'passengers.*.email.required' => 'Each passenger must have an :attribute.',
            'passengers.*.email.email' => 'Please enter a valid :attribute address for each passenger.',
            'passengers.*.email.unique' => 'The :attribute address is already registered as a customer.',
            'passengers.*.phone_number.required' => 'Each passenger must have a :attribute.',
            'passengers.*.phone_number.max' => 'The :attribute must not exceed 20 characters.',
            'passengers.*.phone_number.unique' => 'The :attribute number is already registered as a customer.',
            'passengers.*.birth_date.required' => 'Each passenger must have a :attribute.',
            'passengers.*.birth_date.date_format' => 'The :attribute must be in YYYY-MM-DD format.',
            'passengers.*.country_code_name.required' => 'Each passenger must have a :attribute.',
            'passengers.*.country_code_name.size' => 'The :attribute must be a 2-letter country code (e.g. US).',
            'airline.string' => 'The :attribute must be a valid string.',
            'flight_number.string' => 'The :attribute must be a valid string.',
            'return_airline.required' => 'The :attribute is required for round trips.',
            'return_airline.string' => 'The :attribute must be a valid string.',
            'return_flight_number.required' => 'The :attribute is required for round trips.',
            'return_flight_number.string' => 'The :attribute must be a valid string.',
        ];
    }

    public function attributes(): array
    {
        return [
            'search_id' => 'Search ID',
            'result_id' => 'Result ID',
            'mode' => 'Trip Mode',
            'passengers' => 'Passengers',
            'passengers.*.first_name' => 'First Name',
            'passengers.*.last_name' => 'Last Name',
            'passengers.*.email' => 'Email',
            'passengers.*.phone_number' => 'Phone Number',
            'passengers.*.birth_date' => 'Birth Date',
            'passengers.*.country_code_name' => 'Country Code',
            'airline' => 'Airline',
            'flight_number' => 'Flight Number',
            'return_airline' => 'Return Airline',
            'return_flight_number' => 'Return Flight Number',
        ];
    }
}
