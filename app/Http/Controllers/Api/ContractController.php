<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Contract;
use App\Models\Delivery;
use App\Models\DeliveryFile;
use App\Models\Dispute;
use App\Models\FreelancerProfile;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        if (!$this->contractTablesReady()) {
            return $this->success('Contratos pendientes de configurar. Ejecuta las migraciones para activar escrow.', [
                'contracts' => [],
            ]);
        }

        $user = $request->user();
        $query = Contract::with($this->contractRelations())->latest();

        if ($user->mypeProfile) {
            $query->where('mype_profile_id', $user->mypeProfile->id);
        } elseif ($user->freelancerProfile) {
            $query->where('freelancer_profile_id', $user->freelancerProfile->id);
        } elseif (!$this->isAdmin($user)) {
            return $this->error('No tienes un perfil habilitado para contratos.', status: 403);
        }

        return $this->success('Contratos encontrados.', [
            'contracts' => $query->get()->map(fn (Contract $contract) => $this->formatContract($contract)),
        ]);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        if (!$this->canView($request->user(), $contract)) {
            return $this->error('No tienes acceso a este contrato.', status: 403);
        }

        return $this->success('Contrato encontrado.', $this->formatContract($contract->load($this->contractRelations())));
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->contractTablesReady()) {
            return $this->error('Los contratos aun no estan configurados. Ejecuta las migraciones en la base de datos.', status: 503);
        }

        $user = $request->user();
        $mype = $user->mypeProfile;

        if (!$mype) {
            return $this->error('Solo una MYPE puede crear contratos.', status: 403);
        }

        $data = $request->validate([
            'freelancer_profile_id' => ['required', 'integer', 'exists:freelancer_profiles,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'terms' => ['nullable', 'array'],
        ]);

        $service = isset($data['service_id']) ? Service::find($data['service_id']) : null;
        if ($service && (int) $service->freelancer_profile_id !== (int) $data['freelancer_profile_id']) {
            return $this->error('El servicio no pertenece al freelancer seleccionado.', [
                'service_id' => ['El servicio no pertenece al freelancer seleccionado.'],
            ], 422);
        }

        $contract = Contract::create([
            'contract_number' => 'STM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'mype_profile_id' => $mype->id,
            'freelancer_profile_id' => $data['freelancer_profile_id'],
            'service_id' => $data['service_id'] ?? null,
            'client_project_id' => $data['client_project_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency'] ?? 'PEN'),
            'status' => Contract::STATUS_PENDING_PAYMENT,
            'provider' => 'mock',
            'terms' => $data['terms'] ?? null,
        ]);

        return $this->success('Contrato creado. Falta confirmar el pago mock.', $this->formatContract($contract->load($this->contractRelations())), 201);
    }

    public function mockPay(Request $request, Contract $contract): JsonResponse
    {
        if (!$this->isMypeOwner($request->user(), $contract)) {
            return $this->error('Solo la MYPE dueña puede pagar este contrato.', status: 403);
        }

        if ($contract->status !== Contract::STATUS_PENDING_PAYMENT) {
            return $this->error('Este contrato ya no está pendiente de pago.', status: 422);
        }

        $contract = DB::transaction(function () use ($contract, $request): Contract {
            $payment = $contract->payments()->create([
                'payer_user_id' => $request->user()->id,
                'provider' => 'mock',
                'provider_reference' => 'mock_' . Str::uuid(),
                'amount' => $contract->amount,
                'currency' => $contract->currency,
                'status' => 'paid',
                'paid_at' => now(),
                'metadata' => ['mode' => 'sandbox', 'message' => 'Pago simulado para MVP.'],
            ]);

            $contract->escrow()->create([
                'payment_id' => $payment->id,
                'amount' => $contract->amount,
                'currency' => $contract->currency,
                'status' => 'held',
                'held_at' => now(),
                'metadata' => ['provider' => 'mock'],
            ]);

            $contract->forceFill([
                'status' => Contract::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ])->save();

            return $contract->fresh($this->contractRelations());
        });

        return $this->success('Pago mock confirmado. El dinero quedó protegido en escrow.', $this->formatContract($contract));
    }

    public function deliver(Request $request, Contract $contract): JsonResponse
    {
        $user = $request->user();

        if (!$this->isFreelancerOwner($user, $contract)) {
            return $this->error('Solo el freelancer asignado puede entregar este contrato.', status: 403);
        }

        if (!in_array($contract->status, [
            Contract::STATUS_IN_ESCROW,
            Contract::STATUS_IN_PROGRESS,
            Contract::STATUS_REVISION_REQUESTED,
            Contract::STATUS_SUBMITTED_FOR_REVIEW,
        ], true)) {
            return $this->error('El contrato no está listo para recibir entregas.', status: 422);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:2000'],
            'preview_files' => ['nullable', 'array', 'max:5'],
            'preview_files.*' => ['file', 'max:10240'],
            'final_files' => ['nullable', 'array', 'max:5'],
            'final_files.*' => ['file', 'max:20480'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:10240'],
        ]);

        $previewFiles = array_merge($request->file('preview_files', []), $request->file('files', []));
        $finalFiles = $request->file('final_files', []);

        if (count($previewFiles) === 0 && count($finalFiles) === 0 && blank($data['message'] ?? null)) {
            return $this->error('Agrega un comentario o al menos un archivo para entregar.', [
                'delivery' => ['Agrega un comentario o al menos un archivo.'],
            ], 422);
        }

        $contract = DB::transaction(function () use ($contract, $user, $data, $previewFiles, $finalFiles): Contract {
            $round = ((int) $contract->deliveries()->max('revision_round')) + 1;
            $delivery = $contract->deliveries()->create([
                'freelancer_profile_id' => $user->freelancerProfile->id,
                'title' => $data['title'] ?? 'Entrega de revisión',
                'message' => $data['message'] ?? null,
                'status' => Contract::STATUS_SUBMITTED_FOR_REVIEW,
                'revision_round' => max(1, $round),
                'submitted_at' => now(),
            ]);

            foreach ($previewFiles as $file) {
                $this->storeDeliveryFile($delivery, $file, true, false);
            }

            foreach ($finalFiles as $file) {
                $this->storeDeliveryFile($delivery, $file, false, true);
            }

            $contract->forceFill([
                'status' => Contract::STATUS_SUBMITTED_FOR_REVIEW,
                'submitted_at' => now(),
            ])->save();

            return $contract->fresh($this->contractRelations());
        });

        return $this->success('Entrega enviada para revisión.', $this->formatContract($contract));
    }

    public function deliveries(Request $request, Contract $contract): JsonResponse
    {
        if (!$this->canView($request->user(), $contract)) {
            return $this->error('No tienes acceso a estas entregas.', status: 403);
        }

        return $this->success('Entregas encontradas.', [
            'deliveries' => $contract->deliveries()
                ->with('files')
                ->latest()
                ->get()
                ->map(fn (Delivery $delivery) => $this->formatDelivery($delivery, $contract)),
        ]);
    }

    public function requestRevision(Request $request, Contract $contract): JsonResponse
    {
        if (!$this->isMypeOwner($request->user(), $contract)) {
            return $this->error('Solo la MYPE dueña puede pedir cambios.', status: 403);
        }

        $data = $request->validate([
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        if ($contract->status !== Contract::STATUS_SUBMITTED_FOR_REVIEW) {
            return $this->error('Solo puedes pedir cambios cuando hay una entrega en revisión.', status: 422);
        }

        $contract = DB::transaction(function () use ($contract, $data): Contract {
            $delivery = $contract->deliveries()->latest()->first();
            $delivery?->forceFill([
                'status' => Contract::STATUS_REVISION_REQUESTED,
                'reviewed_at' => now(),
                'review_comment' => $data['comment'],
            ])->save();

            $contract->forceFill(['status' => Contract::STATUS_REVISION_REQUESTED])->save();

            return $contract->fresh($this->contractRelations());
        });

        return $this->success('Cambios solicitados al freelancer.', $this->formatContract($contract));
    }

    public function approve(Request $request, Contract $contract): JsonResponse
    {
        if (!$this->isMypeOwner($request->user(), $contract)) {
            return $this->error('Solo la MYPE dueña puede aprobar este contrato.', status: 403);
        }

        if ($contract->status !== Contract::STATUS_SUBMITTED_FOR_REVIEW) {
            return $this->error('Solo puedes aprobar una entrega enviada para revisión.', status: 422);
        }

        $contract = DB::transaction(function () use ($contract): Contract {
            $escrow = $contract->escrow()->lockForUpdate()->first();
            if (!$escrow || $escrow->status !== 'held') {
                abort(response()->json([
                    'success' => false,
                    'message' => 'No hay fondos retenidos en escrow para liberar.',
                    'data' => null,
                    'errors' => ['escrow' => ['No hay fondos retenidos en escrow para liberar.']],
                ], 422));
            }

            $freelancerUser = $contract->freelancerProfile->user;
            $wallet = Wallet::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $freelancerUser->id],
                ['currency' => $contract->currency]
            );
            $newBalance = (float) $wallet->available_balance + (float) $escrow->amount;

            $wallet->forceFill([
                'available_balance' => $newBalance,
                'currency' => $contract->currency,
            ])->save();

            $wallet->transactions()->create([
                'contract_id' => $contract->id,
                'type' => 'escrow_release',
                'direction' => 'credit',
                'amount' => $escrow->amount,
                'currency' => $contract->currency,
                'available_after' => $newBalance,
                'description' => 'Liberación de escrow por contrato aprobado.',
            ]);

            $escrow->forceFill([
                'status' => 'released',
                'released_at' => now(),
            ])->save();

            $contract->deliveryFiles()->update(['downloadable' => true]);

            $contract->forceFill([
                'status' => Contract::STATUS_APPROVED,
                'approved_at' => now(),
                'released_at' => now(),
            ])->save();

            return $contract->fresh($this->contractRelations());
        });

        return $this->success('Entrega aprobada. El escrow fue liberado al freelancer.', $this->formatContract($contract));
    }

    public function dispute(Request $request, Contract $contract): JsonResponse
    {
        if (!$this->canView($request->user(), $contract)) {
            return $this->error('No tienes acceso a este contrato.', status: 403);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if (in_array($contract->status, [Contract::STATUS_RELEASED, Contract::STATUS_CANCELLED], true)) {
            return $this->error('Este contrato ya no puede abrir disputa.', status: 422);
        }

        $contract = DB::transaction(function () use ($contract, $request, $data): Contract {
            $contract->disputes()->create([
                'opened_by_user_id' => $request->user()->id,
                'status' => 'open',
                'reason' => $data['reason'],
            ]);

            $contract->escrow?->forceFill(['status' => 'disputed'])->save();
            $contract->forceFill(['status' => Contract::STATUS_DISPUTED])->save();

            return $contract->fresh($this->contractRelations());
        });

        return $this->success('Disputa abierta. Un administrador deberá resolverla.', $this->formatContract($contract));
    }

    public function downloadFile(Request $request, Contract $contract, DeliveryFile $deliveryFile): StreamedResponse|JsonResponse
    {
        if (!$this->canView($request->user(), $contract) || (int) $deliveryFile->delivery->contract_id !== (int) $contract->id) {
            return $this->error('No tienes acceso a este archivo.', status: 403);
        }

        $canDownload = $deliveryFile->is_preview
            || $deliveryFile->downloadable
            || in_array($contract->status, [Contract::STATUS_APPROVED, Contract::STATUS_RELEASED], true);

        if (!$canDownload) {
            return $this->error('El archivo final se desbloquea cuando la MYPE aprueba la entrega.', [
                'file' => ['Archivo final bloqueado por escrow.'],
            ], 403);
        }

        if (!Storage::disk('local')->exists($deliveryFile->file_path)) {
            return $this->error('Archivo no encontrado.', status: 404);
        }

        return Storage::disk('local')->download($deliveryFile->file_path, $deliveryFile->original_name);
    }

    private function storeDeliveryFile(Delivery $delivery, mixed $file, bool $isPreview, bool $isFinal): DeliveryFile
    {
        $path = $file->store("contracts/{$delivery->contract_id}/deliveries/{$delivery->id}", 'local');

        return $delivery->files()->create([
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'is_preview' => $isPreview,
            'is_final' => $isFinal,
            'downloadable' => false,
            'watermark_text' => $isPreview ? 'Vista previa Skill to Money' : null,
        ]);
    }

    private function contractRelations(): array
    {
        return [
            'mypeProfile.user',
            'freelancerProfile.user',
            'freelancerProfile.skills',
            'service.category',
            'clientProject',
            'payments',
            'escrow',
            'deliveries.files',
            'disputes',
        ];
    }

    private function formatContract(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
            'description' => $contract->description,
            'amount' => (float) $contract->amount,
            'currency' => $contract->currency,
            'status' => $contract->status,
            'provider' => $contract->provider,
            'terms' => $contract->terms,
            'mype' => [
                'id' => $contract->mypeProfile?->id,
                'name' => $contract->mypeProfile?->business_name ?? $contract->mypeProfile?->user?->name,
                'user_id' => $contract->mypeProfile?->user_id,
            ],
            'freelancer' => [
                'id' => $contract->freelancerProfile?->id,
                'user_id' => $contract->freelancerProfile?->user_id,
                'name' => $contract->freelancerProfile?->user?->name,
                'headline' => $contract->freelancerProfile?->headline,
                'skills' => $contract->freelancerProfile?->skills?->pluck('name')->values() ?? [],
            ],
            'service' => $contract->service ? [
                'id' => $contract->service->id,
                'title' => $contract->service->title,
                'category' => $contract->service->category?->name,
            ] : null,
            'client_project' => $contract->clientProject ? [
                'id' => $contract->clientProject->id,
                'title' => $contract->clientProject->title,
                'category' => $contract->clientProject->category,
            ] : null,
            'payment' => $contract->payments->sortByDesc('created_at')->first() ? [
                'id' => $contract->payments->sortByDesc('created_at')->first()->id,
                'status' => $contract->payments->sortByDesc('created_at')->first()->status,
                'provider' => $contract->payments->sortByDesc('created_at')->first()->provider,
                'paid_at' => $contract->payments->sortByDesc('created_at')->first()->paid_at,
            ] : null,
            'escrow' => $contract->escrow ? [
                'id' => $contract->escrow->id,
                'status' => $contract->escrow->status,
                'amount' => (float) $contract->escrow->amount,
                'currency' => $contract->escrow->currency,
                'held_at' => $contract->escrow->held_at,
                'released_at' => $contract->escrow->released_at,
                'refunded_at' => $contract->escrow->refunded_at,
            ] : null,
            'deliveries' => $contract->deliveries->sortByDesc('created_at')->values()->map(fn (Delivery $delivery) => $this->formatDelivery($delivery, $contract)),
            'disputes' => $contract->disputes->sortByDesc('created_at')->values()->map(fn (Dispute $dispute) => [
                'id' => $dispute->id,
                'status' => $dispute->status,
                'reason' => $dispute->reason,
                'resolution' => $dispute->resolution,
                'admin_comment' => $dispute->admin_comment,
                'resolved_at' => $dispute->resolved_at,
                'created_at' => $dispute->created_at,
            ]),
            'created_at' => $contract->created_at,
            'updated_at' => $contract->updated_at,
            'started_at' => $contract->started_at,
            'submitted_at' => $contract->submitted_at,
            'approved_at' => $contract->approved_at,
            'released_at' => $contract->released_at,
        ];
    }

    private function formatDelivery(Delivery $delivery, Contract $contract): array
    {
        return [
            'id' => $delivery->id,
            'title' => $delivery->title,
            'message' => $delivery->message,
            'status' => $delivery->status,
            'revision_round' => $delivery->revision_round,
            'submitted_at' => $delivery->submitted_at,
            'reviewed_at' => $delivery->reviewed_at,
            'review_comment' => $delivery->review_comment,
            'files' => $delivery->files->map(fn (DeliveryFile $file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'is_preview' => $file->is_preview,
                'is_final' => $file->is_final,
                'downloadable' => $file->downloadable || in_array($contract->status, [Contract::STATUS_APPROVED, Contract::STATUS_RELEASED], true),
                'watermark_text' => $file->watermark_text,
                'download_url' => "/api/contracts/{$contract->id}/files/{$file->id}/download",
            ]),
            'created_at' => $delivery->created_at,
        ];
    }

    private function isMypeOwner(User $user, Contract $contract): bool
    {
        return $user->mypeProfile && (int) $user->mypeProfile->id === (int) $contract->mype_profile_id;
    }

    private function isFreelancerOwner(User $user, Contract $contract): bool
    {
        return $user->freelancerProfile && (int) $user->freelancerProfile->id === (int) $contract->freelancer_profile_id;
    }

    private function canView(User $user, Contract $contract): bool
    {
        return $this->isMypeOwner($user, $contract)
            || $this->isFreelancerOwner($user, $contract)
            || $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        $adminEmails = collect(explode(',', (string) config('app.admin_emails', env('APP_ADMIN_EMAILS', ''))))
            ->map(fn (string $email) => strtolower(trim($email)))
            ->filter();

        return $user->user_type === 'admin' || $adminEmails->contains(strtolower($user->email));
    }

    private function contractTablesReady(): bool
    {
        return Schema::hasTable('contracts')
            && Schema::hasTable('payments')
            && Schema::hasTable('escrows')
            && Schema::hasTable('deliveries')
            && Schema::hasTable('delivery_files')
            && Schema::hasTable('disputes');
    }
}
