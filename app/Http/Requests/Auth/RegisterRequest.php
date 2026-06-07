<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Closure;
use Illuminate\Validation\Rule;

class RegisterRequest extends ApiRequest
{
    private const LETTERS_AND_SPACES = '/^[\pL]+(?:\s[\pL]+)*$/u';

    private const FREELANCER_EMAIL_DOMAINS = [
        'gmail.com',
        'outlook.com',
        'hotmail.com',
        'yahoo.com',
        'icloud.com',
        'live.com',
    ];

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'dni' => trim((string) $this->input('dni')),
            'ruc' => trim((string) $this->input('ruc')),
            'business_name' => trim((string) ($this->input('business_name') ?? $this->input('company_name'))),
        ]);
    }

    public function rules(): array
    {
        $isFreelancer = $this->isFreelancerRegistration();
        $isMype = $this->isMypeRegistration();

        $rules = [
            'name' => [
                'nullable',
                'string',
                'max:150',
                'required_without_all:first_name,company_name,business_name',
                'regex:' . self::LETTERS_AND_SPACES,
            ],
            'first_name' => [
                $isFreelancer ? 'required' : 'nullable',
                'string',
                'max:80',
                'regex:' . self::LETTERS_AND_SPACES,
            ],
            'last_name' => [
                $isFreelancer ? 'required' : 'nullable',
                'string',
                'max:80',
                'regex:' . self::LETTERS_AND_SPACES,
            ],
            'company_name' => [$isMype ? 'required' : 'nullable', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'user_type' => ['nullable', 'string', Rule::in(['admin', 'freelancer', 'mype'])],
            'dni' => [$isFreelancer ? 'required' : 'nullable', 'digits:8', 'unique:freelancer_profiles,dni'],
            'ruc' => [$isMype ? 'required' : 'nullable', 'digits:11', 'unique:mype_profiles,ruc'],
        ];

        if ($isFreelancer) {
            $rules['email'][] = $this->freelancerEmailDomainRule();
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede contener letras.',
            'first_name.regex' => 'El nombre solo puede contener letras.',
            'last_name.regex' => 'El apellido solo puede contener letras.',
            'company_name.required' => 'El nombre de la MYPE es obligatorio.',
            'dni.required' => 'El DNI es obligatorio para freelancers.',
            'dni.digits' => 'El DNI debe contener exactamente 8 numeros.',
            'dni.unique' => 'Este DNI ya esta registrado.',
            'ruc.required' => 'El RUC es obligatorio para MYPES.',
            'ruc.digits' => 'El RUC debe contener exactamente 11 numeros.',
            'ruc.unique' => 'Este RUC ya esta registrado.',
            'email.email' => 'Ingresa un correo valido.',
            'email.unique' => 'Este correo ya se encuentra registrado.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
        ];
    }

    private function freelancerEmailDomainRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $domain = strtolower((string) strrchr((string) $value, '@'));
            $domain = ltrim($domain, '@');

            if (! in_array($domain, self::FREELANCER_EMAIL_DOMAINS, true)) {
                $fail('Usa un correo personal valido, por ejemplo Gmail, Outlook, Hotmail, Yahoo o iCloud.');
            }
        };
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
