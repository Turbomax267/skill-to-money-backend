<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            return $this->error($result['message'], status: 422);
        }

        $analysis = $result['data'];

        $updates = [
            'headline' => $analysis['headline'] ?? null,
            'category' => $analysis['category'] ?? null,
            'suggested_rate' => $analysis['suggested_rate'] ?? null,
            'bio' => $analysis['bio'] ?? $user->freelancerProfile->bio,
            'gemini_analysis' => [
                'profile_criteria' => $analysis['profile_criteria'] ?? [],
                'suggested_projects' => $analysis['suggested_projects'] ?? [],
                'tips' => $analysis['tips'] ?? [],
                'strengths' => $analysis['strengths'] ?? [],
                'availability_summary' => $analysis['availability_summary'] ?? null,
                'input_snapshot' => $this->inputSnapshot($data),
            ],
        ];

        if (!empty($data['availability'])) {
            $updates['availability_status'] = $this->normalizeAvailabilityStatus($data['availability']);
        }

        $user->freelancerProfile->update($updates);

        return $this->success('Perfil analizado y actualizado exitosamente.', $analysis);
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
}
