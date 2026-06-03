<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class PeruApiService
{
    public function validateRuc(string $ruc): array
    {
        $apiKey = config('services.peru_api.key');

        if (empty($apiKey)) {
            return [
                'valid' => false,
                'message' => 'No se configuro la API Key de Peru API.',
            ];
        }

        try {
            $response = Http::baseUrl((string) config('services.peru_api.base_url'))
                ->timeout((int) config('services.peru_api.timeout', 8))
                ->acceptJson()
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->get('/api/ruc/'.$ruc, [
                    'summary' => 0,
                    'plan' => 0,
                ]);
        } catch (Throwable) {
            return [
                'valid' => false,
                'message' => 'No se pudo validar el RUC en SUNAT. Intente nuevamente.',
            ];
        }

        if ($response->status() === 404) {
            return [
                'valid' => false,
                'message' => 'El RUC no existe en SUNAT.',
            ];
        }

        if ($response->status() === 401) {
            return [
                'valid' => false,
                'message' => 'No se pudo validar el RUC por credenciales invalidas.',
            ];
        }

        if (! $response->successful()) {
            return [
                'valid' => false,
                'message' => 'No se pudo validar el RUC en SUNAT. Intente nuevamente.',
            ];
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== '200' || empty($data['razon_social'])) {
            return [
                'valid' => false,
                'message' => 'El RUC no existe o no tiene datos validos en SUNAT.',
            ];
        }

        $estado = strtoupper(trim((string) ($data['estado'] ?? '')));
        $condicion = strtoupper(trim((string) ($data['condicion'] ?? '')));

        if ($estado !== 'ACTIVO' || $condicion !== 'HABIDO') {
            return [
                'valid' => false,
                'message' => 'El RUC debe estar ACTIVO y HABIDO en SUNAT.',
            ];
        }

        return [
            'valid' => true,
            'ruc' => $data['ruc'] ?? $ruc,
            'business_name' => $data['razon_social'],
            'state' => $estado,
            'condition' => $condicion,
            'location' => $this->resolveLocation($data),
        ];
    }

    private function resolveLocation(array $data): ?string
    {
        $parts = array_filter([
            $data['distrito'] ?? null,
            $data['provincia'] ?? null,
            $data['departamento'] ?? null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
