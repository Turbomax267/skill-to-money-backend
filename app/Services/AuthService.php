<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AuthRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthService
{
    private const USER_TYPES = ['admin', 'freelancer', 'mype'];

    public function __construct(private readonly AuthRepository $authRepository)
    {
    }

    public function register(array $data, ?string $forcedUserType = null): array
    {
        $userType = $forcedUserType ?? ($data['user_type'] ?? 'freelancer');

        if (! in_array($userType, self::USER_TYPES, true)) {
            $userType = 'freelancer';
        }

        $user = $this->authRepository->createUser([
            'name' => $this->resolveName($data),
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'user_type' => $userType,
        ]);

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
        $freshUser = $user->fresh();

        return [
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
