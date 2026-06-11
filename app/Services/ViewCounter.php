<?php

namespace App\Services;

use App\Models\ResourceView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ViewCounter
{
    public function track(Request $request, Model $resource, string $type, string $column = 'views_count'): bool
    {
        $user = $request->user();

        if ($user === null || !$resource->getKey()) {
            return false;
        }

        $view = ResourceView::query()->firstOrCreate([
            'viewer_user_id' => $user->id,
            'resource_type' => $type,
            'resource_id' => $resource->getKey(),
            'viewed_on' => now()->toDateString(),
        ]);

        if (!$view->wasRecentlyCreated) {
            return false;
        }

        $resource->increment($column);
        $resource->refresh();

        return true;
    }
}
