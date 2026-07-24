<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
            ],

            'email' => [
                'required',
                'email',
                // 'unique:users,email',
            ],

            'password' => [
                'required',
                'min:8',
                'alpha_num',
                'regex:/[a-z]/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your :attribute.',
            'name.string' => 'The :attribute must be a string.',
            'email.required' => 'Please enter your :attribute.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This :attribute is already taken.',
            'password.required' => 'Please enter your :attribute.',
            'password.min' => 'The :attribute must be at least 8 characters.',
            'password.alpha_num' => 'The :attribute must contain both letters and numbers.',
            'password.regex' => 'The :attribute must contain at least one Lowercase letter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
        ];
    }
}
