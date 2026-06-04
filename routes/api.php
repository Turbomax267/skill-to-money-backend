<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\PeruController;
use App\Http\Controllers\Api\MessagingController;
use App\Http\Controllers\Api\ProfilesController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('peru')->group(function (): void {
    Route::get('/dni/{dni}', [PeruController::class, 'dni']);
    Route::get('/ruc/{ruc}', [PeruController::class, 'ruc']);
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/register/freelancer', [AuthController::class, 'registerFreelancer']);
    Route::post('/register/mype', [AuthController::class, 'registerMype']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.api');
});

Route::middleware('auth.api')->group(function (): void {
    Route::get('/users', [UsersController::class, 'index']);
    Route::get('/profiles', [ProfilesController::class, 'index']);
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::get('/marketplace', [MarketplaceController::class, 'index']);
    Route::get('/messaging', [MessagingController::class, 'index']);
    Route::get('/recommendations', [RecommendationController::class, 'index']);
});
