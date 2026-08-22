<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;

class ListMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'before_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:' . config(
                    'chat.max_message_page_size',
                    50
                ),
            ],
        ];
    }
}