<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AuthRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const USER_TYPES = ['admin', 'freelancer', 'mype'];

    public function __construct(
        private readonly AuthRepository $authRepository,
        private readonly PeruApiService $peruApiService,
    )
    {
    }

    public function register(array $data, ?string $forcedUserType = null): array
    {
        $userType = $forcedUserType ?? ($data['user_type'] ?? 'freelancer');

        if (! in_array($userType, self::USER_TYPES, true)) {
            $userType = 'freelancer';
        }

        $mypeProfileData = null;
        $freelancerProfileData = null;

        if ($userType === 'mype') {
            $mypeProfileData = $this->peruApiService->validateRuc($data['ruc']);

            if (! $mypeProfileData['valid']) {
                throw ValidationException::withMessages([
                    'ruc' => [$mypeProfileData['message']],
                ]);
            }
        } elseif ($userType === 'freelancer') {
            $freelancerProfileData = [
                'dni' => $data['dni'],
                'experience_area' => $data['experience_area'] ?? 'No especificada',
            ];
        }

        $user = DB::transaction(function () use ($data, $userType, $mypeProfileData, $freelancerProfileData): User {
            $user = $this->authRepository->createUser([
                'name' => $this->resolveName($data),
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'user_type' => $userType,
            ]);

            if ($mypeProfileData !== null) {
                $user->mypeProfile()->create([
                    'business_name' => $mypeProfileData['business_name'],
                    'ruc' => $mypeProfileData['ruc'],
                    'location' => $mypeProfileData['location'],
                ]);
            }

            if ($freelancerProfileData !== null) {
                $user->freelancerProfile()->create($freelancerProfileData);
            }

            return $user;
        });

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

    public function sendPasswordResetLink(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
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

    private function resolveName(array $data): string
    {
        if (! empty($data['name'])) {
            return trim($data['name']);
        }

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return strtolower($data['email']);
    }
}
