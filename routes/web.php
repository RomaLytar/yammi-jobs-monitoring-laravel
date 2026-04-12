<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DashboardController;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\JobDetailController;

Route::get('/', DashboardController::class)->name('jobs-monitor.dashboard');
Route::get('/{uuid}/{attempt}', JobDetailController::class)
    ->where('attempt', '[0-9]+')
    ->name('jobs-monitor.detail');
