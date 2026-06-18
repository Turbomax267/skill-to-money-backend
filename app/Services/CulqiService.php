<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CulqiService
{
    public function charge(array $payload): array
    {
        $privateKey = (string) config('services.culqi.private_key');

        if ($privateKey === '') {
            throw new RuntimeException('Culqi no esta configurado. Agrega CULQI_PRIVATE_KEY en el backend.');
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
                ?? data_get($body, 'error.merchant_message')
                ?? data_get($body, 'error.user_message')
                ?? data_get($body, 'error.message')
                ?? 'Culqi rechazo el pago.';

            throw new RuntimeException((string) $message, $exception->response?->status() ?? 422, $exception);
        }

        $body = $response->json();
        $charge = is_array($body)
            ? (data_get($body, 'data.object') ?? data_get($body, 'data') ?? $body)
            : null;

        if (! is_array($charge) || ! data_get($charge, 'id')) {
            Log::warning('Culqi returned an unexpected charge response.', [
                'response' => $body,
            ]);

            $message = data_get($body, 'merchant_message')
                ?? data_get($body, 'user_message')
                ?? data_get($body, 'message')
                ?? data_get($body, 'error.merchant_message')
                ?? data_get($body, 'error.user_message')
                ?? data_get($body, 'error.message')
                ?? 'Culqi devolvio una respuesta inesperada. Revisa los logs de Render para ver el detalle.';

            throw new RuntimeException((string) $message);
        }

        if (data_get($charge, 'object') !== null && data_get($charge, 'object') !== 'charge') {
            Log::warning('Culqi returned a non-charge object.', [
                'response' => $body,
            ]);
        }

        return $charge;
    }
}
