<?php

use App\Http\Controllers\ErrorReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['service' => 'aternix-error-receiver', 'ok' => true]));

// Error reporting intake — used by Aternix desktop/web apps to auto-send reports.
Route::post('/error-reports', [ErrorReportController::class, 'store'])->middleware('throttle:60,1');
Route::get('/error-reports', [ErrorReportController::class, 'index']);
