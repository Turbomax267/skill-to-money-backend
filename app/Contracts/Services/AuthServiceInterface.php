<?php

namespace App\Contracts\Services;

use App\Models\User;

interface AuthServiceInterface
{
    public function registerFreelancer(array $data): array;

    public function registerMype(array $data): array;

    public function login(array $credentials): ?array;

    public function sendPasswordResetLink(string $email): void;

    public function logout(User $user, ?string $plainToken): void;
}
