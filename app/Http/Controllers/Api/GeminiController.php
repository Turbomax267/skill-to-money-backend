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
        ]);

        $result = $this->geminiService->analyzeFreelancer($data);

        if (!$result['valid']) {
            return $this->error($result['message'], status: 422);
        }

        $analysis = $result['data'];
        $user = $request->user();

        if ($user && $user->freelancerProfile) {
            $user->freelancerProfile->update([
                'headline' => $analysis['headline'] ?? null,
                'category' => $analysis['category'] ?? null,
                'suggested_rate' => $analysis['suggested_rate'] ?? null,
                'bio' => $analysis['bio'] ?? $user->freelancerProfile->bio,
                'gemini_analysis' => [
                    'suggested_projects' => $analysis['suggested_projects'] ?? [],
                    'tips' => $analysis['tips'] ?? [],
                ],
            ]);
        }

        return $this->success('Perfil analizado y actualizado exitosamente.', $analysis);
    }
}
