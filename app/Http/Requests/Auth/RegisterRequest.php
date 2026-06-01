<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150', 'required_without:first_name'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'user_type' => ['nullable', 'string', Rule::in(['admin', 'freelancer', 'mype'])],
        ];
    }
}
