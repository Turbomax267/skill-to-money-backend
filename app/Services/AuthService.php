<?php

namespace App\Services;

use App\Mail\WelcomeAccountMail;
use App\Models\User;
use App\Repositories\AuthRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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

        $user = DB::transaction(function () use ($data, $userType): User {
            $user = $this->authRepository->createUser([
                'name' => $this->resolveName($data, $userType),
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'user_type' => $userType,
            ]);

            if ($userType === 'freelancer') {
                $this->authRepository->createFreelancerProfile($user, $data);
            }

            if ($userType === 'mype') {
                $this->authRepository->createMypeProfile($user, $data);
            }

            return $user;
        });

        Mail::to($user->email)->send(new WelcomeAccountMail($user, $this->frontendUrl()));

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

        return [
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
    }

    private function resolveName(array $data, string $userType): string
    {
        if ($userType === 'mype' && ! empty($data['business_name'])) {
            return trim($data['business_name']);
        }

        if (! empty($data['name'])) {
            return trim($data['name']);
        }

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

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
