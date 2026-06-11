<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public int $recipientUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('user.' . $this->recipientUserId),
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
