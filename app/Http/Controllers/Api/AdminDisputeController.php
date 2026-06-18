<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDisputeController extends Controller
{
    use ApiResponse;

    public function resolve(Request $request, Dispute $dispute): JsonResponse
    {
        if (!$this->isAdmin($request->user())) {
            return $this->error('Solo un administrador puede resolver disputas.', status: 403);
        }

        $data = $request->validate([
            'resolution' => ['required', 'in:release,refund,request_revision'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $contract = DB::transaction(function () use ($dispute, $request, $data): Contract {
            $contract = $dispute->contract()->with(['escrow', 'freelancerProfile.user'])->lockForUpdate()->firstOrFail();
            $escrow = $contract->escrow;

            if ($data['resolution'] === 'release') {
                if ($escrow && $escrow->status !== 'released') {
                    $wallet = Wallet::query()->lockForUpdate()->firstOrCreate(
                        ['user_id' => $contract->freelancerProfile->user_id],
                        ['currency' => $contract->currency]
                    );
                    $newBalance = (float) $wallet->available_balance + (float) $escrow->amount;
                    $wallet->forceFill(['available_balance' => $newBalance])->save();
                    $wallet->transactions()->create([
                        'contract_id' => $contract->id,
                        'type' => 'escrow_release',
                        'direction' => 'credit',
                        'amount' => $escrow->amount,
                        'currency' => $contract->currency,
                        'available_after' => $newBalance,
                        'description' => 'Liberación de escrow por resolución administrativa.',
                    ]);
                    $escrow->forceFill(['status' => 'released', 'released_at' => now()])->save();
                }

                $contract->deliveryFiles()->update(['downloadable' => true]);
                $contract->forceFill([
                    'status' => Contract::STATUS_RELEASED,
                    'approved_at' => $contract->approved_at ?? now(),
                    'released_at' => now(),
                ])->save();
            } elseif ($data['resolution'] === 'refund') {
                $escrow?->forceFill(['status' => 'refunded', 'refunded_at' => now()])->save();
                $contract->forceFill(['status' => Contract::STATUS_CANCELLED, 'cancelled_at' => now()])->save();
            } else {
                $escrow?->forceFill(['status' => 'held'])->save();
                $contract->forceFill(['status' => Contract::STATUS_REVISION_REQUESTED])->save();
            }

            $dispute->forceFill([
                'status' => 'resolved',
                'resolution' => $data['resolution'],
                'admin_user_id' => $request->user()->id,
                'admin_comment' => $data['comment'] ?? null,
                'resolved_at' => now(),
            ])->save();

            return $contract->fresh([
                'mypeProfile.user',
                'freelancerProfile.user',
                'escrow',
                'deliveries.files',
                'disputes',
            ]);
        });

        return $this->success('Disputa resuelta.', [
            'contract_id' => $contract->id,
            'status' => $contract->status,
        ]);
    }

    private function isAdmin(User $user): bool
    {
        $adminEmails = collect(explode(',', (string) config('app.admin_emails', env('APP_ADMIN_EMAILS', ''))))
            ->map(fn (string $email) => strtolower(trim($email)))
            ->filter();

        return $user->user_type === 'admin' || $adminEmails->contains(strtolower($user->email));
    }
}
