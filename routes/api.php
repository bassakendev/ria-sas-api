<?php

use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminFeedbackController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// GROUP 1: AUTHENTIFICATION (Public Routes)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// GROUP 5: SUBSCRIPTION PLANS (Public - no auth required)
Route::get('subscription/plans', [SubscriptionController::class, 'getPlans']);

// GROUP 1 + Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('user', [AuthController::class, 'user']);
    Route::put('user/profile', [AuthController::class, 'updateProfile']);
    Route::delete('user/account', [AuthController::class, 'deleteAccount']);

    // GROUP 3: CLIENTS
    Route::apiResource('clients', ClientController::class);

    // GROUP 2: INVOICES
    Route::apiResource('invoices', InvoiceController::class);
    Route::patch('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);

    // GROUP 4: DASHBOARD
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/export-invoices', [DashboardController::class, 'exportInvoices']);

    // GROUP 2 + 6: EXPORTS
    Route::get('invoices/export', [ExportController::class, 'invoicesCsv']);
    Route::get('clients/export', [ExportController::class, 'clientsCsv']);

    // GROUP 2: COMMUNICATION (Email/WhatsApp)
    Route::post('invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);
    Route::post('invoices/{invoice}/send-whatsapp', [InvoiceController::class, 'sendWhatsapp']);

    // GROUP 6: FEEDBACK
    Route::post('feedback', [FeedbackController::class, 'create']);
    Route::get('feedback', [FeedbackController::class, 'index']);
    Route::get('feedback/{feedback}', [FeedbackController::class, 'show']);
    Route::patch('feedback/{feedback}/mark-read', [FeedbackController::class, 'markAsRead']);
    Route::patch('feedback/{feedback}/close', [FeedbackController::class, 'close']);
    Route::delete('feedback/{feedback}', [FeedbackController::class, 'destroy']);

    // GROUP 5: SUBSCRIPTIONS
    Route::get('subscription', [SubscriptionController::class, 'getCurrent']);
    Route::post('subscription/upgrade', [SubscriptionController::class, 'upgrade']);
    Route::post('subscription/downgrade', [SubscriptionController::class, 'downgrade']);
    Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('subscription/reactivate', [SubscriptionController::class, 'reactivate']);
    Route::get('subscription/invoices', [SubscriptionController::class, 'getInvoices']);
    Route::get('subscription/usage', [SubscriptionController::class, 'getUsage']);

    // GROUP 5: STRIPE
    Route::post('stripe/create-checkout-session', [StripeController::class, 'createCheckoutSession']);
    Route::post('stripe/create-portal-session', [StripeController::class, 'createPortalSession']);
    Route::post('stripe/cancel-subscription', [StripeController::class, 'cancelSubscription']);
    Route::get('stripe/subscription-details', [StripeController::class, 'getSubscriptionDetails']);
    Route::get('stripe/customer', [StripeController::class, 'getCustomer']);
    Route::get('stripe/invoices', [StripeController::class, 'listInvoices']);
});

// GROUP 5: STRIPE WEBHOOK (Public)
Route::post('stripe/webhook', [StripeController::class, 'webhook']);

// ADMIN ROUTES (Superadmin only)
Route::prefix('admin')->middleware(['auth:sanctum', \App\Http\Middleware\SuperAdminMiddleware::class])->group(function () {
    // Overview
    Route::get('overview', [AdminController::class, 'overview']);

    // Users Management
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::patch('users/{user}/role', [AdminUserController::class, 'changeRole']);
    Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend']);
    Route::post('users/{user}/activate', [AdminUserController::class, 'activate']);
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    Route::post('users/{user}/assign-plan', [AdminUserController::class, 'assignPlan']);

    // Subscriptions Management
    Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);
    Route::patch('subscriptions/{subscription}/plan', [AdminSubscriptionController::class, 'changePlan']);
    Route::post('subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel']);

    // Subscription Plans Management
    Route::get('plans', [AdminSubscriptionPlanController::class, 'index']);
    Route::get('plans/{plan}', [AdminSubscriptionPlanController::class, 'show']);
    Route::patch('plans/{plan}', [AdminSubscriptionPlanController::class, 'update']);

    // Feedbacks Management
    Route::get('feedbacks', [AdminFeedbackController::class, 'index']);
    Route::get('feedbacks/{feedback}', [AdminFeedbackController::class, 'show']);
    Route::patch('feedbacks/{feedback}/status', [AdminFeedbackController::class, 'updateStatus']);
    Route::patch('feedbacks/{feedback}/response', [AdminFeedbackController::class, 'updateResponse']);
    Route::delete('feedbacks/{feedback}', [AdminFeedbackController::class, 'destroy']);

    // Analytics
    Route::get('stats', [AdminAnalyticsController::class, 'stats']);

    // Audit Logs
    Route::get('audit-logs', [AdminAuditLogController::class, 'index']);

    // Settings
    Route::get('settings', [AdminSettingsController::class, 'index']);
    Route::patch('settings', [AdminSettingsController::class, 'update']);
});
