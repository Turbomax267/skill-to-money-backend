<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class ForgotPasswordRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'exists:users,email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => 'No encontramos una cuenta con ese correo.',
            'email.email' => 'Ingresa un correo valido.',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Coloque un correo valido.',
        ];
    }
}
