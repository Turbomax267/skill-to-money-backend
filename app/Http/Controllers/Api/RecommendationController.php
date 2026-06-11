<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FreelancerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecommendationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:freelancer',
            'search' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:100',
            'skill' => 'nullable|string|max:100',
            'max_rate' => 'nullable|numeric|min:0',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $limit = (int) ($validated['limit'] ?? 6);
        $profiles = FreelancerProfile::query()
            ->with(['user', 'skills'])
            ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'))
            ->get()
            ->map(fn(FreelancerProfile $profile) => $this->scoreProfile($profile, $validated))
            ->filter(fn(array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        if ($profiles->isEmpty()) {
            $profiles = FreelancerProfile::query()
                ->with(['user', 'skills'])
                ->whereHas('user', fn($q) => $q->where('user_type', 'freelancer'))
                ->orderByDesc('rating')
                ->orderByDesc('completed_jobs')
                ->limit($limit)
                ->get()
                ->map(fn(FreelancerProfile $profile) => $this->fallbackProfile($profile))
                ->values();
        }

        return $this->success('Freelancers recomendados.', [
            'recommendations' => $profiles,
        ]);
    }

    private function scoreProfile(FreelancerProfile $profile, array $filters): array
    {
        $score = 0;
        $reasons = [];
        $search = $this->normalize($filters['search'] ?? '');
        $category = $this->normalize($filters['category'] ?? '');
        $skill = $this->normalize($filters['skill'] ?? '');
        $skills = $profile->skills->pluck('name')->values();
        $skillText = $this->normalize($skills->implode(' '));
        $profileText = $this->normalize(implode(' ', [
            $profile->headline,
            $profile->category,
            $profile->bio,
            $profile->experience_area,
            $profile->user?->name,
            $skillText,
        ]));

        if ($category !== '' && str_contains($this->normalize((string) $profile->category), $category)) {
            $score += 35;
            $reasons[] = 'Coincide con la categoria solicitada.';
        }

        if ($skill !== '' && str_contains($skillText, $skill)) {
            $score += 30;
            $reasons[] = 'Tiene habilidades relacionadas.';
        }

        if ($search !== '') {
            foreach ($this->terms($search) as $term) {
                if (str_contains($profileText, $term)) {
                    $score += 8;
                }
            }

            if ($score > 0) {
                $reasons[] = 'Su perfil coincide con tu busqueda.';
            }
        }

        $rate = $this->parseRate($profile->suggested_rate);
        if (isset($filters['max_rate']) && $rate !== null && $rate <= (float) $filters['max_rate']) {
            $score += 15;
            $reasons[] = 'Se ajusta al presupuesto indicado.';
        }

        $rating = (float) $profile->rating;
        if (isset($filters['min_rating']) && $rating >= (float) $filters['min_rating']) {
            $score += 15;
            $reasons[] = 'Cumple la reputacion minima.';
        } elseif ($rating >= 4.5) {
            $score += 10;
            $reasons[] = 'Tiene buena reputacion.';
        }

        if ($profile->completed_jobs > 0) {
            $score += min(10, (int) $profile->completed_jobs);
            $reasons[] = 'Tiene trabajos completados.';
        }

        if ($profile->availability_status === 'available') {
            $score += 5;
            $reasons[] = 'Esta disponible para nuevos proyectos.';
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
            'score' => min(100, $score),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private function fallbackProfile(FreelancerProfile $profile): array
    {
        $item = $this->scoreProfile($profile, []);
        $item['score'] = min(100, 40 + (float) $profile->rating * 8 + min(20, (int) $profile->completed_jobs));
        $item['reasons'] = ['Perfil destacado por reputacion y experiencia.'];

        return $item;
    }

    private function terms(string $value): array
    {
        return array_values(array_filter(explode(' ', $value), fn(string $term) => strlen($term) >= 3));
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return request()->getSchemeAndHttpHost() . '/api/media/' . ltrim($path, '/');
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
