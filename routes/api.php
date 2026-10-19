<?php

use App\Http\Controllers\Api\AdAccountController;
use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\AdDailyStatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FundController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', DashboardController::class);

Route::apiResource('ad-accounts', AdAccountController::class);
Route::apiResource('ads', AdController::class);
Route::apiResource('funds', FundController::class);

Route::post('daily-stats/bulk', [AdDailyStatController::class, 'bulk']);
Route::apiResource('daily-stats', AdDailyStatController::class)
    ->except('show')
    ->parameters(['daily-stats' => 'dailyStat']);
