<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\PaymentController;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Clients
    Route::apiResource('clients', ClientController::class)->except(['destroy']);
    
    // Billings
    Route::apiResource('billings', BillingController::class)->except(['destroy']);

    // Payments
    Route::post('/billings/{billing}/payments', [PaymentController::class, 'store']);
});
