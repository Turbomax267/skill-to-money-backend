<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Recommendation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        $query = Recommendation::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->latest('id');

        if (is_string($type) && $type !== '') {
            $query->where('recommendation_type', $type);
        }

        return $this->success('Recommendations retrieved.', $query->limit(20)->get());
    }
}

