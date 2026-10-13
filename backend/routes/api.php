<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\StockIssueController;
use App\Http\Controllers\Api\ReportController;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/dashboard', [ReportController::class, 'dashboard']);
    Route::apiResource('items', ItemController::class);
    Route::apiResource('suppliers', SupplierController::class);

    Route::get('/stock/balances', [StockController::class, 'balances']);
    Route::post('/stock/opening', [StockController::class, 'opening']);
    Route::get('/stock/movements', [StockController::class, 'movements']);
    Route::get('/stock/monthly', [ReportController::class, 'monthly']);
    Route::get('/reports/daily', [ReportController::class, 'daily']);
    Route::get('/reports/low-stock', [ReportController::class, 'lowStock']);

    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::post('/purchases', [PurchaseController::class, 'store']);
    Route::post('/purchases/{purchase}/post', [PurchaseController::class, 'post']);

    Route::get('/stock-issues', [StockIssueController::class, 'index']);
    Route::post('/stock-issues', [StockIssueController::class, 'store']);
    Route::post('/stock-issues/{issue}/post', [StockIssueController::class, 'post']);
});
