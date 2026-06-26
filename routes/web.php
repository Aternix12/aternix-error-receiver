<?php

use App\Http\Controllers\DashboardController;
use App\Http\Middleware\DashboardAuth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(DashboardAuth::class)
    ->name('dashboard');
