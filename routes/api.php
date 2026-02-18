<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StripeController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    // Public routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        // Clients
        Route::apiResource('clients', ClientController::class);

        // Services
        Route::apiResource('services', ServiceController::class);

        // Invoices
        Route::apiResource('invoices', InvoiceController::class);
        Route::patch('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid']);
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Exports
        Route::get('invoices/export/csv', [ExportController::class, 'invoicesCsv']);
        Route::get('clients/export/csv', [ExportController::class, 'clientsCsv']);

        // Stripe
        Route::post('stripe/create-checkout-session', [StripeController::class, 'createCheckoutSession']);
    });

    // Stripe webhook (public)
    Route::post('stripe/webhook', [StripeController::class, 'handleWebhook']);
});
