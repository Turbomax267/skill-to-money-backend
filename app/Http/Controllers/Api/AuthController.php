<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
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

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'token' => ['required', 'string'],
        ]);

        $result = $this->authService->verifyEmail($data['email'], $data['token']);

        if ($result === null) {
            return $this->error('El enlace de verificaciÃ³n no es vÃ¡lido o expirÃ³.', [
                'token' => ['Solicita un nuevo enlace e intÃ©ntalo otra vez.'],
            ], 422);
        }

        return $this->success('Correo verificado correctamente.', $result);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $sent = $this->authService->sendPasswordResetLink($request->validated('email'));

        if (! $sent) {
            return $this->error('No se pudo enviar el enlace de recuperación.', ['email' => ['Inténtalo nuevamente.']], 500);
        }

        return $this->success('Te enviamos un enlace para recuperar tu contraseña.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $reset = $this->authService->resetPassword($request->validated());

        if (! $reset) {
            return $this->error('El enlace de recuperación no es válido o expiró.', ['token' => ['Token inválido.']], 422);
        }

        return $this->success('Contrasena actualizada correctamente.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user(), $request->bearerToken());

        return $this->success('Session closed.');
    }
}

