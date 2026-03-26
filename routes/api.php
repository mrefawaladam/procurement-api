<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\RequestController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/requests', [RequestController::class, 'index']);
    Route::get('/requests/export', [RequestController::class, 'exportCsv']);
    Route::post('/requests', [RequestController::class, 'store']);
    Route::post('/requests/{id}/approve', [RequestController::class, 'approve']);
    Route::post('/requests/{id}/reject', [RequestController::class, 'reject']);
    Route::post('/requests/{id}/procure', [RequestController::class, 'procure']);
    Route::post('/requests/{id}/check-stock', [RequestController::class, 'checkStock']);

    // Reports
    Route::get('/reports/monthly-categories', [\App\Http\Controllers\Api\ReportController::class, 'monthlyCategories']);
    Route::get('/reports/lead-time', [\App\Http\Controllers\Api\ReportController::class, 'averageLeadTime']);
});
