<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class MarketplaceController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success('Marketplace module ready.', ['module' => 'marketplace']);
    }
}
