<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\FreelancerProfile;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Services\ProfileScoringService;
use App\Services\ViewCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProfileScoringService $scoring)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:100',
            'skill' => 'nullable|string|max:100',
            'min_rate' => 'nullable|numeric|min:0',
            'max_rate' => 'nullable|numeric|min:0',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'sort' => 'nullable|in:latest,rating,jobs',
            'order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = FreelancerProfile::query()
            ->with(['user', 'skills'])
            ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'));
        $like = $this->likeOperator();

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($q) use ($search, $like): void {
                $q->where('headline', $like, "%{$search}%")
                    ->orWhere('category', $like, "%{$search}%")
                    ->orWhere('bio', $like, "%{$search}%")
                    ->orWhere('location', $like, "%{$search}%")
                    ->orWhereHas('user', fn($uq) => $uq->where('name', $like, "%{$search}%"))
                    ->orWhereHas('skills', fn($sk) => $sk->where('name', $like, "%{$search}%"));
            });
        }

        if ($category = $validated['category'] ?? null) {
            $terms = $this->categoryTerms($category);

            $query->where(function ($q) use ($terms, $like): void {
                foreach ($terms as $term) {
                    $q->orWhere('category', $like, "%{$term}%")
                        ->orWhere('experience_area', $like, "%{$term}%")
                        ->orWhere('headline', $like, "%{$term}%")
                        ->orWhereHas('skills', function ($skillQuery) use ($term, $like): void {
                            $skillQuery->where('name', $like, "%{$term}%")
                                ->orWhere('category', $like, "%{$term}%");
                        });
                }
            });
        }

        if ($location = $validated['location'] ?? null) {
            $query->where('location', $like, "%{$location}%");
        }

        if ($skill = $validated['skill'] ?? null) {
            $query->whereHas('skills', fn($q) => $q->where('name', $like, "%{$skill}%"));
        }

        if (isset($validated['min_rating'])) {
            $query->where('rating', '>=', $validated['min_rating']);
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

    public function show(Request $request, int $id, ViewCounter $views): JsonResponse
    {
        $profile = FreelancerProfile::with(['user', 'skills', 'portfolioProjects.category', 'services.category'])
            ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'))
            ->find($id);

        if (!$profile) {
            return $this->error('Freelancer no encontrado.', status: 404);
        }

        $views->track($request, $profile, 'freelancer_profile');

        return $this->success('Freelancer encontrado.', [
            ...$this->formatFreelancer($profile),
            'website' => $profile->website,
            'social_links' => $profile->social_links,
            'views_count' => $profile->views_count,
            'services' => $profile->services
                ->whereIn('status', ['active', 'published'])
                ->values()
                ->map(fn(Service $service) => [
                    'id' => $service->id,
                    'title' => $service->title,
                    'description' => $service->description,
                    'price' => (float) $service->price,
                    'currency' => $service->currency,
                    'delivery_days' => $service->delivery_days,
                    'status' => $service->status,
                    'views_count' => $service->views_count,
                    'category' => $service->category?->name,
                ]),
            'portfolio' => $profile->portfolioProjects
                ->sortBy(['category.name', 'project_order', 'created_at'])
                ->values()
                ->map(fn(PortfolioProject $project) => [
                    'id' => $project->id,
                    'category_id' => $project->category_id,
                    'category' => $project->category?->name,
                    'title' => $project->title,
                    'description' => $project->description,
                    'image_path' => $project->image_path,
                    'image_url' => $this->storageUrl($project->image_path),
                    'external_url' => $project->external_url,
                    'project_order' => $project->project_order,
                    'is_featured' => $project->is_featured,
                    'created_at' => optional($project->created_at)->toIso8601String(),
                ]),
        ]);
    }

    private function formatFreelancer(FreelancerProfile $profile): array
    {
        $user = $profile->user;
        $visibility = $this->scoring->visibility($profile);

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
            'photo_url' => $this->storageUrl($profile->profile_photo),
            'skills' => $profile->skills->pluck('name'),
            'availability_status' => $profile->availability_status,
            'views_count' => $profile->views_count,
            'visibility_score' => $visibility['score'],
            'visibility_level' => $visibility['level'],
            'created_at' => $profile->created_at,
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        return $this->publicMediaUrl($path);
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

    private function likeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ilike' : 'like';
    }

    private function categoryTerms(string $category): array
    {
        $normalized = strtolower(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], trim($category)));

        $aliases = match ($normalized) {
            'ux/ui', 'diseno ux/ui', 'diseño ux/ui' => [
                $category,
                'UX/UI',
                'UX',
                'UI',
                'Diseño UX',
                'Experiencia de usuario',
                'User Experience',
                'Interfaz de usuario',
            ],
            'ia y automatizacion', 'ia y automatización', 'skill bot & automatizacion', 'skill bot & automatización' => [
                $category,
                'Automatización',
                'Inteligencia artificial',
                'Skill Bot',
                'Make',
                'n8n',
            ],
            default => [$category],
        };

        return array_values(array_unique(array_filter(array_map('trim', $aliases))));
    }
}
