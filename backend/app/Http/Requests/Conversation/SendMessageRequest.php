<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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

    public function messages(): array
    {
        return [
            'message.required' => 'A message is required.',
            'message.string' => 'The message must be valid text.',
            'message.max' => 'The message may not exceed 10,000 characters.',
        ];
    }
}