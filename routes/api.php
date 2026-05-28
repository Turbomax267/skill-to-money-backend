<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RecommendationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register/freelancer', [AuthController::class, 'registerFreelancer']);
    Route::post('/register/mype', [AuthController::class, 'registerMype']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.api');
});

Route::middleware('auth.api')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'store']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/skills', [ProfileController::class, 'updateSkills']);
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
    Route::patch('/profile/description', [ProfileController::class, 'updateDescription']);
    Route::patch('/profile/social-links', [ProfileController::class, 'updateSocialLinks']);
    Route::get('/recommendations', [RecommendationController::class, 'index']);
});
