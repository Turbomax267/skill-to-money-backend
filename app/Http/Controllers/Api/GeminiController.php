<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Skill;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeminiController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GeminiService $geminiService)
    {
    }

    public function analyze(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skills' => 'required|array|min:1',
            'skills.*' => 'string|max:100',
            'tools' => 'required|array|min:1',
            'tools.*' => 'string|max:100',
            'description' => 'required|string|min:10|max:500',
            'linkedin' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'areas' => 'nullable|array',
            'areas.*' => 'string|max:150',
            'certificates' => 'nullable|array',
            'certificates.*' => 'string|max:150',
            'has_project_experience' => 'nullable|in:si,no,yes,no',
            'projects' => 'nullable|array|max:3',
            'projects.*.name' => 'nullable|string|max:120',
            'projects.*.title' => 'nullable|string|max:120',
            'projects.*.description' => 'nullable|string|max:300',
            'projects.*.time' => 'nullable|string|max:120',
            'projects.*.estimated_time' => 'nullable|string|max:120',
            'projects.*.tools' => 'nullable|array|max:5',
            'projects.*.tools.*' => 'string|max:100',
            'projects.*.category' => 'nullable|string|max:120',
            'availability' => 'nullable|in:si,no,yes,no,available,unavailable',
            'availability_time' => 'nullable|string|max:120',
            'freelance_goals' => 'nullable|string|max:500',
            'goals' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        if (!$user || !$user->freelancerProfile) {
            return $this->error('Solo los freelancers pueden analizar y actualizar este perfil.', status: 403);
        }

        $result = $this->geminiService->analyzeFreelancer($data);

        if (!$result['valid']) {
            return $this->error($result['message'], status: $result['status'] ?? 422);
        }

        $analysis = $result['data'];

        DB::transaction(function () use ($user, $analysis, $data, $result): void {
            $profile = $user->freelancerProfile;
            $socialLinks = $this->socialLinksFromInput($data);

            $updates = [
                'headline' => $analysis['headline'] ?? null,
                'category' => $analysis['category'] ?? null,
                'experience_area' => $analysis['category'] ?? $this->firstInputValue($data['areas'] ?? []),
                'suggested_rate' => $analysis['suggested_rate'] ?? null,
                'bio' => $analysis['bio'] ?? $profile->bio,
                'website' => $socialLinks['website'] ?? $profile->website,
                'gemini_analysis' => [
                    'titulo_profesional' => $analysis['titulo_profesional'] ?? null,
                    'descripcion_profesional' => $analysis['descripcion_profesional'] ?? null,
                    'propuesta_valor' => $analysis['propuesta_valor'] ?? null,
                    'skills_destacadas' => $analysis['skills_destacadas'] ?? [],
                    'herramientas_destacadas' => $analysis['herramientas_destacadas'] ?? [],
                    'proyectos_optimizados' => $analysis['proyectos_optimizados'] ?? [],
                    'servicios_recomendados' => $analysis['servicios_recomendados'] ?? [],
                    'recomendaciones_mejora' => $analysis['recomendaciones_mejora'] ?? [],
                    'profile_criteria' => $analysis['profile_criteria'] ?? [],
                    'suggested_projects' => $analysis['suggested_projects'] ?? [],
                    'tips' => $analysis['tips'] ?? [],
                    'strengths' => $analysis['strengths'] ?? [],
                    'availability_summary' => $analysis['availability_summary'] ?? null,
                    'source' => $result['source'] ?? (!empty($result['fallback']) ? 'local_fallback' : 'gemini'),
                    'input_snapshot' => $this->inputSnapshot($data),
                ],
            ];

            if (!empty($data['availability'])) {
                $updates['availability_status'] = $this->normalizeAvailabilityStatus($data['availability']);
            }

            if (!empty($socialLinks)) {
                $updates['social_links'] = array_merge($profile->social_links ?? [], $socialLinks);
            }

            $profile->update($updates);
            $this->syncAiSkills($profile, $analysis, $data);
            $this->upsertAiService($profile, $analysis);
            $this->upsertAiPortfolioProjects($profile, $analysis);
        });

        $message = $result['message'] ?? 'Perfil analizado y actualizado exitosamente.';

        return $this->success($message, $analysis);
    }

    private function normalizeAvailabilityStatus(string $availability): ?string
    {
        return match (strtolower($availability)) {
            'si', 'yes', 'available' => 'available',
            'no', 'unavailable' => 'unavailable',
            default => null,
        };
    }

    private function inputSnapshot(array $data): array
    {
        return [
            'skills' => $data['skills'] ?? [],
            'tools' => $data['tools'] ?? [],
            'description' => $data['description'] ?? null,
            'areas' => $data['areas'] ?? [],
            'certificates' => $data['certificates'] ?? [],
            'has_project_experience' => $data['has_project_experience'] ?? null,
            'projects' => $data['projects'] ?? [],
            'availability' => $data['availability'] ?? null,
            'availability_time' => $data['availability_time'] ?? null,
            'freelance_goals' => $data['freelance_goals'] ?? $data['goals'] ?? null,
        ];
    }

    private function syncAiSkills($profile, array $analysis, array $data): void
    {
        $skillNames = $this->stringList(array_merge($data['skills'] ?? [], $analysis['skills_destacadas'] ?? []), 8);
        $toolNames = $this->stringList(array_merge($data['tools'] ?? [], $analysis['herramientas_destacadas'] ?? []), 8);
        $skillIds = [];

        foreach ($skillNames as $name) {
            $skillIds[] = Skill::query()
                ->firstOrCreate(['name' => $name], ['category' => 'habilidades/IA'])
                ->id;
        }

        foreach ($toolNames as $name) {
            $skillIds[] = Skill::query()
                ->firstOrCreate(['name' => $name], ['category' => 'herramientas/IA'])
                ->id;
        }

        if (!empty($skillIds)) {
            $profile->skills()->sync(array_values(array_unique($skillIds)));
        }
    }

    private function upsertAiService($profile, array $analysis): void
    {
        $service = $analysis['servicios_recomendados'][0] ?? null;

        if (!is_array($service)) {
            return;
        }

        $title = $this->text($service['nombre'] ?? null);
        $description = $this->text($service['descripcion'] ?? null);

        if ($title === null || $description === null) {
            return;
        }

        Service::query()->updateOrCreate(
            [
                'freelancer_profile_id' => $profile->id,
                'title' => $title,
            ],
            [
                'category_id' => $this->categoryId($service['categoria'] ?? $analysis['category'] ?? null),
                'description' => $description,
                'price' => $this->parseMoney($service['precio_sugerido'] ?? null),
                'currency' => 'PEN',
                'delivery_days' => $this->parseDeliveryDays($service['tiempo_entrega'] ?? null),
                'status' => 'active',
            ],
        );
    }

    private function upsertAiPortfolioProjects($profile, array $analysis): void
    {
        $projects = array_slice($analysis['proyectos_optimizados'] ?? [], 0, 3);

        foreach ($projects as $index => $project) {
            if (!is_array($project)) {
                continue;
            }

            $title = $this->text($project['nombre'] ?? null);

            if ($title === null) {
                continue;
            }

            PortfolioProject::query()->updateOrCreate(
                [
                    'freelancer_profile_id' => $profile->id,
                    'title' => $title,
                ],
                [
                    'category_id' => $this->categoryId($project['categoria'] ?? $analysis['category'] ?? null),
                    'description' => $this->text($project['descripcion_mejorada'] ?? null),
                    'project_order' => $index,
                    'is_featured' => $index < 2,
                ],
            );
        }
    }

    private function categoryId(mixed $value): ?int
    {
        $name = $this->text($value);

        if ($name === null) {
            return null;
        }

        return Category::query()
            ->firstOrCreate(['name' => Str::limit($name, 100, '')], ['status' => 'active'])
            ->id;
    }

    private function socialLinksFromInput(array $data): array
    {
        return array_filter([
            'linkedin' => $this->text($data['linkedin'] ?? null),
            'instagram' => $this->text($data['instagram'] ?? null),
            'website' => $this->text($data['website'] ?? null),
        ], fn (?string $value): bool => $value !== null);
    }

    private function stringList(array $items, int $limit): array
    {
        $values = [];

        foreach ($items as $item) {
            $text = $this->text($item);

            if ($text === null) {
                continue;
            }

            $values[] = Str::limit($text, 100, '');

            if (count($values) >= $limit) {
                break;
            }
        }

        return array_values(array_unique($values));
    }

    private function firstInputValue(array $items): ?string
    {
        return $this->stringList($items, 1)[0] ?? null;
    }

    private function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function parseMoney(mixed $value): float
    {
        $text = $this->text($value) ?? '';

        if (preg_match('/\d+(?:[.,]\d+)?/', $text, $matches) !== 1) {
            return 30.0;
        }

        return max(0.0, (float) str_replace(',', '.', $matches[0]));
    }

    private function parseDeliveryDays(mixed $value): int
    {
        $text = Str::of($this->text($value) ?? '')->ascii()->lower()->value();

        if (preg_match_all('/\d+/', $text, $matches) < 1) {
            return 3;
        }

        $days = max(array_map('intval', $matches[0]));

        if (str_contains($text, 'semana')) {
            $days *= 7;
        }

        return max(1, $days);
    }
}
