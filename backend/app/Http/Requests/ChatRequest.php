<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'max:10000',
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'A message is required.',
            'message.string' => 'The message must be valid text.',
            'message.max' => 'The message may not exceed 10,000 characters.',
        ];
    }
}