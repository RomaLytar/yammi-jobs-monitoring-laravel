<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\DashboardController;

Route::get('/', DashboardController::class)->name('jobs-monitor.dashboard');
