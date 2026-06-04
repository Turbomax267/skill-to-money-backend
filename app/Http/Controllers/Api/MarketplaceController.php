<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PortfolioProject;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MarketplaceController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success('Marketplace module ready.', ['module' => 'marketplace']);
    }

    public function services(Request $request): JsonResponse
    {
        $profile = $request->user()->freelancerProfile;

        if ($profile === null) {
            return $this->error('Freelancer profile not found.', ['profile' => ['Perfil no encontrado.']], 404);
        }

        $services = Service::query()
            ->with('category:id,name')
            ->where('freelancer_profile_id', $profile->id)
            ->latest()
            ->get()
            ->map(fn (Service $service): array => $this->servicePayload($service));

        return $this->success('Services loaded.', $services);
    }

    public function storeService(Request $request): JsonResponse
    {
        $profile = $request->user()->freelancerProfile;

        if ($profile === null) {
            return $this->error('Freelancer profile not found.', ['profile' => ['Perfil no encontrado.']], 404);
        }

        $data = $this->serviceData($request);
        $data['freelancer_profile_id'] = $profile->id;

        $service = Service::query()->create($data)->load('category:id,name');

        return $this->success('Service created.', $this->servicePayload($service), 201);
    }

    public function updateService(Request $request, Service $service): JsonResponse
    {
        if (! $this->ownsService($request, $service)) {
            return $this->error('Service not found.', ['service' => ['Servicio no encontrado.']], 404);
        }

        $service->update($this->serviceData($request));
        $service->load('category:id,name');

        return $this->success('Service updated.', $this->servicePayload($service));
    }

    public function deleteService(Request $request, Service $service): JsonResponse
    {
        if (! $this->ownsService($request, $service)) {
            return $this->error('Service not found.', ['service' => ['Servicio no encontrado.']], 404);
        }

        $service->delete();

        return $this->success('Service deleted.');
    }

    public function portfolio(Request $request): JsonResponse
    {
        $profile = $request->user()->freelancerProfile;

        if ($profile === null) {
            return $this->error('Freelancer profile not found.', ['profile' => ['Perfil no encontrado.']], 404);
        }

        $projects = PortfolioProject::query()
            ->with('category:id,name')
            ->where('freelancer_profile_id', $profile->id)
            ->orderBy('project_order')
            ->latest()
            ->get()
            ->map(fn (PortfolioProject $project): array => $this->portfolioPayload($project));

        return $this->success('Portfolio loaded.', $projects);
    }

    public function storePortfolioProject(Request $request): JsonResponse
    {
        $profile = $request->user()->freelancerProfile;

        if ($profile === null) {
            return $this->error('Freelancer profile not found.', ['profile' => ['Perfil no encontrado.']], 404);
        }

        $data = $this->portfolioData($request);
        $data['freelancer_profile_id'] = $profile->id;

        $project = PortfolioProject::query()->create($data)->load('category:id,name');

        return $this->success('Portfolio project created.', $this->portfolioPayload($project), 201);
    }

    public function updatePortfolioProject(Request $request, PortfolioProject $portfolioProject): JsonResponse
    {
        if (! $this->ownsPortfolioProject($request, $portfolioProject)) {
            return $this->error('Portfolio project not found.', ['project' => ['Proyecto no encontrado.']], 404);
        }

        $portfolioProject->update($this->portfolioData($request, $portfolioProject));
        $portfolioProject->load('category:id,name');

        return $this->success('Portfolio project updated.', $this->portfolioPayload($portfolioProject));
    }

    public function deletePortfolioProject(Request $request, PortfolioProject $portfolioProject): JsonResponse
    {
        if (! $this->ownsPortfolioProject($request, $portfolioProject)) {
            return $this->error('Portfolio project not found.', ['project' => ['Proyecto no encontrado.']], 404);
        }

        $portfolioProject->delete();

        return $this->success('Portfolio project deleted.');
    }

    private function serviceData(Request $request): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'delivery_days' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['active', 'paused', 'draft'])],
        ]);
    }

    private function portfolioData(Request $request, ?PortfolioProject $project = null): array
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'file' => ['nullable', 'file', 'max:15360'],
            'external_url' => ['nullable', 'string', 'max:255'],
            'project_order' => ['required', 'integer', 'min:0'],
            'is_featured' => ['required', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('portfolio/images', 'public');
        } elseif ($project !== null) {
            $data['image_path'] = $project->image_path;
        }

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('portfolio/files', 'public');
        } elseif ($project !== null) {
            $data['file_path'] = $project->file_path;
        }

        unset($data['image'], $data['file']);

        return $data;
    }

    private function ownsService(Request $request, Service $service): bool
    {
        return $request->user()->freelancerProfile?->id === $service->freelancer_profile_id;
    }

    private function ownsPortfolioProject(Request $request, PortfolioProject $project): bool
    {
        return $request->user()->freelancerProfile?->id === $project->freelancer_profile_id;
    }

    private function servicePayload(Service $service): array
    {
        return [
            'id' => $service->id,
            'category_id' => $service->category_id,
            'category' => $service->category?->name,
            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'currency' => $service->currency,
            'delivery_days' => $service->delivery_days,
            'status' => $service->status,
            'views_count' => $service->views_count,
            'created_at' => $service->created_at,
        ];
    }

    private function portfolioPayload(PortfolioProject $project): array
    {
        return [
            'id' => $project->id,
            'category_id' => $project->category_id,
            'category' => $project->category?->name,
            'title' => $project->title,
            'description' => $project->description,
            'image_path' => $project->image_path,
            'image_url' => $project->image_path ? Storage::disk('public')->url($project->image_path) : null,
            'file_path' => $project->file_path,
            'file_url' => $project->file_path ? Storage::disk('public')->url($project->file_path) : null,
            'external_url' => $project->external_url,
            'project_order' => $project->project_order,
            'is_featured' => $project->is_featured,
            'created_at' => $project->created_at,
        ];
    }
}
