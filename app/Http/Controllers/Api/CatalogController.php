<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success('Catalog module ready.', ['module' => 'catalog']);
    }
}
