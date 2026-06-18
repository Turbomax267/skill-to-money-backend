<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Favorite;
use App\Models\FreelancerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FavoritesController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mypeProfile = $user->mypeProfile;

        if (!$mypeProfile) {
            return $this->error('Solo las MYPES pueden tener favoritos.', status: 403);
        }

        $favorites = $mypeProfile->favorites()
            ->with(['freelancer.user', 'freelancer.skills'])
            ->get()
            ->map(fn(Favorite $fav) => $this->formatFreelancer($fav->freelancer));

        return $this->success('Favoritos obtenidos.', ['favorites' => $favorites]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $mypeProfile = $user->mypeProfile;

        if (!$mypeProfile) {
            return $this->error('Solo las MYPES pueden agregar favoritos.', status: 403);
        }

        $validated = $request->validate([
            'freelancer_profile_id' => 'required|exists:freelancer_profiles,id',
        ]);

        $exists = Favorite::where('mype_profile_id', $mypeProfile->id)
            ->where('freelancer_profile_id', $validated['freelancer_profile_id'])
            ->exists();

        if ($exists) {
            return $this->error('Este freelancer ya está en tus favoritos.', status: 409);
        }

        $favorite = $mypeProfile->favorites()->create([
            'freelancer_profile_id' => $validated['freelancer_profile_id'],
        ]);

        $favorite->load(['freelancer.user', 'freelancer.skills']);

        return $this->success('Freelancer agregado a favoritos.', [
            'favorite' => $this->formatFreelancer($favorite->freelancer),
        ], 201);
    }

    public function destroy(int $freelancerProfileId, Request $request): JsonResponse
    {
        $user = $request->user();
        $mypeProfile = $user->mypeProfile;

        if (!$mypeProfile) {
            return $this->error('Solo las MYPES pueden eliminar favoritos.', status: 403);
        }

        $deleted = Favorite::where('mype_profile_id', $mypeProfile->id)
            ->where('freelancer_profile_id', $freelancerProfileId)
            ->delete();

        if (!$deleted) {
            return $this->error('Favorito no encontrado.', status: 404);
        }

        return $this->success('Freelancer eliminado de favoritos.');
    }

    private function formatFreelancer(FreelancerProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'name' => $profile->user?->name ?? 'Freelancer',
            'headline' => $profile->headline,
            'category' => $profile->category,
            'bio' => $profile->bio,
            'suggested_rate' => $profile->suggested_rate,
            'rate_amount' => $this->parseRate($profile->suggested_rate),
            'location' => $profile->location,
            'experience_area' => $profile->experience_area,
            'rating' => $profile->rating,
            'completed_jobs' => $profile->completed_jobs,
            'profile_photo' => $profile->profile_photo,
            'photo_url' => $this->storageUrl($profile->profile_photo),
            'skills' => $profile->skills->pluck('name'),
            'availability_status' => $profile->availability_status,
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        return $this->publicMediaUrl($path);
    }

    private function parseRate(?string $rate): ?float
    {
        if ($rate === null) {
            return null;
        }

        if (!preg_match('/\d+(?:[.,]\d+)?/', $rate, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[0]);
    }
}
