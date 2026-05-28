<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class HealthController extends Controller
{
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
