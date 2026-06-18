<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClientProject;
use App\Services\ViewCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientProjectController extends Controller
{
    use ApiResponse;

    public function publicIndex(Request $request): JsonResponse
    {
        $query = ClientProject::query()
            ->with('mypeProfile.user')
            ->whereIn('status', ['published', 'in_progress']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = trim((string) $request->query('category', ''))) {
            $query->where('category', 'like', "%{$category}%");
        }

        if ($request->filled('min_budget')) {
            $query->where('budget_max', '>=', (float) $request->query('min_budget'));
        }

        if ($request->filled('max_budget')) {
            $query->where('budget_min', '<=', (float) $request->query('max_budget'));
        }

        $projects = $query
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ClientProject $project): array => [
                ...$this->payload($project),
                'mype' => $this->mypePayload($project),
            ])
            ->values();

        return $this->success('Client projects loaded.', [
            'projects' => $projects,
            'total' => $projects->count(),
        ]);
    }

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

        $isPro = $request->user()->hasProSubscription();

        return $this->success('Client projects loaded.', [
            'projects' => $projects,
            'limits' => [
                'plan' => $isPro ? 'pro' : 'free',
                'max_projects' => $isPro ? null : 1,
                'can_create' => $isPro || $projects->count() < 1,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $profile = $request->user()->mypeProfile;

        if ($profile === null) {
            return $this->error('MYPE profile not found.', ['profile' => ['Perfil MYPE no encontrado.']], 404);
        }

        if (! $request->user()->hasProSubscription() && $profile->clientProjects()->count() >= 1) {
            return $this->error(
                'El plan Free permite publicar solo 1 proyecto. Actualiza a Pro para crear más publicaciones.',
                ['plan' => ['Actualiza a Pro para crear más de 1 proyecto.']],
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

    public function publicShow(Request $request, ClientProject $clientProject, ViewCounter $views): JsonResponse
    {
        if ($clientProject->status === 'cancelled') {
            return $this->error('Client project not found.', ['project' => ['Proyecto no encontrado.']], 404);
        }

        $clientProject->load('mypeProfile.user');
        $views->track($request, $clientProject, 'client_project');

        return $this->success('Client project loaded.', [
            ...$this->payload($clientProject),
            'mype' => $this->mypePayload($clientProject),
        ]);
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
            'views_count' => $project->views_count,
            'ai_generated' => $project->ai_generated,
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ];
    }

    private function mypePayload(ClientProject $project): array
    {
        $profile = $project->mypeProfile;

        return [
            'id' => $profile?->id,
            'name' => $profile?->business_name ?? $profile?->user?->name ?? 'MYPE',
            'business_name' => $profile?->business_name,
            'industry' => $profile?->industry,
            'description' => $profile?->description,
            'website' => $profile?->website,
            'location' => $profile?->location,
            'profile_photo' => $profile?->profile_photo,
            'photo_url' => $this->storageUrl($profile?->profile_photo),
            'views_count' => $profile?->views_count,
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        return $this->publicMediaUrl($path);
    }
}

