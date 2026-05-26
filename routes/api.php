<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminOnboardingKeyController;
use App\Http\Controllers\Api\Admin\AdminTenantController;
use App\Http\Controllers\Api\Admin\AdminTenantRequestController;
use App\Http\Controllers\Api\Central\AuthController;
use App\Http\Controllers\Api\Central\OnboardingController;
use App\Http\Controllers\Api\Central\TenantRequestController;
use App\Http\Controllers\Api\Central\TenantResolveController;
use App\Http\Controllers\Api\Customer\CustomerAuthController;
use App\Http\Controllers\Api\Customer\CustomerOrderController;
use App\Http\Controllers\Api\Customer\MenuController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\PaymentController;
use App\Http\Controllers\Api\Customer\ReceiptController;
use App\Http\Controllers\Api\Customer\TableSessionController;
use App\Http\Controllers\Api\Tenant\CashierController;
use App\Http\Controllers\Api\Tenant\KitchenController;
use App\Http\Controllers\Api\Tenant\MenuCategoryController;
use App\Http\Controllers\Api\Tenant\MenuCollectionController;
use App\Http\Controllers\Api\Tenant\MenuItemController;
use App\Http\Controllers\Api\Tenant\ReportController;
use App\Http\Controllers\Api\Tenant\ShopController;
use App\Http\Controllers\Api\Tenant\ShopProfileController;
use App\Http\Controllers\Api\Tenant\StaffController;
use App\Http\Controllers\Api\Tenant\TableController;
use App\Http\Controllers\Api\Tenant\TenantCustomerController;
use App\Http\Controllers\Api\Tenant\TenantOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central / public — tenant resolve + tenant login + registration requests
|--------------------------------------------------------------------------
*/
Route::get('/tenants/resolve', TenantResolveController::class);
Route::post('/t/{tenant_slug}/login', [AuthController::class, 'login']);
Route::post('/tenant-requests', [TenantRequestController::class, 'store']);
Route::get('/tenant-requests/check-slug', [TenantRequestController::class, 'checkSlug']);
Route::post('/onboarding/verify', [OnboardingController::class, 'verify']);
Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);

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
    Route::post('/tenants/{id}/onboarding-key', [AdminOnboardingKeyController::class, 'generate']);
    Route::post('/tenants/{id}/onboarding-key/regenerate', [AdminOnboardingKeyController::class, 'regenerate']);
    Route::get('/tenant-requests', [AdminTenantRequestController::class, 'index']);
    Route::get('/tenant-requests/{tenantRequest}', [AdminTenantRequestController::class, 'show']);
    Route::post('/tenant-requests/{tenantRequest}/approve', [AdminTenantRequestController::class, 'approve']);
    Route::post('/tenant-requests/{tenantRequest}/reject', [AdminTenantRequestController::class, 'reject']);
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
            $user = $request->user();

            return [
                'status' => 200,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
            ];
        });

        Route::get('/shop-profile', [ShopProfileController::class, 'show']);
        Route::put('/shop-profile', [ShopProfileController::class, 'update']);
        Route::put('/settings', [ShopProfileController::class, 'updateSettings']);

        Route::middleware(['tenant.role:owner,manager,cashier,kitchen'])->group(function () {
            Route::get('/kitchen/orders', [KitchenController::class, 'index']);
            Route::get('/kitchen/orders/{order}', [KitchenController::class, 'show']);
            Route::patch('/kitchen/orders/{order}', [KitchenController::class, 'updateStatus']);
            Route::post('/kitchen/orders/{order}/approve', [KitchenController::class, 'approve']);
            Route::post('/kitchen/orders/{order}/reject', [KitchenController::class, 'reject']);
        });

        Route::middleware(['tenant.role:owner,manager,cashier'])->group(function () {
            Route::get('/cashier/orders', [CashierController::class, 'unpaid']);
            Route::get('/cashier/orders/{order}', [CashierController::class, 'show']);
            Route::post('/cashier/orders/{order}/bill', [CashierController::class, 'generateBill']);
            Route::post('/cashier/orders/{order}/cash', [CashierController::class, 'confirmCash']);

            Route::get('/orders', [TenantOrderController::class, 'index']);
            Route::get('/orders/{order}', [TenantOrderController::class, 'show']);
            Route::post('/orders/{order}/mark-paid', [TenantOrderController::class, 'markPaid']);

            Route::get('/customers', [TenantCustomerController::class, 'index']);
            Route::get('/customers/{customer}', [TenantCustomerController::class, 'show']);
        });

        Route::middleware(['tenant.role:owner,manager'])->group(function () {
            Route::get('/dashboard', [ReportController::class, 'dashboard']);

            Route::apiResource('staff', StaffController::class)->parameters(['staff' => 'id']);
            Route::apiResource('tables', TableController::class)->parameters(['tables' => 'id']);
            Route::post('/tables/{id}/toggle-ordering', [TableController::class, 'toggleOrdering']);
            Route::post('/tables/{id}/block-sessions', [TableController::class, 'blockSessions']);
            Route::post('/tables/{id}/reset-qr', [TableController::class, 'resetQrCode']);
            Route::post('/menu-categories/reorder', [MenuCategoryController::class, 'reorder']);
            Route::apiResource('menu-categories', MenuCategoryController::class)->parameters(['menu-categories' => 'id']);
            Route::apiResource('menu-items', MenuItemController::class)->parameters(['menu-items' => 'id']);
            Route::post('/menu-collections/reorder', [MenuCollectionController::class, 'reorder']);
            Route::apiResource('menu-collections', MenuCollectionController::class)->parameters(['menu-collections' => 'id']);
            Route::post('/menu-collections/{id}/items', [MenuCollectionController::class, 'attachItem']);
            Route::post('/menu-collections/{id}/items/reorder', [MenuCollectionController::class, 'reorderItems']);
            Route::delete('/menu-collections/{id}/items/{itemId}', [MenuCollectionController::class, 'detachItem']);
        });
    });

/*
|--------------------------------------------------------------------------
| Customer public — no auth required; tenant resolved by slug
|--------------------------------------------------------------------------
*/
Route::middleware(['tenant.slug'])
    ->prefix('s/{tenant_slug}')
    ->group(function () {
        Route::get('/shop', [ShopController::class, 'show']);
        Route::get('/table/{qr_token}/menu', MenuController::class);
        Route::post('/table-sessions', [TableSessionController::class, 'store']);

        Route::post('/auth/register', [CustomerAuthController::class, 'register']);
        Route::post('/auth/login', [CustomerAuthController::class, 'login']);

        Route::post('/table/{qr_token}/orders', [OrderController::class, 'store']);
        Route::patch('/orders/{order}/items', [OrderController::class, 'updateItems']);
        Route::get('/orders/{order}/status', [OrderController::class, 'status']);
        Route::post('/orders/{order}/apply-points', [OrderController::class, 'applyPoints']);
        Route::post('/orders/{order}/payments', [PaymentController::class, 'createSession']);
        Route::post('/payment-sessions/{session}/confirm-demo', [PaymentController::class, 'confirmDemo']);
        Route::get('/orders/{order}/receipt', [ReceiptController::class, 'show']);
        Route::get('/orders/{order}/receipt/download', [ReceiptController::class, 'download']);

        Route::post('/broadcasting/auth', function (Request $request) {
            return Broadcast::auth($request);
        });
    });

/*
|--------------------------------------------------------------------------
| Customer authenticated — Sanctum; tenant resolved by slug
|--------------------------------------------------------------------------
*/
Route::middleware(['tenant.slug', 'auth:sanctum'])
    ->prefix('s/{tenant_slug}')
    ->group(function () {
        Route::get('/me', [CustomerAuthController::class, 'me']);
        Route::post('/auth/logout', [CustomerAuthController::class, 'logout']);
        Route::get('/orders', [CustomerOrderController::class, 'index']);
        Route::get('/points', [CustomerOrderController::class, 'points']);
        Route::post('/orders/{order}/claim', [CustomerOrderController::class, 'claim']);
    });
