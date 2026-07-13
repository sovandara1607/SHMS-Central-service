<?php

use App\Http\Controllers\LabReportApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::middleware('central-service.key')->group(function () {
    Route::get('/lab-reports/{labReportId}/status', [LabReportApiController::class, 'status']);
    Route::post('/lab-reports/{labReportId}/regenerate', [LabReportApiController::class, 'regenerate']);
});
