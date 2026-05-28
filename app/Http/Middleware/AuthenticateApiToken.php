<?php

namespace App\Http\Middleware;

use App\Contracts\Repositories\AuthRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(private readonly AuthRepositoryInterface $authRepository)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if ($plainToken === null) {
            return $this->unauthorized();
        }

        $apiToken = $this->authRepository->findValidToken($plainToken);

        if ($apiToken === null || $apiToken->user === null) {
            return $this->unauthorized();
        }

        $this->authRepository->markTokenAsUsed($apiToken);
        Auth::setUser($apiToken->user);
        $request->setUserResolver(fn () => $apiToken->user);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
            'data' => null,
            'errors' => null,
        ], 401);
    }
}
