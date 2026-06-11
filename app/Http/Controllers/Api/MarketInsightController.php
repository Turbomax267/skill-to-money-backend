<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClientProject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketInsightController extends Controller
{
    use ApiResponse;

    public function trends(Request $request): JsonResponse
    {
        $profile = $request->user()->freelancerProfile;

        if ($profile === null) {
            return $this->error('Freelancer profile not found.', ['profile' => ['Perfil freelancer no encontrado.']], 404);
        }

        $keywords = $this->freelancerKeywords($profile);
        $projects = $this->marketProjects($keywords);

        if ($projects->isEmpty()) {
            return $this->success('No hay tendencias suficientes para tu perfil todavía.', [
                'trends' => [],
                'has_data' => false,
                'keywords' => $keywords,
            ]);
        }

        $trends = $projects
            ->groupBy(fn (ClientProject $project): string => $this->trendLabel($project, $keywords))
            ->map(function (Collection $items, string $label): array {
                $budgets = $this->budgets($items);

                return [
                    'label' => $label,
                    'demand_count' => $items->count(),
                    'average_budget' => $this->average($budgets),
                    'min_budget' => empty($budgets) ? null : min($budgets),
                    'max_budget' => empty($budgets) ? null : max($budgets),
                    'currency' => 'PEN',
                    'sample_projects' => $items->take(3)->map(fn (ClientProject $project): array => [
                        'id' => $project->id,
                        'title' => $project->title,
                        'category' => $project->category,
                    ])->values(),
                ];
            })
            ->sortByDesc('demand_count')
            ->values()
            ->take(6);

        return $this->success('Tendencias de mercado calculadas.', [
            'trends' => $trends,
            'has_data' => $trends->isNotEmpty(),
            'keywords' => $keywords,
        ]);
    }

    public function priceSuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'category' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $category = $validated['category'] ?? null;
        $search = $validated['search'] ?? null;

        if (isset($validated['project_id'])) {
            $project = ClientProject::query()->findOrFail($validated['project_id']);

            if ($request->user()->mypeProfile?->id !== $project->mype_profile_id) {
                return $this->error('Client project not found.', ['project' => ['Proyecto no encontrado.']], 404);
            }

            $category = $project->category;
            $search = trim($project->title . ' ' . $project->description);
        }

        $keywords = $this->keywordsFromText(trim(($category ?? '') . ' ' . ($search ?? '')));
        $projects = $this->marketProjects($keywords, $category);
        $budgets = $this->budgets($projects);

        if (empty($budgets)) {
            return $this->success('No hay datos suficientes para recomendar precios todavía.', [
                'has_data' => false,
                'sample_count' => 0,
                'recommended_min' => null,
                'recommended_max' => null,
                'average_price' => null,
                'currency' => 'PEN',
            ]);
        }

        return $this->success('Rango de precio sugerido calculado.', [
            'has_data' => true,
            'sample_count' => count($budgets),
            'recommended_min' => round(min($budgets), 2),
            'recommended_max' => round(max($budgets), 2),
            'average_price' => round($this->average($budgets), 2),
            'currency' => 'PEN',
            'source' => 'client_projects',
        ]);
    }

    private function marketProjects(array $keywords = [], ?string $category = null): Collection
    {
        $query = ClientProject::query()
            ->whereNotIn('status', ['cancelled'])
            ->latest();

        if ($category) {
            $query->where('category', 'like', '%' . $category . '%');
        }

        if (!empty($keywords)) {
            $query->where(function ($query) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $query
                        ->orWhere('title', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhere('category', 'like', "%{$keyword}%");
                }
            });
        }

        return $query->limit(200)->get();
    }

    private function freelancerKeywords($profile): array
    {
        $skills = $profile->skills()->pluck('name')->all();

        return $this->keywordsFromText(implode(' ', [
            $profile->headline,
            $profile->category,
            $profile->experience_area,
            $profile->bio,
            ...$skills,
        ]));
    }

    private function keywordsFromText(string $text): array
    {
        $normalized = Str::of($text)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->value();
        $stopwords = ['para', 'con', 'los', 'las', 'una', 'uno', 'del', 'por', 'que', 'como', 'servicio', 'proyecto'];

        return collect(explode(' ', $normalized))
            ->map(fn (string $word): string => trim($word))
            ->filter(fn (string $word): bool => strlen($word) >= 3 && !in_array($word, $stopwords, true))
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    private function trendLabel(ClientProject $project, array $keywords): string
    {
        if ($project->category) {
            return $project->category;
        }

        $text = Str::of($project->title . ' ' . $project->description)->ascii()->lower()->value();

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return Str::headline($keyword);
            }
        }

        return Str::limit($project->title, 60, '');
    }

    private function budgets(Collection $projects): array
    {
        return $projects
            ->map(function (ClientProject $project): ?float {
                $min = $project->budget_min !== null ? (float) $project->budget_min : null;
                $max = $project->budget_max !== null ? (float) $project->budget_max : null;

                if ($min !== null && $max !== null) {
                    return ($min + $max) / 2;
                }

                return $min ?? $max;
            })
            ->filter(fn (?float $value): bool => $value !== null && $value > 0)
            ->values()
            ->all();
    }

    private function average(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }
}

