<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StockIssueController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // admin/management guards on sensitive routes handled in controllers or middleware
        Route::get('/dashboard/summary', [ReportController::class, 'dashboard']);
        Route::get('/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/meta', [ReportController::class, 'meta']);
        Route::get('/dashboard/recent-movements', fn () => StockMovement::with('item:id,name,item_code')->orderByDesc('id')->limit(10)->get());

        Route::apiResource('items', ItemController::class);
        Route::get('/items/{item}/transactions', [StockController::class, 'itemTransactions']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('/units', [UnitController::class, 'index']);
        Route::post('/units', [UnitController::class, 'store']);
        Route::get('/units/{unit}', [UnitController::class, 'show']);
        Route::put('/units/{unit}', [UnitController::class, 'update']);
        Route::delete('/units/{unit}', [UnitController::class, 'destroy']);

        Route::apiResource('suppliers', SupplierController::class);
        Route::get('/suppliers/{supplier}/receipts', [SupplierController::class, 'receipts']);

        Route::get('/stock/balances', [StockController::class, 'balances']);
        Route::post('/stock/opening', [StockController::class, 'opening']);
        Route::post('/stock-in', [StockController::class, 'stockIn']);
        Route::post('/stock-out', [StockController::class, 'stockOut']);
        Route::post('/stock-adjustments', [StockController::class, 'adjust']);
        Route::post('/stock-adjustments/{movement}/reverse', [StockController::class, 'reverse']);
        Route::get('/stock/movements', [StockController::class, 'movements']);

        Route::get('/purchases', [PurchaseController::class, 'index']);
        Route::post('/purchases', [PurchaseController::class, 'store']);
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
        Route::put('/purchases/{purchase}', [PurchaseController::class, 'update']);
        Route::post('/purchases/{purchase}/submit', [PurchaseController::class, 'submit']);
        Route::post('/purchases/{purchase}/approve', [PurchaseController::class, 'approve'])->middleware('role:administrator,management');
        Route::post('/purchases/{purchase}/reject', [PurchaseController::class, 'reject'])->middleware('role:administrator,management');
        Route::post('/purchases/{purchase}/order', [PurchaseController::class, 'markOrdered']);
        Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel']);
        Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive']);
        Route::get('/purchases/{purchase}/receipts', [PurchaseController::class, 'receipts']);
        Route::post('/purchases/{purchase}/receipts/{receipt}/reverse', [PurchaseController::class, 'reverseReceipt']);
        Route::post('/purchases/{purchase}/post', [PurchaseController::class, 'post']);

        Route::get('/stock-issues', [StockIssueController::class, 'index']);
        Route::post('/stock-issues', [StockIssueController::class, 'store']);
        Route::get('/stock-issues/{issue}', [StockIssueController::class, 'show']);
        Route::post('/stock-issues/{issue}/post', [StockIssueController::class, 'post']);

        Route::get('/reports/low-stock', [ReportController::class, 'lowStock']);
        Route::get('/reports/daily', [ReportController::class, 'daily']);
        Route::get('/reports/daily-inventory', [ReportController::class, 'daily']);
        Route::get('/reports/monthly-inventory', [ReportController::class, 'monthly']);
        Route::get('/stock/monthly', [ReportController::class, 'monthly']);
        Route::get('/reports/stock-in', [ReportController::class, 'stockInReport']);
        Route::get('/reports/stock-out', [ReportController::class, 'stockOutReport']);
        Route::get('/reports/stock-valuation', [ReportController::class, 'valuation']);

        Route::get('/exports/{dataset}', [ExportController::class, 'download'])
            ->where('dataset', '[a-z\-]+');

        Route::get('/users', [UserController::class, 'index'])->middleware('role:administrator');
        Route::post('/users', [UserController::class, 'store'])->middleware('role:administrator');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('role:administrator');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('role:administrator');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('role:administrator,auditor,management');
        Route::get('/settings', [SettingController::class, 'index'])->middleware('role:administrator');
        Route::get('/settings/{key}', [SettingController::class, 'show'])->middleware('role:administrator');
        Route::put('/settings/{key}', [SettingController::class, 'update'])->middleware('role:administrator');
        Route::post('/settings/bulk', [SettingController::class, 'bulk'])->middleware('role:administrator');
    });
});
