<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClientProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientProjectController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->mypeProfile;

        if ($profile === null) {
            return $this->error('MYPE profile not found.', ['profile' => ['Perfil MYPE no encontrado.']], 404);
        }

        $projects = ClientProject::query()
            ->where('mype_profile_id', $profile->id)
            ->latest()
            ->get()
            ->map(fn (ClientProject $project): array => $this->payload($project));

        return $this->success('Client projects loaded.', [
            'projects' => $projects,
            'limits' => [
                'plan' => 'free',
                'max_projects' => 1,
                'can_create' => $projects->count() < 1,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $profile = $request->user()->mypeProfile;

        if ($profile === null) {
            return $this->error('MYPE profile not found.', ['profile' => ['Perfil MYPE no encontrado.']], 404);
        }

        if ($profile->clientProjects()->count() >= 1) {
            return $this->error(
                'El plan Free permite publicar solo 1 proyecto. Actualiza a Pro para crear mas publicaciones.',
                ['plan' => ['Actualiza a Pro para crear mas de 1 proyecto.']],
                403
            );
        }

        $data = $this->validatedData($request);
        $data['mype_profile_id'] = $profile->id;

        $project = ClientProject::query()->create($data);

        return $this->success('Client project created.', $this->payload($project), 201);
    }

    public function update(Request $request, ClientProject $clientProject): JsonResponse
    {
        if (! $this->ownsProject($request, $clientProject)) {
            return $this->error('Client project not found.', ['project' => ['Proyecto no encontrado.']], 404);
        }

        $clientProject->update($this->validatedData($request));

        return $this->success('Client project updated.', $this->payload($clientProject));
    }

    public function destroy(Request $request, ClientProject $clientProject): JsonResponse
    {
        if (! $this->ownsProject($request, $clientProject)) {
            return $this->error('Client project not found.', ['project' => ['Proyecto no encontrado.']], 404);
        }

        $clientProject->delete();

        return $this->success('Client project deleted.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1200'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'expected_delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'status' => ['required', Rule::in(['draft', 'published', 'in_progress', 'completed', 'cancelled'])],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ai_generated' => ['nullable', 'boolean'],
        ]);
    }

    private function ownsProject(Request $request, ClientProject $project): bool
    {
        return $request->user()->mypeProfile?->id === $project->mype_profile_id;
    }

    private function payload(ClientProject $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'category' => $project->category,
            'description' => $project->description,
            'budget_min' => $project->budget_min,
            'budget_max' => $project->budget_max,
            'expected_delivery_days' => $project->expected_delivery_days,
            'status' => $project->status,
            'progress' => $project->progress,
            'ai_generated' => $project->ai_generated,
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ];
    }
}
