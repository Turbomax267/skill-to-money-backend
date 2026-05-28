<?php

namespace App\Contracts\Repositories;

use App\Models\ApiToken;
use App\Models\User;

interface AuthRepositoryInterface
{
    public function createUser(array $data): User;

    public function findUserByEmail(string $email): ?User;

    public function createToken(User $user, string $name = 'default'): array;

    public function findValidToken(string $plainToken): ?ApiToken;

    public function markTokenAsUsed(ApiToken $token): void;

    public function revokeToken(User $user, ?string $plainToken): void;
}
