<?php

use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminProductController;
use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminCustomRequestController;
use App\Http\Controllers\Api\V1\Admin\AdminContentController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingsController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CUSTOMER AUTH ROUTES (web guard)
|--------------------------------------------------------------------------
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:web')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| ADMIN AUTH ROUTES (admin guard)
|--------------------------------------------------------------------------
*/

Route::post('/auth/admin/login', [AuthController::class, 'adminLogin']);

Route::middleware('auth:admin')->group(function () {
    Route::post('/auth/admin/logout', [AuthController::class, 'adminLogout']);
    Route::get('/auth/admin/me', [AuthController::class, 'adminMe']);
});

/*
|--------------------------------------------------------------------------
| ADMIN PROTECTED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:admin', 'role:admin'])
    ->prefix('admin')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        // Products — custom routes BEFORE apiResource to avoid {id} conflict
        Route::post('/products/{id}/duplicate', [AdminProductController::class, 'duplicate']);
        Route::post('/products/{id}/images', [AdminProductController::class, 'uploadImage']);
        Route::delete('/products/{id}/images/{imageId}', [AdminProductController::class, 'removeImage']);
        Route::apiResource('products', AdminProductController::class);

        // Categories
        Route::apiResource('categories', AdminCategoryController::class);

        // Orders — no store (orders created by customers)
        Route::apiResource('orders', AdminOrderController::class)->except(['store']);

        // Customers — no store (customers register themselves)
        Route::apiResource('customers', AdminCustomerController::class)->except(['store']);

        // Reviews — no store
        Route::apiResource('reviews', AdminReviewController::class)->except(['store']);

        // Custom Requests — no store
        Route::apiResource('custom-requests', AdminCustomRequestController::class)->except(['store']);

        // Content
        Route::apiResource('contents', AdminContentController::class);

        // Settings — only index + update
        Route::get('/settings', [AdminSettingsController::class, 'index']);
        Route::put('/settings', [AdminSettingsController::class, 'update']);
    });

/*
|--------------------------------------------------------------------------
| CUSTOMER PROTECTED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:web', 'role:customer'])
    ->prefix('customer')
    ->group(function () {

        Route::get('/profile', function () {
            return response()->json(['message' => 'Customer Profile']);
        });
    });