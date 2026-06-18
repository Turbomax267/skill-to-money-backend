<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public int $recipientUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recipientUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'mype_profile_id' => $this->conversation->mype_profile_id,
            'freelancer_profile_id' => $this->conversation->freelancer_profile_id,
        ];
    }
}
