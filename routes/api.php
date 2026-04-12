<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Yammi\JobsMonitor\Infrastructure\Http\Controller\ApiController;

Route::get('/jobs', [ApiController::class, 'jobs'])->name('jobs-monitor.api.jobs');
Route::get('/failures', [ApiController::class, 'failures'])->name('jobs-monitor.api.failures');
Route::get('/stats', [ApiController::class, 'stats'])->name('jobs-monitor.api.stats');
