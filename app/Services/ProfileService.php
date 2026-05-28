<?php

namespace App\Services;

use App\Contracts\Repositories\ProfileRepositoryInterface;
use App\Contracts\Services\ProfileServiceInterface;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileService implements ProfileServiceInterface
{
    public function __construct(private readonly ProfileRepositoryInterface $profileRepository)
    {
    }

    public function getProfile(User $user): ?array
    {
        $profile = $this->profileRepository->findByUser($user);

        return $profile?->toArray();
    }

    public function createProfile(User $user, array $data): array
    {
        return $this->profileRepository->saveForUser($user, $this->cleanProfileData($data))->toArray();
    }

    public function updateProfile(User $user, array $data): array
    {
        return $this->profileRepository->saveForUser($user, $this->cleanProfileData($data))->toArray();
    }

    public function updateSkills(User $user, array $skills): array
    {
        return $this->profileRepository->saveForUser($user, ['skills' => array_values($skills)])->toArray();
    }

    public function updatePhoto(User $user, ?UploadedFile $photo, ?string $photoUrl): array
    {
        if ($photo !== null) {
            $path = $photo->store('profile-photos', 'public');
            $photoUrl = Storage::disk('public')->url($path);
        }

        return $this->profileRepository->saveForUser($user, ['photo_url' => $photoUrl])->toArray();
    }

    public function updateDescription(User $user, string $description): array
    {
        return $this->profileRepository->saveForUser($user, ['description' => $description])->toArray();
    }

    public function updateSocialLinks(User $user, array $socialLinks): array
    {
        return $this->profileRepository->saveForUser($user, ['social_links' => $socialLinks])->toArray();
    }

    private function cleanProfileData(array $data): array
    {
        return collect($data)
            ->only((new ProfessionalProfile())->getFillable())
            ->filter(fn ($value) => $value !== null)
            ->all();
    }
}
