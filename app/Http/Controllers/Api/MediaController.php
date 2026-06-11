<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function show(string $path): BinaryFileResponse
    {
        $normalizedPath = ltrim($path, '/');
        $disk = Storage::disk('public');

        abort_unless($disk->exists($normalizedPath), 404);
        $fullPath = $disk->path($normalizedPath);

        return response()->file($fullPath, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
