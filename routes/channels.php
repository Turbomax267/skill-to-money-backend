<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function (mixed $user, int $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (!$conversation) {
        return false;
    }

    $profile = null;

    if ($user->user_type === 'mype') {
        $profile = $user->mypeProfile;
        return $profile && $profile->id === $conversation->mype_profile_id;
    }

    if ($user->user_type === 'freelancer') {
        $profile = $user->freelancerProfile;
        return $profile && $profile->id === $conversation->freelancer_profile_id;
    }

    return false;
});

Broadcast::channel('user.{userId}', fn(mixed $user, int $userId) => (int) $user->id === $userId);
