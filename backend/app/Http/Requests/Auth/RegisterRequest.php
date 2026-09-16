<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>
                'Your name is required.',

            'email.required' =>
                'Your email address is required.',

            'email.email' =>
                'Please provide a valid email address.',

            'email.unique' =>
                'An account already exists with this email address.',

            'password.required' =>
                'A password is required.',

            'password.confirmed' =>
                'The password confirmation does not match.',
        ];
    }
}