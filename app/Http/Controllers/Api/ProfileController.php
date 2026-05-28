<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\ProfileServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreProfileRequest;
use App\Http\Requests\Profile\UpdateDescriptionRequest;
use App\Http\Requests\Profile\UpdateProfilePhotoRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateSkillsRequest;
use App\Http\Requests\Profile\UpdateSocialLinksRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProfileServiceInterface $profileService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success('Profile retrieved.', $this->profileService->getProfile($request->user()));
    }

    public function store(StoreProfileRequest $request): JsonResponse
    {
        $profile = $this->profileService->createProfile($request->user(), $request->validated());

        return $this->success('Profile created.', $profile, 201);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $profile = $this->profileService->updateProfile($request->user(), $request->validated());

        return $this->success('Profile updated.', $profile);
    }

    public function updateSkills(UpdateSkillsRequest $request): JsonResponse
    {
        $profile = $this->profileService->updateSkills($request->user(), $request->validated('skills'));

        return $this->success('Skills updated.', $profile);
    }

    public function updatePhoto(UpdateProfilePhotoRequest $request): JsonResponse
    {
        $profile = $this->profileService->updatePhoto(
            $request->user(),
            $request->file('photo'),
            $request->validated('photo_url')
        );

        return $this->success('Profile photo updated.', $profile);
    }

    public function updateDescription(UpdateDescriptionRequest $request): JsonResponse
    {
        $profile = $this->profileService->updateDescription($request->user(), $request->validated('description'));

        return $this->success('Description updated.', $profile);
    }

    public function updateSocialLinks(UpdateSocialLinksRequest $request): JsonResponse
    {
        $profile = $this->profileService->updateSocialLinks($request->user(), $request->validated('social_links'));

        return $this->success('Social links updated.', $profile);
    }
}
