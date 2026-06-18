<?php

namespace App\Http\Controllers\Api;

use App\Events\ConversationCreated;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\CreateConversationRequest;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagingController extends Controller
{
    use ApiResponse;

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getProfile($user);

        if (!$profile) {
            return $this->error('Completa tu perfil para usar mensajería.', null, 400);
        }

        $profileType = $user->user_type === 'mype' ? 'mype' : 'freelancer';
        $profileId = $profile->id;

        $conversations = Conversation::where("{$profileType}_profile_id", $profileId)
            ->with([
                'messages' => fn($q) => $q->latest()->limit(1),
            ])
            ->orderBy('last_message_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(Conversation $conv) => $this->formatConversation($conv, $user));

        return $this->success('Conversaciones obtenidas.', ['conversations' => $conversations]);
    }

    public function createConversation(CreateConversationRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getProfile($user);

        if (!$profile) {
            return $this->error('Completa tu perfil para usar mensajería.', null, 400);
        }

        $isMype = $user->user_type === 'mype';
        $mypeProfileId = $isMype ? $profile->id : $request->input('mype_profile_id');
        $freelancerProfileId = $isMype ? $request->input('freelancer_profile_id') : $profile->id;

        if ($isMype && !$freelancerProfileId) {
            return $this->error('Debes especificar el freelancer.', null, 422);
        }

        if (!$isMype && !$mypeProfileId) {
            return $this->error('Debes especificar la MYPE.', null, 422);
        }

        $existing = Conversation::where('mype_profile_id', $mypeProfileId)
            ->where('freelancer_profile_id', $freelancerProfileId)
            ->first();

        if ($existing) {
            $message = $this->createMessage($existing->id, $user->id, $request->input('message'));
            $existing->touchLastMessage();
            $existing->refresh();

            $this->createNotification($existing, $user);

            broadcast(new MessageSent($existing, $message));

            return $this->success('Mensaje enviado.', [
                'conversation' => $this->formatConversation($existing, $user),
                'message' => $this->formatMessage($message, $user),
            ]);
        }

        $conversation = Conversation::create([
            'mype_profile_id' => $mypeProfileId,
            'freelancer_profile_id' => $freelancerProfileId,
            'service_id' => $request->input('service_id'),
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        $message = $this->createMessage($conversation->id, $user->id, $request->input('message'));

        $this->createNotification($conversation, $user);

        broadcast(new MessageSent($conversation, $message));

        $recipient = $this->getRecipientUser($conversation, $user);
        if ($recipient) {
            broadcast(new ConversationCreated($conversation, $recipient->id));
        }

        return $this->success('Conversacion creada.', [
            'conversation' => $this->formatConversation($conversation, $user),
            'message' => $this->formatMessage($message, $user),
        ], 201);
    }

    public function showConversation(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getProfile($user);

        if (!$profile) {
            return $this->error('Completa tu perfil.', null, 400);
        }

        $conversation = $this->findConversation($id, $profile, $user);

        if (!$conversation) {
            return $this->error('Conversacion no encontrada.', null, 404);
        }

        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn(Message $msg) => $this->formatMessage($msg, $user));

        $this->markMessagesAsRead($conversation, $user);

        return $this->success('Conversacion obtenida.', [
            'conversation' => $this->formatConversation($conversation, $user),
            'messages' => $messages,
        ]);
    }

    public function sendMessage(SendMessageRequest $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getProfile($user);

        if (!$profile) {
            return $this->error('Completa tu perfil.', null, 400);
        }

        $conversation = $this->findConversation($conversationId, $profile, $user);

        if (!$conversation) {
            return $this->error('Conversacion no encontrada.', null, 404);
        }

        if ($conversation->status !== 'active') {
            return $this->error('Esta conversacion esta cerrada.', null, 400);
        }

        $message = $this->createMessage($conversation->id, $user->id, $request->input('message'));
        $conversation->touchLastMessage();

        $this->createNotification($conversation, $user);

        broadcast(new MessageSent($conversation, $message));

        return $this->success('Mensaje enviado.', [
            'message' => $this->formatMessage($message, $user),
        ], 201);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getProfile($user);

        if (!$profile) {
            return $this->error('Completa tu perfil.', null, 400);
        }

        $conversation = $this->findConversation($id, $profile, $user);

        if (!$conversation) {
            return $this->error('Conversacion no encontrada.', null, 404);
        }

        $this->markMessagesAsRead($conversation, $user);

        return $this->success('Mensajes marcados como leídos.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->getProfile($user);

        if (!$profile) {
            return $this->success('Sin notificaciones.', ['total' => 0]);
        }

        $profileType = $user->user_type === 'mype' ? 'mype' : 'freelancer';
        $profileId = $profile->id;

        $unreadConversations = Conversation::where("{$profileType}_profile_id", $profileId)
            ->whereHas('messages', function ($q) use ($user) {
                $q->where('sender_user_id', '!=', $user->id)
                    ->whereNull('read_at');
            })
            ->count();

        $unreadNotifications = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->success('Contador de no leídos.', [
            'messages' => $unreadConversations,
            'notifications' => $unreadNotifications,
            'total' => $unreadConversations + $unreadNotifications,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function (Notification $n) {
                return [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'message' => $n->message,
                    'data' => $n->data,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ];
            });

        return $this->success('Notificaciones obtenidas.', ['notifications' => $notifications]);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->error('Notificacion no encontrada.', null, 404);
        }

        $notification->update(['read_at' => now()]);

        return $this->success('Notificacion marcada como leida.');
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success('Todas las notificaciones marcadas como leidas.');
    }

    private function getProfile(User $user): \App\Models\FreelancerProfile|\App\Models\MypeProfile|null
    {
        if ($user->user_type === 'mype') {
            return $user->mypeProfile;
        }

        if ($user->user_type === 'freelancer') {
            return $user->freelancerProfile;
        }

        return null;
    }

    private function findConversation(int $id, \App\Models\FreelancerProfile|\App\Models\MypeProfile $profile, User $user): ?Conversation
    {
        $profileType = $user->user_type === 'mype' ? 'mype' : 'freelancer';
        $profileId = $profile->id;

        return Conversation::where('id', $id)
            ->where("{$profileType}_profile_id", $profileId)
            ->first();
    }

    private function createMessage(int $conversationId, int $senderUserId, string $messageText): Message
    {
        return Message::create([
            'conversation_id' => $conversationId,
            'sender_user_id' => $senderUserId,
            'message' => $messageText,
        ]);
    }

    private function createNotification(Conversation $conversation, User $sender): void
    {
        $recipientUser = $this->getRecipientUser($conversation, $sender);

        if (!$recipientUser) {
            return;
        }

        Notification::create([
            'user_id' => $recipientUser->id,
            'type' => 'new_message',
            'title' => 'Nuevo mensaje',
            'message' => substr($sender->name . ': ' . request()->input('message', ''), 0, 200),
            'data' => [
                'conversation_id' => $conversation->id,
                'sender_name' => $sender->name,
            ],
        ]);
    }

    private function getRecipientUser(Conversation $conversation, User $sender): ?User
    {
        if ($sender->user_type === 'mype') {
            return $conversation->freelancer?->user;
        }

        return $conversation->mype?->user;
    }

    private function markMessagesAsRead(Conversation $conversation, User $user): void
    {
        $conversation->messages()
            ->where('sender_user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function formatConversation(Conversation $conv, User $currentUser): array
    {
        $isMype = $currentUser->user_type === 'mype';
        $otherParty = $isMype ? $conv->freelancer : $conv->mype;

        $lastMessage = $conv->messages()->latest()->first();
        $unreadCount = $conv->messages()
            ->where('sender_user_id', '!=', $currentUser->id)
            ->whereNull('read_at')
            ->count();

        $otherUserName = $otherParty?->user?->name ?? ($isMype ? 'Freelancer' : 'MYPE');
        $otherPhoto = $otherParty?->profile_photo;

        return [
            'id' => $conv->id,
            'other_user' => [
                'id' => $otherParty?->user?->id,
                'name' => $otherUserName,
                'photo_url' => $otherPhoto ? $this->storageUrl($otherPhoto) : null,
            ],
            'last_message' => $lastMessage?->message ?? '',
            'last_message_at' => $conv->last_message_at?->toISOString() ?? $conv->created_at->toISOString(),
            'unread_count' => $unreadCount,
            'service_id' => $conv->service_id,
            'status' => $conv->status,
            'created_at' => $conv->created_at->toISOString(),
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        return $this->publicMediaUrl($path);
    }

    private function formatMessage(Message $msg, User $currentUser): array
    {
        return [
            'id' => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'sender' => [
                'id' => $msg->sender?->id,
                'name' => $msg->sender?->name,
            ],
            'is_mine' => $msg->sender_user_id === $currentUser->id,
            'message' => $msg->message,
            'read_at' => $msg->read_at,
            'created_at' => $msg->created_at->toISOString(),
        ];
    }
}

