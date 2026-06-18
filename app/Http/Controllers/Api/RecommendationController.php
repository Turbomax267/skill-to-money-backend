<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClientProject;
use App\Models\FreelancerProfile;
use App\Models\MatchResult;
use App\Services\ProfileScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProfileScoringService $scoring)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:freelancer',
            'project_id' => 'nullable|integer|exists:client_projects,id',
            'search' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:100',
            'skill' => 'nullable|string|max:100',
            'min_rate' => 'nullable|numeric|min:0',
            'max_rate' => 'nullable|numeric|min:0',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $project = null;

        if (isset($validated['project_id'])) {
            $project = ClientProject::query()->findOrFail($validated['project_id']);

            if ($request->user()->mypeProfile?->id !== $project->mype_profile_id) {
                return $this->error('Client project not found.', ['project' => ['Proyecto no encontrado.']], 404);
            }

            $validated['search'] = trim($project->title . ' ' . $project->description);
            $validated['category'] = $project->category ?? ($validated['category'] ?? null);
            $validated['min_rate'] = $project->budget_min ?? ($validated['min_rate'] ?? null);
            $validated['max_rate'] = $project->budget_max ?? ($validated['max_rate'] ?? null);
        }

        $limit = (int) ($validated['limit'] ?? 6);
        $profiles = FreelancerProfile::query()
            ->with(['user', 'skills', 'portfolioProjects', 'services'])
            ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'))
            ->get()
            ->filter(fn(FreelancerProfile $profile) => $this->hasRecommendableProfile($profile))
            ->map(fn(FreelancerProfile $profile) => $this->scoreProfile($profile, $validated, $project))
            ->filter(fn(array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        if ($profiles->isEmpty() && ! $this->hasSpecificRecommendationContext($validated)) {
            $profiles = FreelancerProfile::query()
                ->with(['user', 'skills', 'portfolioProjects', 'services'])
                ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'))
                ->get()
                ->filter(fn(FreelancerProfile $profile) => $this->hasRecommendableProfile($profile))
                ->sortByDesc(fn(FreelancerProfile $profile) => ((float) $profile->rating * 100) + (int) $profile->completed_jobs)
                ->take($limit)
                ->map(fn(FreelancerProfile $profile) => $this->fallbackProfile($profile))
                ->values();
        }

        return $this->success('Freelancers recomendados.', [
            'recommendations' => $profiles,
        ]);
    }

    private function scoreProfile(FreelancerProfile $profile, array $filters, ?ClientProject $project = null): array
    {
        $compatibility = $this->scoring->compatibility($profile, $filters, $project);
        $skills = $profile->skills->pluck('name')->values();
        $rate = $this->scoring->parseRate($profile->suggested_rate);

        if ($project !== null) {
            MatchResult::query()->updateOrCreate(
                [
                    'mype_profile_id' => $project->mype_profile_id,
                    'freelancer_profile_id' => $profile->id,
                    'service_id' => null,
                ],
                [
                    'compatibility_score' => $compatibility['score'],
                    'reason' => implode(' ', $compatibility['reasons']),
                    'status' => 'suggested',
                ],
            );
        }

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'name' => $profile->user?->name ?? 'Freelancer',
            'headline' => $profile->headline,
            'category' => $profile->category,
            'bio' => $profile->bio,
            'suggested_rate' => $profile->suggested_rate,
            'rate_amount' => $rate,
            'location' => $profile->location,
            'rating' => $profile->rating,
            'completed_jobs' => $profile->completed_jobs,
            'profile_photo' => $profile->profile_photo,
            'photo_url' => $this->storageUrl($profile->profile_photo),
            'skills' => $skills,
            'availability_status' => $profile->availability_status,
            'score' => $compatibility['score'],
            'compatibility_score' => $compatibility['score'],
            'compatibility_level' => $compatibility['level'],
            'compatibility_breakdown' => $compatibility['breakdown'],
            'reasons' => $compatibility['reasons'],
        ];
    }

    private function fallbackProfile(FreelancerProfile $profile): array
    {
        $item = $this->scoreProfile($profile, []);
        $item['score'] = min(100, 40 + (float) $profile->rating * 8 + min(20, (int) $profile->completed_jobs));
        $item['compatibility_score'] = $item['score'];
        $item['compatibility_level'] = 'Perfil destacado';
        $item['reasons'] = ['Perfil destacado por reputacion y experiencia.'];

        return $item;
    }

    private function hasSpecificRecommendationContext(array $filters): bool
    {
        return filled($filters['project_id'] ?? null)
            || filled($filters['search'] ?? null)
            || filled($filters['category'] ?? null)
            || filled($filters['skill'] ?? null);
    }

    private function hasRecommendableProfile(FreelancerProfile $profile): bool
    {
        $profile->loadMissing(['skills', 'portfolioProjects', 'services']);

        if (filled($profile->category) || filled($profile->headline) || filled($profile->bio)) {
            return true;
        }

        return $profile->skills->isNotEmpty()
            || $profile->portfolioProjects->isNotEmpty()
            || $profile->services->isNotEmpty();
    }

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return request()->getSchemeAndHttpHost() . '/api/media/' . ltrim($path, '/');
    }

}
