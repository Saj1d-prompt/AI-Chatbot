<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'A conversation title is required.',
            'title.string' => 'The conversation title must be valid text.',
            'title.max' => 'The conversation title may not exceed 120 characters.',
        ];
    }
}