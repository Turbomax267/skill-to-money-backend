<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends ApiRequest
{
    private const LETTERS_AND_SPACES = '/^[\pL]+(?:\s[\pL]+)*$/u';

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150', 'required_without:first_name', 'regex:'.self::LETTERS_AND_SPACES],
            'first_name' => ['nullable', 'string', 'max:80', 'regex:'.self::LETTERS_AND_SPACES],
            'last_name' => ['nullable', 'string', 'max:80', 'regex:'.self::LETTERS_AND_SPACES],
            'company_name' => ['nullable', 'string', 'max:150'],
            'dni' => [$this->isFreelancerRegistration() ? 'required' : 'nullable', 'digits:8', 'unique:freelancer_profiles,dni'],
            'ruc' => [$this->isMypeRegistration() ? 'required' : 'nullable', 'digits:11', 'unique:mype_profiles,ruc'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'user_type' => ['nullable', 'string', Rule::in(['admin', 'freelancer', 'mype'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede contener letras.',
            'first_name.regex' => 'El nombre solo puede contener letras.',
            'last_name.regex' => 'El apellido solo puede contener letras.',
            'dni.required' => 'Ingrese el DNI del usuario.',
            'dni.digits' => 'El DNI debe contener exactamente 8 numeros.',
            'dni.unique' => 'Este DNI ya esta registrado.',
            'ruc.required' => 'Ingrese el RUC de la MYPE.',
            'ruc.digits' => 'El RUC debe contener exactamente 11 numeros.',
            'ruc.unique' => 'Este RUC ya esta registrado.',
            'email.email' => 'Coloque un correo valido.',
            'email.unique' => 'Este correo ya esta registrado.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
        ];
    }

    private function isMypeRegistration(): bool
    {
        return $this->is('api/auth/register/mype') || $this->input('user_type') === 'mype';
    }

    private function isFreelancerRegistration(): bool
    {
        return ! $this->isMypeRegistration();
    }
}
