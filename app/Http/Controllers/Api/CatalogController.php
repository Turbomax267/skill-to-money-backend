<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FreelancerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = FreelancerProfile::query()
            ->with(['user', 'skills'])
            ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'));

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('headline', 'like', "%{$search}%")
                    ->orWhere('bio', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('skills', fn($sk) => $sk->where('name', 'like', "%{$search}%"));
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($location = $request->input('location')) {
            $query->where('location', 'like', "%{$location}%");
        }

        if ($skill = $request->input('skill')) {
            $query->whereHas('skills', fn($q) => $q->where('name', 'like', "%{$skill}%"));
        }

        if ($minRate = $request->input('min_rate')) {
            $query->where('suggested_rate', '>=', $minRate);
        }

        if ($maxRate = $request->input('max_rate')) {
            $query->where('suggested_rate', '<=', $maxRate);
        }

        $sortField = match ($request->input('sort')) {
            'rating' => 'rating',
            'jobs' => 'completed_jobs',
            default => 'created_at',
        };
        $sortDir = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->input('per_page', 12), 50);
        $freelancers = $query->paginate($perPage);

        $data = $freelancers->map(function (FreelancerProfile $profile) {
            $user = $profile->user;

            return [
                'id' => $profile->id,
                'user_id' => $profile->user_id,
                'name' => $user?->name ?? 'Freelancer',
                'first_name' => $this->extractFirstName($user?->name),
                'dni' => $profile->dni,
                'headline' => $profile->headline,
                'category' => $profile->category,
                'bio' => $profile->bio,
                'suggested_rate' => $profile->suggested_rate,
                'location' => $profile->location,
                'experience_area' => $profile->experience_area,
                'rating' => $profile->rating,
                'completed_jobs' => $profile->completed_jobs,
                'profile_photo' => $profile->profile_photo,
                'skills' => $profile->skills->pluck('name'),
                'availability_status' => $profile->availability_status,
                'created_at' => $profile->created_at,
            ];
        });

        return $this->success('Freelancers encontrados.', [
            'freelancers' => $data,
            'total' => $freelancers->total(),
            'per_page' => $freelancers->perPage(),
            'current_page' => $freelancers->currentPage(),
            'last_page' => $freelancers->lastPage(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $profile = FreelancerProfile::with(['user', 'skills', 'portfolioProjects'])
            ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'))
            ->find($id);

        if (!$profile) {
            return $this->error('Freelancer no encontrado.', status: 404);
        }

        $user = $profile->user;

        return $this->success('Freelancer encontrado.', [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'name' => $user?->name ?? 'Freelancer',
            'headline' => $profile->headline,
            'category' => $profile->category,
            'bio' => $profile->bio,
            'suggested_rate' => $profile->suggested_rate,
            'location' => $profile->location,
            'experience_area' => $profile->experience_area,
            'rating' => $profile->rating,
            'completed_jobs' => $profile->completed_jobs,
            'profile_photo' => $profile->profile_photo,
            'website' => $profile->website,
            'social_links' => $profile->social_links,
            'availability_status' => $profile->availability_status,
            'skills' => $profile->skills->pluck('name'),
            'portfolio' => $profile->portfolioProjects->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'description' => $p->description,
                'image_path' => $p->image_path,
                'external_url' => $p->external_url,
            ]),
        ]);
    }

    private function extractFirstName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        return explode(' ', $name)[0];
    }
}
