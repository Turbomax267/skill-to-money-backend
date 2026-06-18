<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\CulqiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    use ApiResponse;

    private const PRO_MONTHLY_PRICE = 29.00;
    private const PRO_YEARLY_PRICE = 290.00;

    public function show(Request $request): JsonResponse
    {
        if (! Schema::hasTable('subscriptions')) {
            return $this->success('Suscripción cargada.', $this->freePayload($request->user()));
        }

        return $this->success('Suscripción cargada.', $this->subscriptionPayload($request->user()));
    }

    public function checkout(Request $request): JsonResponse
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('subscription_payments')) {
            return $this->error(
                'La pasarela de suscripción aún no está lista. Ejecuta las migraciones en Render.',
                ['database' => ['Faltan las tablas subscriptions o subscription_payments.']],
                503
            );
        }

        $data = $request->validate([
            'plan' => ['required', Rule::in(['pro'])],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'payment_method' => ['required', Rule::in(['card', 'yape', 'plin'])],
            'save_payment_method' => ['nullable', 'boolean'],
            'payment_details' => ['required', 'array'],
            'payment_details.card_number' => ['nullable', 'string', 'max:25'],
            'payment_details.card_holder' => ['nullable', 'string', 'max:120'],
            'payment_details.expiry_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'payment_details.expiry_year' => ['nullable', 'integer', 'min:24', 'max:2100'],
            'payment_details.cvv' => ['nullable', 'string', 'size:3'],
            'payment_details.phone' => ['nullable', 'string', 'max:20'],
            'payment_details.culqi_token' => ['nullable', 'string', 'max:120'],
            'payment_details.culqi_email' => ['nullable', 'email', 'max:160'],
            'payment_details.device_finger_print_id' => ['nullable', 'string', 'max:120'],
            'payment_details.authentication_3ds' => ['nullable', 'array'],
            'payment_details.authentication_3ds.eci' => ['nullable', 'string', 'max:30'],
            'payment_details.authentication_3ds.xid' => ['nullable', 'string', 'max:255'],
            'payment_details.authentication_3ds.cavv' => ['nullable', 'string', 'max:255'],
            'payment_details.authentication_3ds.protocolVersion' => ['nullable', 'string', 'max:30'],
            'payment_details.authentication_3ds.directoryServerTransactionId' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $method = $data['payment_method'];
        $cycle = $data['billing_cycle'];
        $amount = $cycle === 'yearly' ? self::PRO_YEARLY_PRICE : self::PRO_MONTHLY_PRICE;
        $details = $data['payment_details'];

        if ($method === 'card' && filled($details['culqi_token'] ?? null)) {
            return $this->checkoutWithCulqi($user, $cycle, $amount, $data, $details);
        }

        return $this->checkoutDemo($user, $method, $cycle, $amount, $data, $details);
    }

    public function culqiWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = (string) (
            data_get($payload, 'type')
            ?? data_get($payload, 'event_type')
            ?? data_get($payload, 'event')
            ?? ''
        );
        $charge = data_get($payload, 'data.object')
            ?? data_get($payload, 'data')
            ?? data_get($payload, 'object')
            ?? [];

        if (! is_array($charge)) {
            $charge = [];
        }

        $chargeId = (string) (data_get($charge, 'id') ?? data_get($payload, 'id') ?? '');
        $localPaymentId = data_get($charge, 'metadata.local_payment_id')
            ?? data_get($payload, 'data.metadata.local_payment_id')
            ?? null;

        $payment = null;
        if ($localPaymentId) {
            $payment = SubscriptionPayment::query()->find($localPaymentId);
        }
        if (!$payment && $chargeId !== '') {
            $payment = SubscriptionPayment::query()->where('provider_reference', $chargeId)->first();
        }

        if (!$payment) {
            return $this->success('Webhook Culqi recibido sin pago local asociado.', [
                'event' => $eventType,
                'charge_id' => $chargeId ?: null,
            ]);
        }

        if (str_contains($eventType, 'failed')) {
            $payment->forceFill([
                'status' => 'failed',
                'metadata' => [
                    ...($payment->metadata ?? []),
                    'culqi_webhook' => $this->compactCulqiCharge($charge),
                    'webhook_event' => $eventType,
                ],
            ])->save();

            return $this->success('Webhook Culqi fallido registrado.', ['payment_id' => $payment->id]);
        }

        if (str_contains($eventType, 'succeeded') || $this->isSuccessfulCulqiCharge($charge)) {
            $this->activateSubscriptionFromPayment($payment, $charge, $eventType);

            return $this->success('Webhook Culqi exitoso procesado.', ['payment_id' => $payment->id]);
        }

        return $this->success('Webhook Culqi recibido.', [
            'payment_id' => $payment->id,
            'event' => $eventType,
        ]);
    }

    private function checkoutWithCulqi($user, string $cycle, float $amount, array $data, array $details): JsonResponse
    {
        $reference = 'STM-CULQI-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));

        $payment = SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'subscription_id' => null,
            'plan' => 'pro',
            'amount' => $amount,
            'currency' => 'PEN',
            'payment_method' => 'card',
            'provider' => 'culqi',
            'provider_reference' => $reference,
            'card_brand' => null,
            'card_last_four' => null,
            'status' => 'pending',
            'metadata' => [
                'billing_cycle' => $cycle,
                'save_payment_method' => (bool) ($data['save_payment_method'] ?? false),
                'provider' => 'culqi',
            ],
        ]);

        try {
            $chargePayload = [
                'amount' => (int) round($amount * 100),
                'currency_code' => 'PEN',
                'email' => $details['culqi_email'] ?? $user->email,
                'source_id' => $details['culqi_token'],
                'description' => 'Suscripción SkillPro ' . ($cycle === 'yearly' ? 'anual' : 'mensual'),
                'installments' => 0,
                'metadata' => [
                    'local_payment_id' => (string) $payment->id,
                    'local_reference' => $reference,
                    'user_id' => (string) $user->id,
                    'plan' => 'pro',
                    'billing_cycle' => $cycle,
                ],
            ];

            if (filled($details['device_finger_print_id'] ?? null)) {
                $chargePayload['antifraud_details'] = [
                    'first_name' => $user->first_name ?? $user->name,
                    'last_name' => $user->last_name ?? $user->name,
                    'email' => $details['culqi_email'] ?? $user->email,
                    'device_finger_print_id' => $details['device_finger_print_id'],
                ];
            }

            if (! empty($details['authentication_3ds']) && is_array($details['authentication_3ds'])) {
                $chargePayload['authentication_3DS'] = array_filter([
                    'eci' => data_get($details, 'authentication_3ds.eci'),
                    'xid' => data_get($details, 'authentication_3ds.xid'),
                    'cavv' => data_get($details, 'authentication_3ds.cavv'),
                    'protocolVersion' => data_get($details, 'authentication_3ds.protocolVersion'),
                    'directoryServerTransactionId' => data_get($details, 'authentication_3ds.directoryServerTransactionId'),
                ], static fn ($value) => filled($value));
            }

            $charge = app(CulqiService::class)->charge($chargePayload);
        } catch (\Throwable $exception) {
            $payment->forceFill([
                'status' => 'failed',
                'metadata' => [
                    ...($payment->metadata ?? []),
                    'error' => $exception->getMessage(),
                ],
            ])->save();

            return $this->error($exception->getMessage(), ['culqi' => [$exception->getMessage()]], 422);
        }

        if (!$this->isSuccessfulCulqiCharge($charge)) {
            $message = (string) (data_get($charge, 'user_message') ?? data_get($charge, 'merchant_message') ?? 'Culqi no aprobó el cargo.');
            $payment->forceFill([
                'status' => 'failed',
                'provider_reference' => data_get($charge, 'id') ?? $payment->provider_reference,
                'metadata' => [
                    ...($payment->metadata ?? []),
                    'culqi_charge' => $this->compactCulqiCharge($charge),
                    'message' => $message,
                ],
            ])->save();

            return $this->error($message, ['culqi' => [$message]], 422);
        }

        $payment = $this->activateSubscriptionFromPayment($payment, $charge, 'charge.creation.succeeded');

        return $this->success('Pago Culqi aprobado. Tu plan Pro está activo.', [
            ...$this->subscriptionPayload($user->fresh()),
            'payment' => $this->paymentPayload($payment),
        ], 201);
    }

    private function checkoutDemo($user, string $method, string $cycle, float $amount, array $data, array $details): JsonResponse
    {
        $card = null;

        if ($method === 'card') {
            $card = $this->validateDemoCard($details);

            if ($card['error'] !== null) {
                return $this->error($card['error'], ['payment' => [$card['error']]], 422);
            }
        }

        if (in_array($method, ['yape', 'plin'], true)) {
            $phone = preg_replace('/\D+/', '', (string) ($details['phone'] ?? ''));

            if (! preg_match('/^9\d{8}$/', $phone)) {
                return $this->error(
                    'Ingresa un celular peruano valido para simular el pago.',
                    ['phone' => ['El celular debe tener 9 digitos y empezar en 9.']],
                    422
                );
            }
        }

        $reference = 'STM-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));

        $result = DB::transaction(function () use ($user, $method, $card, $reference, $cycle, $amount, $data): array {
            Subscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'ends_at' => now(),
                ]);

            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'plan' => 'pro',
                'status' => 'active',
                'billing_cycle' => $cycle,
                'amount' => $amount,
                'currency' => 'PEN',
                'source' => 'skillpay_demo',
                'starts_at' => now(),
                'ends_at' => $cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);

            $payment = SubscriptionPayment::query()->create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan' => 'pro',
                'amount' => $amount,
                'currency' => 'PEN',
                'payment_method' => $method,
                'provider' => 'skillpay_demo',
                'provider_reference' => $reference,
                'card_brand' => $card['brand'] ?? null,
                'card_last_four' => $card['last_four'] ?? null,
                'status' => 'succeeded',
                'metadata' => [
                    'mode' => 'demo',
                    'billing_cycle' => $cycle,
                    'save_payment_method' => (bool) ($data['save_payment_method'] ?? false),
                    'message' => 'Pago simulado para activar el plan Pro.',
                ],
                'paid_at' => now(),
            ]);

            return [
                'subscription' => $subscription,
                'payment' => $payment,
            ];
        });

        return $this->success('Pago aprobado. Tu plan Pro está activo.', [
            ...$this->subscriptionPayload($user->fresh()),
            'payment' => $this->paymentPayload($result['payment']),
        ], 201);
    }

    private function activateSubscriptionFromPayment(SubscriptionPayment $payment, array $charge, string $eventType): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $charge, $eventType): SubscriptionPayment {
            $payment = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $cycle = (string) ($payment->metadata['billing_cycle'] ?? 'monthly');

            Subscription::query()
                ->where('user_id', $payment->user_id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'ends_at' => now(),
                ]);

            $subscription = $payment->subscription;
            if (!$subscription || $subscription->status !== 'active') {
                $subscription = Subscription::query()->create([
                    'user_id' => $payment->user_id,
                    'plan' => 'pro',
                    'status' => 'active',
                    'billing_cycle' => $cycle,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'source' => 'culqi',
                    'starts_at' => now(),
                    'ends_at' => $cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                ]);
            }

            $source = data_get($charge, 'source', []);
            $payment->forceFill([
                'subscription_id' => $subscription->id,
                'provider_reference' => data_get($charge, 'id') ?? $payment->provider_reference,
                'card_brand' => data_get($source, 'iin.card_brand') ?? $payment->card_brand,
                'card_last_four' => data_get($source, 'last_four') ?? $payment->card_last_four,
                'status' => 'succeeded',
                'metadata' => [
                    ...($payment->metadata ?? []),
                    'culqi_charge' => $this->compactCulqiCharge($charge),
                    'webhook_event' => $eventType,
                ],
                'paid_at' => $payment->paid_at ?? now(),
            ])->save();

            return $payment->fresh('subscription');
        });
    }

    private function isSuccessfulCulqiCharge(array $charge): bool
    {
        $object = data_get($charge, 'object');
        $state = strtolower((string) data_get($charge, 'state', ''));
        $responseCode = strtolower((string) data_get($charge, 'response_code', ''));

        return ($object === null || $object === 'charge')
            && (
                $state === 'exitosa'
                || $responseCode === 'venta_exitosa'
                || str_contains($responseCode, 'exitosa')
            );
    }

    private function compactCulqiCharge(array $charge): array
    {
        return [
            'id' => data_get($charge, 'id'),
            'state' => data_get($charge, 'state'),
            'response_code' => data_get($charge, 'response_code'),
            'merchant_message' => data_get($charge, 'merchant_message'),
            'user_message' => data_get($charge, 'user_message'),
            'amount' => data_get($charge, 'amount'),
            'currency' => data_get($charge, 'currency') ?? data_get($charge, 'currency_code'),
            'source_last_four' => data_get($charge, 'source.last_four'),
            'source_brand' => data_get($charge, 'source.iin.card_brand'),
        ];
    }

    private function validateDemoCard(array $details): array
    {
        $number = preg_replace('/\D+/', '', (string) ($details['card_number'] ?? ''));
        $holder = trim((string) ($details['card_holder'] ?? ''));
        $cvv = preg_replace('/\D+/', '', (string) ($details['cvv'] ?? ''));
        $month = (int) ($details['expiry_month'] ?? 0);
        $year = (int) ($details['expiry_year'] ?? 0);

        if (strlen($number) < 13 || strlen($number) > 19) {
            return ['error' => 'Ingresa un numero de tarjeta valido para la demo.'];
        }

        if ($holder === '') {
            return ['error' => 'Ingresa el nombre del titular de la tarjeta.'];
        }

        if (! preg_match('/^\d{3}$/', $cvv)) {
            return ['error' => 'Ingresa un CVV valido.'];
        }

        if (! $this->isAcceptedDemoCard($number)) {
            return ['error' => 'Usa una tarjeta de prueba valida para la demo. Ejemplo: 4242 4242 4242 4242.'];
        }

        if ($year < 100) {
            $year += 2000;
        }

        if ($month < 1 || $month > 12 || $year < (int) now()->format('Y')) {
            return ['error' => 'Ingresa una fecha de vencimiento valida.'];
        }

        if ($year === (int) now()->format('Y') && $month < (int) now()->format('n')) {
            return ['error' => 'La tarjeta esta vencida.'];
        }

        return [
            'error' => null,
            'brand' => $this->cardBrand($number),
            'last_four' => substr($number, -4),
        ];
    }

    private function cardBrand(string $number): string
    {
        return match (true) {
            str_starts_with($number, '4') => 'Visa',
            preg_match('/^5[1-5]/', $number) === 1 => 'Mastercard',
            preg_match('/^3[47]/', $number) === 1 => 'American Express',
            default => 'Tarjeta',
        };
    }

    private function isAcceptedDemoCard(string $number): bool
    {
        $testCards = [
            '4242424242424242',
            '4000056655665556',
            '5555555555554444',
            '2223003122003222',
            '378282246310005',
            '4747474747474747',
        ];

        return in_array($number, $testCards, true) || $this->passesLuhn($number);
    }

    private function passesLuhn(string $number): bool
    {
        $sum = 0;
        $alternate = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($alternate) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum > 0 && $sum % 10 === 0;
    }

    private function subscriptionPayload($user): array
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('subscription_payments')) {
            return $this->freePayload($user);
        }

        $subscription = $user->activeSubscription()->first();
        $lastPayment = $user->subscriptionPayments()->latest()->first();
        $isPro = $subscription?->plan === 'pro';

        return [
            'plan' => $isPro ? 'pro' : 'free',
            'status' => $isPro ? $subscription->status : 'free',
            'billing_cycle' => $isPro ? $subscription->billing_cycle : null,
            'amount' => $isPro ? (float) $subscription->amount : 0,
            'currency' => 'PEN',
            'starts_at' => $subscription?->starts_at?->toISOString(),
            'ends_at' => $subscription?->ends_at?->toISOString(),
            'features' => $this->featuresFor($user->user_type, $isPro ? 'pro' : 'free'),
            'last_payment' => $lastPayment ? $this->paymentPayload($lastPayment) : null,
        ];
    }

    private function paymentPayload(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'reference' => $payment->provider_reference,
            'plan' => $payment->plan,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'payment_method' => $payment->payment_method,
            'card_brand' => $payment->card_brand,
            'card_last_four' => $payment->card_last_four,
            'status' => $payment->status,
            'saved_for_renewal' => (bool) ($payment->metadata['save_payment_method'] ?? false),
            'paid_at' => $payment->paid_at?->toISOString(),
        ];
    }

    private function freePayload($user): array
    {
        return [
            'plan' => 'free',
            'status' => 'free',
            'billing_cycle' => null,
            'amount' => 0,
            'currency' => 'PEN',
            'starts_at' => null,
            'ends_at' => null,
            'features' => $this->featuresFor($user->user_type, 'free'),
            'last_payment' => null,
        ];
    }

    private function featuresFor(string $userType, string $plan): array
    {
        if ($userType === 'mype') {
            return $plan === 'pro'
                ? ['Publicaciones ilimitadas', 'Mayor visibilidad', 'Recomendaciones avanzadas', 'Soporte prioritario']
                : ['1 publicación activa', 'Buscar freelancers', 'Guardar favoritos', 'Explorar servicios'];
        }

        return $plan === 'pro'
            ? ['Servicios ilimitados', 'Más visibilidad', 'Skill Bot ampliado', 'Mejoras de perfil']
            : ['Perfil freelancer', 'Portafolio básico', '1 servicio recomendado', 'Visibilidad estándar'];
    }
}
