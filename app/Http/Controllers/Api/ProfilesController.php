<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProfilesController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success('Profiles module ready.', ['module' => 'profiles']);
    }
}
