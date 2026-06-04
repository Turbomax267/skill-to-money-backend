<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\PeruApiService;
use Illuminate\Http\JsonResponse;

class PeruController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PeruApiService $peruApiService)
    {
    }

    public function dni(string $dni): JsonResponse
    {
        if (!preg_match('/^\d{8}$/', $dni)) {
            return $this->error('El DNI debe tener 8 dígitos.', errors: ['dni' => ['Formato inválido.']], status: 422);
        }

        $result = $this->peruApiService->lookupDni($dni);

        if (!$result['valid']) {
            return $this->error($result['message'], status: 422);
        }

        return $this->success('DNI encontrado.', [
            'dni' => $result['dni'],
            'first_name' => $result['first_name'],
            'last_name' => $result['last_name'],
            'full_name' => $result['full_name'],
        ]);
    }

    public function ruc(string $ruc): JsonResponse
    {
        if (!preg_match('/^\d{11}$/', $ruc)) {
            return $this->error('El RUC debe tener 11 dígitos.', errors: ['ruc' => ['Formato inválido.']], status: 422);
        }

        $result = $this->peruApiService->validateRuc($ruc);

        if (!$result['valid']) {
            return $this->error($result['message'], status: 422);
        }

        return $this->success('RUC encontrado.', [
            'ruc' => $result['ruc'],
            'business_name' => $result['business_name'],
            'state' => $result['state'],
            'condition' => $result['condition'],
            'location' => $result['location'],
        ]);
    }
}
