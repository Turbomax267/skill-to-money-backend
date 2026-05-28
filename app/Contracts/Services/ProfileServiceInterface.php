<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;

interface ProfileServiceInterface
{
    public function getProfile(User $user): ?array;

    public function createProfile(User $user, array $data): array;

    public function updateProfile(User $user, array $data): array;

    public function updateSkills(User $user, array $skills): array;

    public function updatePhoto(User $user, ?UploadedFile $photo, ?string $photoUrl): array;

    public function updateDescription(User $user, string $description): array;

    public function updateSocialLinks(User $user, array $socialLinks): array;
}
