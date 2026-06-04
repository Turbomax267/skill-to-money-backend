<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success('Catalog loaded.', [
            'categories' => Category::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'status']),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'status']);

        return $this->success('Categories loaded.', $categories);
    }
}
