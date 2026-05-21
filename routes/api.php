<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\StoryProgressController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth routes (public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Storage routes (public)
Route::post('/storage/upload', [StorageController::class, 'upload']);

// SSE image generation (public — auth via ?token= query param)
Route::get('/stories/{story}/generate-images', [StoryController::class, 'generateImages']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', [UserController::class, 'profile']);
    Route::put('/user', [UserController::class, 'updateProfile']);
    Route::post('/user/avatar', [UserController::class, 'updateAvatar']);

    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/achievements/play-minutes', [AchievementController::class, 'playMinutes']);
    Route::post('/achievements/play-sessions', [AchievementController::class, 'logPlay']);

    Route::get('/stories', [StoryController::class, 'index']);
    Route::post('/stories', [StoryController::class, 'generate']);
    Route::get('/stories/{story}', [StoryController::class, 'show']);
    Route::post('/stories/{story}/questions/{question}/answer', [StoryProgressController::class, 'answer']);
    Route::post('/stories/{story}/complete', [StoryProgressController::class, 'complete']);

    Route::post('/chatbot/chat', [ChatbotController::class, 'chat']);
    Route::get('/chatbot/sessions', [ChatbotController::class, 'index']);
    Route::get('/chatbot/sessions/{session}', [ChatbotController::class, 'show']);
    Route::delete('/chatbot/sessions/{session}', [ChatbotController::class, 'destroy']);
});
