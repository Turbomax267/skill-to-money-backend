<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('broadcasting.default') === 'pusher' && !class_exists(\Pusher\Pusher::class)) {
            config(['broadcasting.default' => 'log']);
        }

        Broadcast::routes(['middleware' => ['auth.api'], 'prefix' => 'api']);

        require base_path('routes/channels.php');
    }
}
