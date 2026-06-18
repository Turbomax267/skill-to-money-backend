<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Escrow;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        if (!$this->walletTablesReady()) {
            return $this->success('Wallet pendiente de configurar. Ejecuta las migraciones para activar escrow.', [
                'wallet' => $this->emptyWallet(),
            ]);
        }

        $wallet = $this->walletFor($request->user());
        $pendingEscrow = 0.0;

        if ($request->user()->freelancerProfile) {
            $pendingEscrow = (float) Escrow::query()
                ->where('status', 'held')
                ->whereHas('contract', fn ($query) => $query->where('freelancer_profile_id', $request->user()->freelancerProfile->id))
                ->sum('amount');
        } elseif ($request->user()->mypeProfile) {
            $pendingEscrow = (float) Escrow::query()
                ->whereIn('status', ['held', 'disputed'])
                ->whereHas('contract', fn ($query) => $query->where('mype_profile_id', $request->user()->mypeProfile->id))
                ->sum('amount');
        }

        return $this->success('Wallet encontrada.', [
            'wallet' => $this->formatWallet($wallet, $pendingEscrow),
        ]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        if (!$this->walletTablesReady()) {
            return $this->error('La wallet aun no esta configurada. Ejecuta las migraciones en la base de datos.', status: 503);
        }

        $user = $request->user();

        if (!$user->freelancerProfile) {
            return $this->error('Solo un freelancer puede solicitar retiros.', status: 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:50'],
            'method' => ['nullable', 'string', 'max:50'],
            'destination' => ['nullable', 'string', 'max:120'],
        ]);

        $wallet = DB::transaction(function () use ($user, $data): Wallet {
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'PEN']
            );

            if ((float) $wallet->available_balance < (float) $data['amount']) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'No tienes saldo disponible suficiente para retirar.',
                    'data' => null,
                    'errors' => ['amount' => ['No tienes saldo disponible suficiente.']],
                ], 422));
            }

            $newBalance = (float) $wallet->available_balance - (float) $data['amount'];
            $wallet->forceFill(['available_balance' => $newBalance])->save();

            $withdrawal = $wallet->withdrawalRequests()->create([
                'user_id' => $user->id,
                'amount' => $data['amount'],
                'currency' => $wallet->currency,
                'provider' => 'mock',
                'status' => 'paid',
                'provider_reference' => 'wd_mock_' . Str::uuid(),
                'requested_at' => now(),
                'processed_at' => now(),
                'metadata' => [
                    'method' => $data['method'] ?? 'mock',
                    'destination' => $data['destination'] ?? null,
                    'message' => 'Retiro simulado para MVP.',
                ],
            ]);

            $wallet->transactions()->create([
                'type' => 'withdrawal_paid',
                'direction' => 'debit',
                'amount' => $withdrawal->amount,
                'currency' => $wallet->currency,
                'available_after' => $newBalance,
                'description' => 'Retiro mock procesado.',
                'metadata' => ['withdrawal_request_id' => $withdrawal->id],
            ]);

            return $wallet->fresh(['transactions', 'withdrawalRequests']);
        });

        return $this->success('Retiro mock procesado.', [
            'wallet' => $this->formatWallet($wallet, 0),
        ], 201);
    }

    private function walletFor(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => 'PEN']
        )->load(['transactions' => fn ($query) => $query->latest()->limit(20), 'withdrawalRequests' => fn ($query) => $query->latest()->limit(10)]);
    }

    private function walletTablesReady(): bool
    {
        return Schema::hasTable('wallets')
            && Schema::hasTable('wallet_transactions')
            && Schema::hasTable('withdrawal_requests')
            && Schema::hasTable('escrows');
    }

    private function emptyWallet(): array
    {
        return [
            'id' => null,
            'available_balance' => 0.0,
            'pending_balance' => 0.0,
            'escrow_balance' => 0.0,
            'currency' => 'PEN',
            'transactions' => [],
            'withdrawals' => [],
        ];
    }

    private function formatWallet(Wallet $wallet, float $pendingEscrow): array
    {
        return [
            'id' => $wallet->id,
            'available_balance' => (float) $wallet->available_balance,
            'pending_balance' => (float) $wallet->pending_balance,
            'escrow_balance' => $pendingEscrow,
            'currency' => $wallet->currency,
            'transactions' => $wallet->transactions->map(fn ($transaction) => [
                'id' => $transaction->id,
                'contract_id' => $transaction->contract_id,
                'type' => $transaction->type,
                'direction' => $transaction->direction,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'available_after' => (float) $transaction->available_after,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
            ]),
            'withdrawals' => $wallet->withdrawalRequests->map(fn ($withdrawal) => [
                'id' => $withdrawal->id,
                'amount' => (float) $withdrawal->amount,
                'currency' => $withdrawal->currency,
                'provider' => $withdrawal->provider,
                'status' => $withdrawal->status,
                'requested_at' => $withdrawal->requested_at,
                'processed_at' => $withdrawal->processed_at,
            ]),
        ];
    }
}
