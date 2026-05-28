<?php

namespace App\Services;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Services\AuthServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthService implements AuthServiceInterface
{
    public function __construct(private readonly AuthRepositoryInterface $authRepository)
    {
    }

    public function registerFreelancer(array $data): array
    {
        return $this->register($data, 'freelancer');
    }

    public function registerMype(array $data): array
    {
        return $this->register($data, 'mype');
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

    private function register(array $data, string $accountType): array
    {
        $user = $this->authRepository->createUser([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company_name' => $data['company_name'] ?? null,
            'account_type' => $accountType,
            'phone' => $data['phone'] ?? null,
            'email' => strtolower($data['email']),
            'password' => $data['password'],
        ]);

        return $this->authenticatedPayload($user);
    }

    private function authenticatedPayload(User $user): array
    {
        $token = $this->authRepository->createToken($user, 'auth');

        return [
            'token_type' => 'Bearer',
            'access_token' => $token['token'],
            'expires_at' => $token['expires_at'],
            'user' => $user->fresh(),
        ];
    }
}
