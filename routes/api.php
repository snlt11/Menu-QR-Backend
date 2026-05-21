<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminTenantController;
use App\Http\Controllers\Api\Central\AuthController;
use App\Http\Controllers\Api\Central\TenantResolveController;
use App\Http\Controllers\Api\Customer\MenuController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\PaymentController;
use App\Http\Controllers\Api\Tenant\CashierController;
use App\Http\Controllers\Api\Tenant\KitchenController;
use App\Http\Controllers\Api\Tenant\MenuCategoryController;
use App\Http\Controllers\Api\Tenant\MenuCollectionController;
use App\Http\Controllers\Api\Tenant\MenuItemController;
use App\Http\Controllers\Api\Tenant\ReportController;
use App\Http\Controllers\Api\Tenant\ShopProfileController;
use App\Http\Controllers\Api\Tenant\StaffController;
use App\Http\Controllers\Api\Tenant\TableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central / public — tenant resolve + tenant login
| (shop signup is admin-only — see admin routes below)
|--------------------------------------------------------------------------
*/
Route::get('/tenants/resolve', TenantResolveController::class);
Route::post('/t/{tenant_slug}/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Platform admin — login + tenant CRUD
|--------------------------------------------------------------------------
*/
Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::apiResource('tenants', AdminTenantController::class)->parameters(['tenants' => 'id']);
});

/*
|--------------------------------------------------------------------------
| Tenant staff — authed tenant.users; tenant context initialized FIRST
|--------------------------------------------------------------------------
*/
Route::middleware(['tenant.slug', 'auth:sanctum'])
    ->prefix('t/{tenant_slug}')
    ->group(function () {
        Route::get('/me', function (Request $request) {
            return ['status' => 200, 'data' => $request->user()];
        });

        Route::get('/shop-profile', [ShopProfileController::class, 'show']);
        Route::put('/shop-profile', [ShopProfileController::class, 'update']);

        Route::get('/kitchen/orders', [KitchenController::class, 'index']);
        Route::patch('/kitchen/orders/{order}', [KitchenController::class, 'updateStatus']);

        Route::get('/cashier/orders', [CashierController::class, 'unpaid']);
        Route::post('/cashier/orders/{order}/bill', [CashierController::class, 'generateBill']);
        Route::post('/cashier/orders/{order}/cash', [CashierController::class, 'confirmCash']);

        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);

        Route::apiResource('staff', StaffController::class)->parameters(['staff' => 'id']);
        Route::apiResource('tables', TableController::class)->parameters(['tables' => 'id']);
        Route::apiResource('menu-categories', MenuCategoryController::class)->parameters(['menu-categories' => 'id']);
        Route::apiResource('menu-items', MenuItemController::class)->parameters(['menu-items' => 'id']);
        Route::post('/menu-collections/reorder', [MenuCollectionController::class, 'reorder']);
        Route::apiResource('menu-collections', MenuCollectionController::class)->parameters(['menu-collections' => 'id']);
        Route::post('/menu-collections/{id}/items', [MenuCollectionController::class, 'attachItem']);
        Route::delete('/menu-collections/{id}/items/{itemId}', [MenuCollectionController::class, 'detachItem']);
    });

/*
|--------------------------------------------------------------------------
| Customer public — no auth required; tenant resolved by slug
|--------------------------------------------------------------------------
*/
Route::middleware(['tenant.slug'])
    ->prefix('s/{tenant_slug}')
    ->group(function () {
        Route::get('/table/{qr_token}/menu', MenuController::class);
        Route::post('/table/{qr_token}/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}/status', [OrderController::class, 'status']);
        Route::post('/orders/{order}/apply-points', [OrderController::class, 'applyPoints']);
        Route::post('/orders/{order}/payments', [PaymentController::class, 'createSession']);
        Route::post('/payment-sessions/{session}/confirm-demo', [PaymentController::class, 'confirmDemo']);
    });
