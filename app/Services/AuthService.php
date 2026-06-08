<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AuthRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const USER_TYPES = ['admin', 'freelancer', 'mype'];

    public function __construct(
        private readonly AuthRepository $authRepository,
        private readonly PeruApiService $peruApiService,
        private readonly WebhookMailService $webhookMailService,
    ) {
    }

    public function register(array $data, ?string $forcedUserType = null): array
    {
        $userType = $forcedUserType ?? ($data['user_type'] ?? 'freelancer');

        if (! in_array($userType, self::USER_TYPES, true)) {
            $userType = 'freelancer';
        }

        $profileData = $data;

        if ($userType === 'mype') {
            $mypeProfileData = $this->peruApiService->validateRuc($data['ruc']);

            if (! $mypeProfileData['valid']) {
                throw ValidationException::withMessages([
                    'ruc' => [$mypeProfileData['message']],
                ]);
            }

            $profileData = array_merge($profileData, [
                'business_name' => $mypeProfileData['business_name'],
                'ruc' => $mypeProfileData['ruc'],
                'location' => $mypeProfileData['location'] ?? null,
            ]);
        }

        if ($userType === 'freelancer') {
            $profileData = array_merge($profileData, [
                'dni' => $data['dni'],
                'experience_area' => $data['experience_area'] ?? 'No especificada',
            ]);
        }

        $user = DB::transaction(function () use ($profileData, $userType): User {
            $user = $this->authRepository->createUser([
                'name' => $this->resolveName($profileData, $userType),
                'email' => strtolower($profileData['email']),
                'password' => $profileData['password'],
                'user_type' => $userType,
            ]);

            if ($userType === 'freelancer') {
                $this->authRepository->createFreelancerProfile($user, $profileData);
            }

            if ($userType === 'mype') {
                $this->authRepository->createMypeProfile($user, $profileData);
            }

            return $user;
        });

        $this->webhookMailService->sendWelcomeMail($user, $this->frontendUrl());

        return $this->authenticatedPayload($user);
    }

    public function login(array $credentials): ?array
    {
        $user = $this->authRepository->findUserByEmail($credentials['email']);

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        return $this->authenticatedPayload($user);
    }

    public function sendPasswordResetLink(string $email): bool
    {
        if ($this->webhookMailService->shouldUseWebhookMailer()) {
            $user = $this->authRepository->findUserByEmail($email);

            if ($user === null) {
                return false;
            }

            $token = Password::broker()->createToken($user);
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            $url = $frontendUrl.'/reset-password?token='.urlencode($token).'&email='.urlencode($user->email);

            $this->webhookMailService->sendPasswordResetMail($user, $url);

            return true;
        }

        $status = Password::sendResetLink(['email' => $email]);

        return $status === Password::RESET_LINK_SENT;
    }

    public function resetPassword(array $data): bool
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET;
    }

    public function logout(User $user, ?string $plainToken): void
    {
        $this->authRepository->revokeToken($user, $plainToken);
    }

    private function authenticatedPayload(User $user): array
    {
        $token = $this->authRepository->createToken($user, 'auth');
        $freshUser = $user->fresh(['freelancerProfile', 'mypeProfile']);

        $payload = [
            'token_type' => 'Bearer',
            'access_token' => $token['token'],
            'expires_at' => $token['expires_at'],
            'user' => [
                'id' => $freshUser->id,
                'name' => $freshUser->name,
                'first_name' => null,
                'last_name' => null,
                'company_name' => $freshUser->mypeProfile?->business_name,
                'email' => $freshUser->email,
                'user_type' => $freshUser->user_type,
                'account_type' => $freshUser->user_type,
            ],
        ];

        if ($freshUser->freelancerProfile !== null) {
            $payload['freelancer_profile'] = [
                'id' => $freshUser->freelancerProfile->id,
                'dni' => $freshUser->freelancerProfile->dni,
                'experience_area' => $freshUser->freelancerProfile->experience_area,
            ];
        }

        if ($freshUser->mypeProfile !== null) {
            $payload['mype_profile'] = [
                'id' => $freshUser->mypeProfile->id,
                'business_name' => $freshUser->mypeProfile->business_name,
                'ruc' => $freshUser->mypeProfile->ruc,
                'location' => $freshUser->mypeProfile->location,
            ];
        }

        return $payload;
    }

    private function resolveName(array $data, string $userType): string
    {
        if ($userType === 'mype' && ! empty($data['business_name'])) {
            return trim($data['business_name']);
        }

        if (! empty($data['name'])) {
            return trim($data['name']);
        }

        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return strtolower($data['email']);
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/');
    }
}
