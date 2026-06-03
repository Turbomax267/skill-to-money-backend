<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class ForgotPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Coloque un correo valido.',
        ];
    }
}
