<?php

namespace App\Repositories;

use App\Models\ApiToken;
use App\Models\FreelancerProfile;
use App\Models\MypeProfile;
use App\Models\User;
use Illuminate\Support\Str;

class AuthRepository
{
    public function createUser(array $data): User
    {
        return User::query()->create($data);
    }

    public function createFreelancerProfile(User $user, array $data): FreelancerProfile
    {
        return $user->freelancerProfile()->create([
            'dni' => $data['dni'],
            'experience_area' => $data['experience_area'] ?? 'Por definir',
            'contact_phone' => $data['phone'] ?? null,
            'availability_status' => 'available',
            'visibility_score' => 0,
        ]);
    }

    public function createMypeProfile(User $user, array $data): MypeProfile
    {
        return $user->mypeProfile()->create([
            'business_name' => $data['business_name'] ?? $data['company_name'],
            'ruc' => $data['ruc'],
        ]);
    }

    public function findUserByEmail(string $email): ?User
    {
        return User::query()->where('email', strtolower($email))->first();
    }

    public function createToken(User $user, string $name = 'auth'): array
    {
        $plainToken = Str::random(80);

        $apiToken = $user->apiTokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays((int) config('auth.api_token_ttl_days', 30)),
        ]);

        return [
            'token' => $plainToken,
            'expires_at' => $apiToken->expires_at,
        ];
    }

    public function findValidToken(string $plainToken): ?ApiToken
    {
        return ApiToken::query()
            ->with('user')
            ->where('token', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function markTokenAsUsed(ApiToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }

    public function revokeToken(User $user, ?string $plainToken): void
    {
        if ($plainToken === null) {
            return;
        }

        $user->apiTokens()
            ->where('token', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
