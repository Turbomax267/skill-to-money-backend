<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClientProject;
use App\Models\MypeProfile;
use App\Services\ViewCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MypeController extends Controller
{
    use ApiResponse;

    public function show(Request $request, MypeProfile $mypeProfile, ViewCounter $views): JsonResponse
    {
        $mypeProfile->load(['user', 'clientProjects' => fn ($query) => $query->whereNotIn('status', ['cancelled'])->latest()]);
        $views->track($request, $mypeProfile, 'mype_profile');

        return $this->success('MYPE loaded.', [
            'id' => $mypeProfile->id,
            'user_id' => $mypeProfile->user_id,
            'name' => $mypeProfile->business_name ?? $mypeProfile->user?->name ?? 'MYPE',
            'business_name' => $mypeProfile->business_name,
            'industry' => $mypeProfile->industry,
            'description' => $mypeProfile->description,
            'website' => $mypeProfile->website,
            'location' => $mypeProfile->location,
            'profile_photo' => $mypeProfile->profile_photo,
            'views_count' => $mypeProfile->views_count,
            'projects' => $mypeProfile->clientProjects
                ->values()
                ->map(fn (ClientProject $project): array => [
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
                    'created_at' => $project->created_at,
                ]),
        ]);
    }
}
