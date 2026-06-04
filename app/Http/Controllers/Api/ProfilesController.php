<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilesController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success('Profiles module ready.', ['module' => 'profiles']);
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success('Profile loaded.', $this->profilePayload($request));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type === 'mype') {
            $data = $request->validate([
                'business_name' => ['sometimes', 'required', 'string', 'max:150'],
                'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
                'description' => ['sometimes', 'nullable', 'string'],
                'website' => ['sometimes', 'nullable', 'string', 'max:255'],
                'location' => ['sometimes', 'nullable', 'string', 'max:150'],
            ]);

            $user->mypeProfile?->update($data);
        } else {
            $data = $request->validate([
                'experience_area' => ['sometimes', 'required', 'string', 'max:150'],
                'bio' => ['sometimes', 'nullable', 'string'],
                'location' => ['sometimes', 'nullable', 'string', 'max:150'],
                'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
                'website' => ['sometimes', 'nullable', 'string', 'max:255'],
                'social_links' => ['sometimes', 'nullable', 'array'],
                'availability_status' => ['sometimes', 'nullable', 'string', 'max:50'],
            ]);

            $user->freelancerProfile?->update($data);
        }

        return $this->success('Profile updated.', $this->profilePayload($request));
    }

    public function updateDescription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string'],
        ]);

        if ($request->user()->user_type === 'mype') {
            $request->user()->mypeProfile?->update(['description' => $data['description']]);
        } else {
            $request->user()->freelancerProfile?->update(['bio' => $data['description']]);
        }

        return $this->success('Description updated.', $this->profilePayload($request));
    }

    public function updateSocialLinks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'social_links' => ['required', 'array'],
        ]);

        $request->user()->freelancerProfile?->update([
            'social_links' => $data['social_links'],
            'website' => $data['social_links']['website'] ?? $request->user()->freelancerProfile?->website,
        ]);

        return $this->success('Social links updated.', $this->profilePayload($request));
    }

    public function updateSkills(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skills' => ['required', 'array'],
            'skills.*' => ['required', 'string', 'max:100'],
        ]);

        $profile = $request->user()->freelancerProfile;

        if ($profile === null) {
            return $this->error('Freelancer profile not found.', ['profile' => ['Perfil no encontrado.']], 404);
        }

        $skillIds = collect($data['skills'])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name): int => Skill::query()->firstOrCreate(['name' => $name])->id)
            ->values()
            ->all();

        $profile->skills()->sync($skillIds);

        return $this->success('Skills updated.', $this->profilePayload($request));
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $path = $data['photo']->store('profiles', 'public');

        if ($request->user()->user_type === 'mype') {
            $request->user()->mypeProfile?->update(['profile_photo' => $path]);
        } else {
            $request->user()->freelancerProfile?->update(['profile_photo' => $path]);
        }

        return $this->success('Photo updated.', $this->profilePayload($request));
    }

    private function profilePayload(Request $request): array
    {
        $user = $request->user()->fresh(['freelancerProfile.skills', 'mypeProfile']);

        if ($user->user_type === 'mype') {
            $profile = $user->mypeProfile;

            return [
                'id' => $profile?->id,
                'user_id' => $user->id,
                'business_name' => $profile?->business_name,
                'ruc' => $profile?->ruc,
                'industry' => $profile?->industry,
                'description' => $profile?->description,
                'website' => $profile?->website,
                'location' => $profile?->location,
                'profile_photo' => $profile?->profile_photo,
                'photo_url' => $profile?->profile_photo ? Storage::disk('public')->url($profile->profile_photo) : null,
            ];
        }

        $profile = $user->freelancerProfile;

        return [
            'id' => $profile?->id,
            'user_id' => $user->id,
            'dni' => $profile?->dni,
            'experience_area' => $profile?->experience_area,
            'bio' => $profile?->bio,
            'description' => $profile?->bio,
            'location' => $profile?->location,
            'contact_phone' => $profile?->contact_phone,
            'website' => $profile?->website,
            'social_links' => $profile?->social_links,
            'availability_status' => $profile?->availability_status,
            'rating' => $profile?->rating,
            'completed_jobs' => $profile?->completed_jobs,
            'visibility_score' => $profile?->visibility_score,
            'profile_photo' => $profile?->profile_photo,
            'photo_url' => $profile?->profile_photo ? Storage::disk('public')->url($profile->profile_photo) : null,
            'skills' => $profile?->skills->pluck('name')->values()->all() ?? [],
        ];
    }
}
