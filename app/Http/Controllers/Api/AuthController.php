<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->success('Account created.', $result, 201);
    }

    public function registerFreelancer(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated(), 'freelancer');

        return $this->success('Freelancer account created.', $result, 201);
    }

    public function registerMype(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated(), 'mype');

        return $this->success('MYPE account created.', $result, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result === null) {
            return $this->error('Invalid credentials.', ['email' => ['Invalid credentials.']], 401);
        }

        return $this->success('Session started.', $result);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetLink($request->validated('email'));

        return $this->success('If the email exists, a recovery link will be sent.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user(), $request->bearerToken());

        return $this->success('Session closed.');
    }
}
