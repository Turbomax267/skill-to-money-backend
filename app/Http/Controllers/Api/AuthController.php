<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterFreelancerRequest;
use App\Http\Requests\Auth\RegisterMypeRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthServiceInterface $authService)
    {
    }

    public function registerFreelancer(RegisterFreelancerRequest $request): JsonResponse
    {
        $result = $this->authService->registerFreelancer($request->validated());

        return $this->success('Freelancer account created.', $result, 201);
    }

    public function registerMype(RegisterMypeRequest $request): JsonResponse
    {
        $result = $this->authService->registerMype($request->validated());

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
