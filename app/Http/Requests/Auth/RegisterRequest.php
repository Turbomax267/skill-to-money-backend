<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Closure;
use Illuminate\Validation\Rule;

class RegisterRequest extends ApiRequest
{
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
        $isFreelancer = $this->is('api/auth/register/freelancer');
        $isMype = $this->is('api/auth/register/mype');

        $rules = [
            'name' => ['nullable', 'string', 'max:150', 'required_without_all:first_name,business_name'],
            'first_name' => [$isFreelancer ? 'required' : 'nullable', 'string', 'max:80'],
            'last_name' => [$isFreelancer ? 'required' : 'nullable', 'string', 'max:80'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'business_name' => [$isMype ? 'required' : 'nullable', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'user_type' => ['nullable', 'string', Rule::in(['admin', 'freelancer', 'mype'])],
            'dni' => [$isFreelancer ? 'required' : 'nullable', 'digits:8'],
            'ruc' => [$isMype ? 'required' : 'nullable', 'digits:11'],
        ];

        if ($isFreelancer) {
            $rules['email'][] = $this->freelancerEmailDomainRule();
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo ya se encuentra registrado.',
            'email.email' => 'Ingresa un correo valido.',
            'dni.required' => 'El DNI es obligatorio para freelancers.',
            'dni.digits' => 'El DNI debe tener 8 digitos.',
            'ruc.required' => 'El RUC es obligatorio para MYPES.',
            'ruc.digits' => 'El RUC debe tener 11 digitos.',
            'business_name.required' => 'El nombre de la MYPE es obligatorio.',
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
}
