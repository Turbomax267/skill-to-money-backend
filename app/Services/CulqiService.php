<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CulqiService
{
    public function charge(array $payload): array
    {
        $privateKey = (string) config('services.culqi.private_key');

        if ($privateKey === '') {
            throw new RuntimeException('Culqi no está configurado. Agrega CULQI_PRIVATE_KEY en el backend.');
        }

        try {
            $response = Http::withToken($privateKey)
                ->acceptJson()
                ->timeout((int) config('services.culqi.timeout', 20))
                ->post(rtrim((string) config('services.culqi.base_url', 'https://api.culqi.com/v2'), '/') . '/charges', $payload)
                ->throw();
        } catch (RequestException $exception) {
            $body = $exception->response?->json();
            $message = data_get($body, 'merchant_message')
                ?? data_get($body, 'user_message')
                ?? data_get($body, 'message')
                ?? 'Culqi rechazó el pago.';

            throw new RuntimeException((string) $message, $exception->response?->status() ?? 422, $exception);
        }

        $charge = $response->json();

        if (! is_array($charge) || data_get($charge, 'object') !== 'charge') {
            throw new RuntimeException('Culqi devolvió una respuesta inesperada.');
        }

        return $charge;
    }
}
