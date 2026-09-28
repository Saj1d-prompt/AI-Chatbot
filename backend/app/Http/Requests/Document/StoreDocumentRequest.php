<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',

                File::types([
                    'txt',
                ])->max('2mb'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' =>
                'Please select a document to upload.',
        ];
    }
}