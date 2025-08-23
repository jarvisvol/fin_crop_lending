<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PolicyController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::get('auth/verify', [AuthController::class, 'verifyToken']); // Public verification

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
    ///

    Route::get('policies', [PolicyController::class, 'index']);
    Route::get('policies/{id}', [PolicyController::class, 'show']);
    Route::post('policies/calculate', [PolicyController::class, 'calculateMaturity']);
    
    // Subscription management
    Route::post('policies/subscribe', [PolicyController::class, 'subscribe']);
    Route::get('policies/subscriptions', [PolicyController::class, 'getSubscriptions']);
    Route::get('policies/stats', [PolicyController::class, 'getStats']);
    Route::post('policies/subscriptions/{id}/cancel', [PolicyController::class, 'cancelSubscription']);

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