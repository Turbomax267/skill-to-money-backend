<?php

namespace App\Http\Requests\Messaging;

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
            'message' => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'El mensaje no puede estar vacio.',
        ];
    }
}
