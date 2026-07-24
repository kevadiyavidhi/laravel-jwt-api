<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchRideRequest extends FormRequest
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
    public function rules()
    {
        return [
            'pickup' => 'required|string',
            'dropoff' => 'required|string',
            'mode' => 'required|string|in:one_way,round_trip',
            'pickup_datetime' =>  'required|date_format:Y-m-d H:i:s',
            'num_passengers' => 'required|integer|min:1',
            'return_pickup_datetime' => 'required_if:mode,round_trip|nullable|date_format:Y-m-d H:i:s',
        ];
    }

    public function messages(): array
    {
        return [
            'pickup.required' => 'Pickup location is required.',
            'dropoff.required' => 'Dropoff location is required.',
            'date.required' => 'Pickup date is required.',
            'time.required' => 'Pickup time is required.',
            'num_passengers.required' => 'Number of passengers is required.',
            'num_passengers.min' => 'At least one passenger is required.',
        ];
    }
}
