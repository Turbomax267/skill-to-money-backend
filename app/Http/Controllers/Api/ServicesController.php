<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Service;
use App\Services\ViewCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServicesController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:100',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'max_delivery_days' => 'nullable|integer|min:1',
            'sort' => 'nullable|in:latest,price,delivery,popular',
            'order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = Service::query()
            ->with(['freelancer.user', 'freelancer.skills', 'category'])
            ->whereIn('status', ['active', 'published'])
            ->whereHas('freelancer.user', fn($q) => $q->where('user_type', 'freelancer'));
        $like = $this->likeOperator();

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($q) use ($search, $like): void {
                $q->where('title', $like, "%{$search}%")
                    ->orWhere('description', $like, "%{$search}%")
                    ->orWhereHas('category', fn($cq) => $cq->where('name', $like, "%{$search}%"))
                    ->orWhereHas('freelancer.user', fn($uq) => $uq->where('name', $like, "%{$search}%"))
                    ->orWhereHas('freelancer.skills', fn($sq) => $sq->where('name', $like, "%{$search}%"));
            });
        }

        if ($category = $validated['category'] ?? null) {
            $query->whereHas('category', fn($q) => $q->where('name', $like, "%{$category}%"));
        }

        if (isset($validated['min_price'])) {
            $query->where('price', '>=', $validated['min_price']);
        }

        if (isset($validated['max_price'])) {
            $query->where('price', '<=', $validated['max_price']);
        }

        if (isset($validated['max_delivery_days'])) {
            $query->where('delivery_days', '<=', $validated['max_delivery_days']);
        }

        $sortField = match ($validated['sort'] ?? null) {
            'price' => 'price',
            'delivery' => 'delivery_days',
            'popular' => 'views_count',
            default => 'created_at',
        };
        $sortDir = ($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDir);

        $services = $query->paginate((int) ($validated['per_page'] ?? 12));

        return $this->success('Servicios encontrados.', [
            'services' => $services->getCollection()->map(fn(Service $service) => $this->formatService($service)),
            'total' => $services->total(),
            'per_page' => $services->perPage(),
            'current_page' => $services->currentPage(),
            'last_page' => $services->lastPage(),
        ]);
    }

    public function show(Request $request, int $id, ViewCounter $views): JsonResponse
    {
        $service = Service::with(['freelancer.user', 'freelancer.skills', 'category'])
            ->whereIn('status', ['active', 'published'])
            ->whereHas('freelancer.user', fn($q) => $q->where('user_type', 'freelancer'))
            ->find($id);

        if (!$service) {
            return $this->error('Servicio no encontrado.', status: 404);
        }

        $views->track($request, $service, 'service');

        return $this->success('Servicio encontrado.', $this->formatService($service->fresh(['freelancer.user', 'freelancer.skills', 'category'])));
    }

    private function formatService(Service $service): array
    {
        $freelancer = $service->freelancer;
        $user = $freelancer?->user;

        return [
            'id' => $service->id,
            'title' => $service->title,
            'description' => $service->description,
            'price' => (float) $service->price,
            'currency' => $service->currency,
            'delivery_days' => $service->delivery_days,
            'status' => $service->status,
            'views_count' => $service->views_count,
            'category' => $service->category?->name,
            'freelancer' => [
                'id' => $freelancer?->id,
                'user_id' => $freelancer?->user_id,
                'name' => $user?->name ?? 'Freelancer',
                'headline' => $freelancer?->headline,
                'rating' => $freelancer?->rating,
                'completed_jobs' => $freelancer?->completed_jobs,
                'profile_photo' => $freelancer?->profile_photo,
                'photo_url' => $this->storageUrl($freelancer?->profile_photo),
                'skills' => $freelancer?->skills->pluck('name')->values() ?? [],
            ],
            'created_at' => $service->created_at,
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return request()->getSchemeAndHttpHost() . '/api/media/' . ltrim($path, '/');
    }

    private function likeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ilike' : 'like';
    }
}
