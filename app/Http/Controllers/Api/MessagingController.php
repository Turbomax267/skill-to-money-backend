<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class MessagingController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success('Messaging module ready.', ['module' => 'messaging']);
    }
}
