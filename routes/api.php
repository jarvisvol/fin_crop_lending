<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PolicyController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Customer CRUD routes (protected)
    Route::apiResource('customers', CustomerController::class)->except(['store']);
    
    // Additional customer routes
    Route::prefix('customers')->group(function () {
        Route::get('search', [CustomerController::class, 'search']);
        Route::get('policy-type/{policyType}', [CustomerController::class, 'byPolicyType']);
        Route::get('stats/summary', [CustomerController::class, 'summaryStats']);
    });

    // Policy CRUD routes
    Route::apiResource('policies', PolicyController::class);
    
    // Additional policy routes
    Route::prefix('policies')->group(function () {
        Route::get('stats/summary', [PolicyController::class, 'stats']);
    });

});

// Health check (public)
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'API is running',
        'timestamp' => now()->toISOString()
    ]);
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});