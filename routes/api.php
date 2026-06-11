<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ClientProjectController;
use App\Http\Controllers\Api\FavoritesController;
use App\Http\Controllers\Api\GeminiController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PeruController;
use App\Http\Controllers\Api\MessagingController;
use App\Http\Controllers\Api\ProfilesController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('peru')->group(function (): void {
    Route::get('/dni/{dni}', [PeruController::class, 'dni']);
    Route::get('/ruc/{ruc}', [PeruController::class, 'ruc']);
});

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*');

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/register/freelancer', [AuthController::class, 'registerFreelancer']);
    Route::post('/register/mype', [AuthController::class, 'registerMype']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.api');
});

Route::middleware('auth.api')->group(function (): void {
    Route::post('/gemini/analyze', [GeminiController::class, 'analyze']);

    Route::get('/catalog/categories', [CatalogController::class, 'categories']);
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::get('/catalog/{id}', [CatalogController::class, 'show']);

    Route::get('/services', [ServicesController::class, 'index']);
    Route::get('/services/{id}', [ServicesController::class, 'show']);

    Route::get('/client/projects', [ClientProjectController::class, 'index']);
    Route::post('/client/projects', [ClientProjectController::class, 'store']);
    Route::put('/client/projects/{clientProject}', [ClientProjectController::class, 'update']);
    Route::delete('/client/projects/{clientProject}', [ClientProjectController::class, 'destroy']);

    Route::get('/favorites', [FavoritesController::class, 'index']);
    Route::post('/favorites', [FavoritesController::class, 'store']);
    Route::delete('/favorites/{freelancerProfileId}', [FavoritesController::class, 'destroy']);

    Route::get('/users', [UsersController::class, 'index']);
    Route::get('/profiles', [ProfilesController::class, 'index']);
    Route::get('/profile', [ProfilesController::class, 'show']);
    Route::get('/profile/skill-options', [ProfilesController::class, 'skillOptions']);
    Route::put('/profile', [ProfilesController::class, 'update']);
    Route::patch('/profile/description', [ProfilesController::class, 'updateDescription']);
    Route::patch('/profile/social-links', [ProfilesController::class, 'updateSocialLinks']);
    Route::patch('/profile/skills', [ProfilesController::class, 'updateSkills']);
    Route::post('/profile/photo', [ProfilesController::class, 'updatePhoto']);
    Route::get('/marketplace', [MarketplaceController::class, 'index']);
    Route::get('/freelancer/services', [MarketplaceController::class, 'services']);
    Route::post('/freelancer/services', [MarketplaceController::class, 'storeService']);
    Route::put('/freelancer/services/{service}', [MarketplaceController::class, 'updateService']);
    Route::delete('/freelancer/services/{service}', [MarketplaceController::class, 'deleteService']);
    Route::get('/freelancer/portfolio', [MarketplaceController::class, 'portfolio']);
    Route::post('/freelancer/portfolio', [MarketplaceController::class, 'storePortfolioProject']);
    Route::post('/freelancer/portfolio/{portfolioProject}', [MarketplaceController::class, 'updatePortfolioProject']);
    Route::put('/freelancer/portfolio/{portfolioProject}', [MarketplaceController::class, 'updatePortfolioProject']);
    Route::delete('/freelancer/portfolio/{portfolioProject}', [MarketplaceController::class, 'deletePortfolioProject']);
    Route::get('/messaging', [MessagingController::class, 'index']);
    Route::get('/recommendations', [RecommendationController::class, 'index']);
});
