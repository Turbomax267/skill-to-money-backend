<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\FreelancerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:100',
            'skill' => 'nullable|string|max:100',
            'min_rate' => 'nullable|numeric|min:0',
            'max_rate' => 'nullable|numeric|min:0',
            'sort' => 'nullable|in:latest,rating,jobs',
            'order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = FreelancerProfile::query()
            ->with(['user', 'skills'])
            ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'));

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($q) use ($search): void {
                $q->where('headline', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('bio', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('skills', fn($sk) => $sk->where('name', 'like', "%{$search}%"));
            });
        }

        if ($category = $validated['category'] ?? null) {
            $query->where('category', 'like', "%{$category}%");
        }

        if ($location = $validated['location'] ?? null) {
            $query->where('location', 'like', "%{$location}%");
        }

        if ($skill = $validated['skill'] ?? null) {
            $query->whereHas('skills', fn($q) => $q->where('name', 'like', "%{$skill}%"));
        }

        $sortField = match ($validated['sort'] ?? null) {
            'rating' => 'rating',
            'jobs' => 'completed_jobs',
            default => 'created_at',
        };
        $sortDir = ($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDir);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $minRate = $validated['min_rate'] ?? null;
        $maxRate = $validated['max_rate'] ?? null;

        if ($minRate !== null || $maxRate !== null) {
            $profiles = $query->get()
                ->filter(function (FreelancerProfile $profile) use ($minRate, $maxRate): bool {
                    $rate = $this->parseRate($profile->suggested_rate);

                    if ($rate === null) {
                        return false;
                    }

                    if ($minRate !== null && $rate < (float) $minRate) {
                        return false;
                    }

                    return !($maxRate !== null && $rate > (float) $maxRate);
                })
                ->values();

            $page = LengthAwarePaginator::resolveCurrentPage();
            $freelancers = new LengthAwarePaginator(
                $profiles->forPage($page, $perPage)->values(),
                $profiles->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        } else {
            $freelancers = $query->paginate($perPage);
        }

        $data = $freelancers->getCollection()->map(fn(FreelancerProfile $profile) => $this->formatFreelancer($profile));

        return $this->success('Freelancers encontrados.', [
            'freelancers' => $data,
            'total' => $freelancers->total(),
            'per_page' => $freelancers->perPage(),
            'current_page' => $freelancers->currentPage(),
            'last_page' => $freelancers->lastPage(),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'status']);

        return $this->success('Categories loaded.', $categories);
    }

    public function show(int $id): JsonResponse
    {
        $profile = FreelancerProfile::with(['user', 'skills', 'portfolioProjects'])
            ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'))
            ->find($id);

        if (!$profile) {
            return $this->error('Freelancer no encontrado.', status: 404);
        }

        return $this->success('Freelancer encontrado.', [
            ...$this->formatFreelancer($profile),
            'website' => $profile->website,
            'social_links' => $profile->social_links,
            'portfolio' => $profile->portfolioProjects->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'description' => $p->description,
                'image_path' => $p->image_path,
                'external_url' => $p->external_url,
            ]),
        ]);
    }

    private function formatFreelancer(FreelancerProfile $profile): array
    {
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
            'rate_amount' => $this->parseRate($profile->suggested_rate),
            'location' => $profile->location,
            'experience_area' => $profile->experience_area,
            'rating' => $profile->rating,
            'completed_jobs' => $profile->completed_jobs,
            'profile_photo' => $profile->profile_photo,
            'skills' => $profile->skills->pluck('name'),
            'availability_status' => $profile->availability_status,
            'created_at' => $profile->created_at,
        ];
    }

    private function extractFirstName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        return explode(' ', $name)[0];
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
